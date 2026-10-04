<?php

declare(strict_types=1);

namespace App\Components\DiscussionControl;

use App\Components\BaseControl;
use App\Models\DiscussionModel;
use App\Utils\AppConstants;
use Nette\Application\UI\Form;


/**
 * Discussion thread with a form for new posts.
 *
 * Threads: "main" (public discussion), "sifry_<year>_<checkpoint>" (discussion below a cipher),
 * "chat" (organizers only). The special value "any" shows posts of all threads (administration)
 * and lets the author choose the thread.
 */
final class DiscussionControl extends BaseControl
{
	public const MainThread = 'main';
	public const CipherThreadPrefix = 'sifry';
	public const ChatThread = 'chat';
	public const AnyThread = 'any';

	private string $thread = self::MainThread;
	private ?string $teamName = null;

	/** @var list<string>|null */
	private ?array $threads = null;


	public function __construct(
		private readonly DiscussionModel $discussionModel,
	) {
	}


	public static function cipherThread(?int $year, ?int $checkpoint): string
	{
		return self::CipherThreadPrefix . '_' . $year . '_' . $checkpoint;
	}


	public function setThread(string $thread): static
	{
		$this->thread = $thread;
		return $this;
	}


	public function setTeamName(?string $teamName): static
	{
		$this->teamName = $teamName;
		return $this;
	}


	public function render(): void
	{
		$isMaster = $this->thread === self::AnyThread;

		$this->renderTemplate(__DIR__ . '/discussion.latte', [
			'data' => $isMaster
				? $this->discussionModel->getAll()
				: $this->discussionModel->getAllByThread($this->thread),
			'requireCaptcha' => $this->teamId === null,
			'isMasterDiscussion' => $isMaster,
		]);
	}


	protected function createComponentDiscussionForm(): Form
	{
		$form = new Form;
		$form->addText('name', 'Jméno:')
			->setRequired('Zadejte prosím své jméno.')
			->addRule($form::MaxLength, 'Jméno může mít maximálně 255 znaků', 255);

		if ($this->teamName !== null) {
			$form->addText('team', 'Tým:')
				->setDisabled()
				->setDefaultValue($this->teamName);
		} else {
			$form->addText('team', 'Tým:')
				->setRequired(false)
				->addRule($form::MaxLength, 'Název týmu může mít maximálně 255 znaků', 255);
		}

		$form->addTextArea('message', 'Zpráva:')
			->setRequired('Nelze odeslat prázdnou zprávu.')
			->setHtmlAttribute('rows', 5)
			->addRule($form::MaxLength, 'Zpráva může mít maximálně 5000 znaků', 5000);

		if ($this->teamId === null) {
			$form->addText('captcha', 'Počet dílků puzzle na logu Palapeli:')
				->setRequired('Vyplňte prosím kontrolní otázku proti spamu.');
		}

		if ($this->thread === self::AnyThread) {
			$form->addSelect('masterThread', 'Vlákno', $this->getThreads());
		}

		$form->addHidden('thread', $this->thread);
		$form->addSubmit('submit', 'ODESLAT');
		$form->onSuccess[] = $this->discussionFormSucceeded(...);
		return $form;
	}


	/**
	 * @param array{name: string, team?: string, message: string, captcha?: string, masterThread?: int, thread: string} $values
	 */
	private function discussionFormSucceeded(Form $form, array $values): void
	{
		if (isset($values['captcha']) && trim($values['captcha']) !== (string) AppConstants::CaptchaAnswer) {
			$form->addError('Špatně vyplněná kontrolní otázka proti spamu.');
			return;
		}

		// only organizers may use HTML; posts are displayed unescaped
		$message = $this->teamId === AppConstants::OrgTeamId
			? $values['message']
			: strip_tags($values['message']);

		$thread = isset($values['masterThread'])
			? $this->getThreads()[$values['masterThread']]
			: $values['thread'];

		$this->discussionModel->insertPost(
			nl2br($message),
			strip_tags($values['name']),
			$this->teamId,
			strip_tags($values['team'] ?? ''),
			$thread,
		);

		$this->flashMessage('Příspěvek do diskuse byl úspěšně odeslán.', 'success');
		$this->redirect('this');
	}


	/**
	 * @return list<string>
	 */
	private function getThreads(): array
	{
		return $this->threads ??= $this->discussionModel->getThreads();
	}
}
