<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Components\ActionScreen\ActionScreen;
use App\Components\ActionScreen\ActionScreenFactory;
use App\Components\CardScreen\CardScreen;
use App\Components\CardScreen\CardScreenFactory;
use App\Components\CheckpointScreen\CheckpointScreen;
use App\Components\CheckpointScreen\CheckpointScreenFactory;
use App\Components\InfoScreen\InfoScreen;
use App\Components\InfoScreen\InfoScreenFactory;


/**
 * Palainfo: the in-game web app for teams registered in the current edition
 * (screens AKCE, KARTA, PŘÍCHODY and INFO). It always works with the current edition,
 * regardless of the edition selected in the archive.
 */
final class PalaInfoPresenter extends BasePresenter
{
	private int $currentYear;


	public function __construct(
		private readonly ActionScreenFactory $actionScreenFactory,
		private readonly InfoScreenFactory $infoScreenFactory,
		private readonly CheckpointScreenFactory $checkpointScreenFactory,
		private readonly CardScreenFactory $cardScreenFactory,
	) {
		parent::__construct();
	}


	protected function startup(): void
	{
		parent::startup();
		$this->currentYear = $this->yearsModel->getCurrentYearNumber();

		// forms of the screens may only be submitted by teams playing the current edition
		if ($this->getSignal() !== null && !$this->canPlay()) {
			$this->error('Palainfo je přístupné pouze týmům zaregistrovaným v aktuálním ročníku.', 403);
		}
	}


	protected function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->hasGameStarted = $this->yearsModel->hasGameStarted($this->currentYear);
		$this->template->hasGameEnded = $this->yearsModel->hasGameEnded($this->currentYear);
	}


	/**
	 * @param ?int $defaultScreen  confirmation screen to open, see ActionScreen::DeadScreen and EndScreen
	 */
	public function renderDefault(?int $defaultScreen = null): void
	{
		$this->getComponent('actionScreen')->setDefaultScreen($defaultScreen);
	}


	private function canPlay(): bool
	{
		return $this->teamId !== null
			&& $this->yearsModel->isTeamInCurrentYear($this->teamId)
			&& $this->yearsModel->hasGameStarted($this->currentYear);
	}


	protected function createComponentActionScreen(): ActionScreen
	{
		return $this->actionScreenFactory->create()
			->setTeamId($this->teamId)
			->setYear($this->currentYear);
	}


	protected function createComponentInfoScreen(): InfoScreen
	{
		return $this->infoScreenFactory->create()
			->setTeamId($this->teamId)
			->setYear($this->currentYear);
	}


	protected function createComponentCardScreen(): CardScreen
	{
		return $this->cardScreenFactory->create()
			->setTeamId($this->teamId)
			->setYear($this->currentYear);
	}


	protected function createComponentCheckpointScreen(): CheckpointScreen
	{
		return $this->checkpointScreenFactory->create()
			->setTeamId($this->teamId)
			->setYear($this->currentYear);
	}
}
