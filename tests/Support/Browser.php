<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Session\TeamSession;
use Nette\Application\Application;
use Nette\Application\BadRequestException;
use Nette\Application\Response;
use Nette\Application\Responses\RedirectResponse;
use Nette\Database\Connection;
use Nette\DI\Container;
use Nette\Http\Request;
use Nette\Http\UrlScript;


/**
 * Simulated browser (one phone of a team): every request runs the whole Nette application in this
 * PHP process – router, presenter, components, forms, templates, session, redirects with flash messages.
 * All requests share the test database connection, so the test transaction covers them.
 */
final class Browser
{
	private const BaseUrl = 'http://palapeli.test';
	private const MaxRedirects = 5;

	/** @var array<string, string> */
	private array $cookies = [];

	/** response of the application to the last request */
	private ?Response $response = null;


	public function __construct(
		private readonly Connection $connection,
	) {
	}


	public function logIn(int $teamId, string $teamName): void
	{
		$this->withContainer('/', function (Container $container) use ($teamId, $teamName): void {
			$container->getByType(TeamSession::class)->logIn($teamId, $teamName);
		});
	}


	/**
	 * GET request, follows redirects.
	 */
	public function open(string $path): Page
	{
		return $this->request('GET', $path);
	}


	/**
	 * Submits a form exactly as a browser would from the given page (its action URL and current field
	 * values, overridden by $values). The page can be outdated, like on a second phone of the team.
	 * @param array<string, string> $values
	 */
	public function submit(Page $page, string $form, array $values = []): Page
	{
		$element = $page->getForm($form)
			?? throw new \LogicException("Form '$form' is not on the page {$page->url}.");

		$post = [];
		foreach ($element->querySelectorAll('input[name]') as $input) {
			$type = strtolower($input->getAttribute('type') ?? 'text');
			if ($input->hasAttribute('disabled') || ($type === 'submit' && $input->getAttribute('name') !== 'send')) {
				continue;
			}

			$post[$input->getAttribute('name')] = $input->getAttribute('value') ?? '';
		}

		return $this->request('POST', $element->getAttribute('action') ?: $page->url, $values + $post);
	}


	/**
	 * Submits a form of the AKCE screen that is not on any page the team has (forged or very old page).
	 * @param array<string, string> $values
	 */
	public function forge(string $form, array $values = []): Page
	{
		return $this->request('POST', '/pala-info/', $values + ['send' => '', '_do' => 'actionScreen-' . $form . '-submit']);
	}


	/**
	 * @param array<string, string> $post
	 */
	private function request(string $method, string $url, array $post = []): Page
	{
		for ($i = 0; $i <= self::MaxRedirects; $i++) {
			[$html, $exception] = $this->withContainer($url, function (Container $container): array {
				$exception = null;
				$application = $container->getByType(Application::class);
				$application->onResponse[] = function (Application $application, Response $response): void {
					$this->response = $response;
				};

				ob_start();
				try {
					$application->run();
				} catch (BadRequestException $e) {
					$exception = $e;
				} finally {
					$output = (string) ob_get_clean();
				}

				return [$output, $exception];
			}, $method, $post);
			$response = $this->takeResponse();

			if ($exception !== null) {
				return new Page($url, $exception->getHttpCode(), $exception->getMessage());
			}

			if (!$response instanceof RedirectResponse) {
				return new Page($url, 200, $html);
			}

			$url = $response->getUrl();
			$method = 'GET';
			$post = [];
		}

		throw new \LogicException("Too many redirects, the last one to $url.");
	}


	private function takeResponse(): ?Response
	{
		$response = $this->response;
		$this->response = null;
		return $response;
	}


	/**
	 * Runs the callback with a fresh DI container serving one HTTP request.
	 * @template T
	 * @param callable(Container): T $callback
	 * @param array<string, string> $post
	 * @return T
	 */
	private function withContainer(string $url, callable $callback, string $method = 'GET', array $post = []): mixed
	{
		$url = str_starts_with($url, 'http') ? $url : self::BaseUrl . $url;
		$httpRequest = new Request(
			new UrlScript($url, '/'),
			post: $post,
			cookies: $this->cookies,
			// Nette forms accept only same-origin submissions
			headers: ['sec-fetch-site' => 'same-origin'],
			method: $method,
			remoteAddress: '127.0.0.1',
		);

		// PHP keeps the ID of the last session in the process, the request must use the one of this browser
		session_id($this->cookies[session_name()] ?? '');

		$container = TestDatabase::createContainer();
		$container->addService('database.default.connection', $this->connection);
		$container->addService('http.request', $httpRequest);
		$container->initialize();

		try {
			return $callback($container);
		} finally {
			if (session_status() === PHP_SESSION_ACTIVE) {
				$this->cookies[session_name()] = session_id();
				session_write_close();
			}

			// PHP keeps the data of a closed session and Nette reads it, the next request may be of another browser
			$_SESSION = [];
		}
	}
}
