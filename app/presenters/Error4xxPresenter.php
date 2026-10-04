<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\Attributes\Requires;
use Nette\Application\BadRequestException;


/**
 * Error page for 4xx errors (forwarded from ErrorPresenter, cannot be accessed directly).
 */
#[Requires(forward: true)]
final class Error4xxPresenter extends BasePresenter
{
	public function renderDefault(BadRequestException $exception): void
	{
		$this->prepareHeading(match ($exception->getCode()) {
			404 => 'Stránka nenalezena',
			403 => 'Přístup zamítnut',
			405 => 'Nepodporovaná metoda',
			410 => 'Nedostupný obsah',
			default => 'Chyba',
		});

		// templates 403.latte, 404.latte, ... with 4xx.latte as the fallback
		$file = __DIR__ . "/templates/Error/{$exception->getCode()}.latte";
		$this->template->setFile(is_file($file) ? $file : __DIR__ . '/templates/Error/4xx.latte');
	}
}
