<?php

declare(strict_types=1);

namespace App\Components;

use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Control;
use Nette\Bridges\ApplicationLatte\DefaultTemplate;


/**
 * Ancestor of all UI components. The creating presenter passes the game year the component
 * works with and the logged-in team (if any).
 *
 * @property-read DefaultTemplate $template
 */
abstract class BaseControl extends Control
{
	public protected(set) int $year;
	public protected(set) ?int $teamId = null;


	public function setYear(int $year): static
	{
		$this->year = $year;
		return $this;
	}


	public function setTeamId(?int $teamId): static
	{
		$this->teamId = $teamId;
		return $this;
	}


	/**
	 * ID of the logged-in team; components that work with team data cannot be used anonymously.
	 * @throws ForbiddenRequestException
	 */
	protected function requireTeamId(): int
	{
		return $this->teamId ?? throw new ForbiddenRequestException('A team must be logged in.');
	}


	/**
	 * Renders the given template file with the given parameters.
	 * @param array<string, mixed> $params
	 */
	protected function renderTemplate(string $file, array $params = []): void
	{
		$template = $this->template;
		$template->setFile($file);
		$template->setParameters($params);
		$template->render();
	}
}
