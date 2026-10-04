<?php

declare(strict_types=1);

namespace App\Components\ActionScreen;

use App\Components\BaseControl;
use App\Models\CiphersModel;
use App\Models\LogModel;
use App\Models\LogType;
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use App\Utils\AppConstants;
use Nette\Application\UI\Form;
use Nette\Database\Row;
use Nette\Utils\DateTime;


/**
 * Palainfo "AKCE" screen: teams enter checkpoint codes and password solutions, set their
 * departure times, ask for total hints ("totálka") and can quit the game.
 */
final class ActionScreen extends BaseControl
{
	/** Code the team has to type to confirm it wants to quit the game. */
	public const EndCode = 'JEZIMADEMDOM';

	/** Confirmation screens that can be opened right after the page loads. */
	public const DeadScreen = 0;
	public const EndScreen = 1;

	private ?int $defaultScreen = null;
	private ?Row $lastCheckpointData = null;


	public function __construct(
		private readonly ResultsModel $resultsModel,
		private readonly YearsModel $yearsModel,
		private readonly TeamsModel $teamsModel,
		private readonly CiphersModel $ciphersModel,
		private readonly LogModel $logModel,
	) {
	}


	/**
	 * Opens the given confirmation screen (self::DeadScreen or self::EndScreen) after the page loads.
	 */
	public function setDefaultScreen(?int $screen): void
	{
		$this->defaultScreen = $screen;
	}


	public function render(): void
	{
		$teamId = $this->requireTeamId();
		$nextCheckpoint = $this->resultsModel->getFirstEmptyCheckpoint($teamId, $this->year);
		$endgame = $this->yearsModel->getEndgameData($this->year);
		$checkpointCount = (int) $endgame?->checkpoint_count;
		$hasPasswordSolution = $this->isWaitingForPasswordSolution($nextCheckpoint);

		$params = [
			'checkpointCount' => $checkpointCount,
			'nextCheckpointNumber' => $nextCheckpoint,
			'hasPasswordSolution' => $hasPasswordSolution,
			'hasFinishCipher' => (bool) $endgame?->has_finish_cipher,
			'defaultScreen' => $this->defaultScreen,
			'teamEnded' => true,
			'deadOpened' => false,
			'deadSolution' => null,
			'endCode' => self::EndCode,
			'lastCheckpointData' => null,
		];

		if ($this->teamsModel->hasTeamEnded($teamId, $this->year)) {
			if (self::hasReachedFinish($nextCheckpoint, $checkpointCount)) {
				$this->flashMessage('Hru jste úspěšně dokončili! Gratulujeme.', 'success');
			} else {
				$this->flashMessage('Již jste ukončili hru a ve hře tak nemůžete pokračovat.');
				$this->flashMessage(sprintf('Přijďte se podívat do cíle: %s (otevřen od %s)', $endgame?->finish_location, $endgame?->finish_open_time), 'info');
			}

			$this->flashAfterparty($endgame);

		} elseif ($this->yearsModel->hasGameEnded($this->year)) {
			$this->flashMessage(sprintf('Hra již skončila, děkujeme za účast. Již nelze zadávat příchody na stanoviště, můžete pouze upravit odchody ze stanovišť na záložce KARTA. Přijďte se za námi podívat do cíle: %s', $endgame?->finish_location), 'info');
			$this->flashAfterparty($endgame);

		} else {
			$this->lastCheckpointData = $last = $this->resultsModel->getLastCheckpointData($teamId, $this->year);
			$params['teamEnded'] = false;
			$params['lastCheckpointData'] = $last;
			if ($last) {
				$params['deadOpened'] = $this->resultsModel->hasTeamOpenedDead($teamId, $this->year, (int) $last->checkpoint_number);
				$params['deadSolution'] = $this->ciphersModel->getDeadSolution($this->year, (int) $last->checkpoint_number);
			}
		}

		$this->renderTemplate(__DIR__ . '/actionScreen.latte', $params);
	}


	protected function createComponentCodeInput(): Form
	{
		$form = new Form;
		$form->addText('code');
		$form->addSubmit('send', '');
		$form->onSuccess[] = $this->codeInputSucceeded(...);
		return $form;
	}


	/**
	 * Handles both checkpoint codes and solutions of password-type ciphers.
	 * @param array{code: string} $values
	 */
	private function codeInputSucceeded(Form $form, array $values): void
	{
		$teamId = $this->requireTeamId();
		$nextCheckpoint = $this->resultsModel->getFirstEmptyCheckpoint($teamId, $this->year);

		if ($this->isWaitingForPasswordSolution($nextCheckpoint)) {
			$this->handlePasswordSolution($nextCheckpoint - 1, $values['code']);
		} else {
			$this->handleCheckpointCode($nextCheckpoint, $values['code']);
		}

		$this->redirect('this');
	}


	/**
	 * The team solved a cipher whose solution is a password: the password counts as leaving the
	 * checkpoint and reveals the location of the next one.
	 */
	private function handlePasswordSolution(int $checkpoint, string $solution): void
	{
		$teamId = $this->requireTeamId();
		if (!$this->ciphersModel->checkSolution($this->year, $checkpoint, $solution)) {
			$this->flashMessage('Řešení není správně.', 'error');
			return;
		}

		$this->resultsModel->insertResultsRow($teamId, $this->year, $checkpoint, exitTime: new DateTime);
		$checkpointCount = $this->yearsModel->getCheckpointCount($this->year);

		if ($checkpoint + 1 === $checkpointCount) {
			// the finish password
			$this->teamsModel->teamEnded($teamId, $this->year);
			$this->flashMessage(sprintf(
				'Gratulujeme k dokončení Palapeli! Hru jste dokončili jako %s., výsledky se započítanými totálkami budou vyhlášeny po skončení hry.',
				$this->resultsModel->getTeamsArrivedCount($this->year, $checkpoint),
			), 'success');
			return;
		}

		$nextLocation = $this->ciphersModel->getDeadSolution($this->year, $checkpoint);
		$this->flashMessage(sprintf('Správně! Umístění dalšího stanoviště: %s', $nextLocation), 'success');
		$this->logModel->log(
			LogType::MessageFromOrg,
			$teamId,
			$checkpoint,
			$this->year,
			sprintf('Umístění stanoviště %s: %s', $checkpoint + 1, $nextLocation),
		);
	}


	/**
	 * The team arrived to a checkpoint and entered the code found there.
	 */
	private function handleCheckpointCode(int $checkpoint, string $code): void
	{
		$teamId = $this->requireTeamId();
		if ($this->yearsModel->hasGameEnded($this->year)) {
			$this->flashMessage('Bohužel jste kód nestihli zadat před koncem hry.', 'error');
			return;
		}

		if (!$this->ciphersModel->checkCode($this->year, $checkpoint, $code)) {
			$this->flashMessage('Nesprávně zadaný kód', 'error');
			return;
		}

		$this->resultsModel->insertResultsRow($teamId, $this->year, $checkpoint, entryTime: new DateTime, usedHint: false);
		$this->logModel->log(LogType::EnterCheckpoint, $teamId, $checkpoint, $this->year);

		$checkpointCount = $this->yearsModel->getCheckpointCount($this->year);
		$order = $this->resultsModel->getTeamsArrivedCount($this->year, $checkpoint);

		if ($checkpoint === $checkpointCount) {
			$this->teamsModel->teamEnded($teamId, $this->year);
			$this->flashMessage(sprintf('Gratulujeme k dokončení Palapeli! Hru jste dokončili jako %s., výsledky se započítanými totálkami budou vyhlášeny po skončení hry.', $order), 'success');
		} elseif ($checkpoint === $checkpointCount - 1) {
			$this->flashMessage(sprintf('Dorazili jste do cíle jako %s.', $order), 'success');
		} elseif ($checkpoint === 0) {
			$this->flashMessage(sprintf('Vítejte na startu Palapeli. Kód startovní šifry jste zadali jako %s.', $order), 'success');
		} else {
			$this->flashMessage(sprintf('Dorazili jste na stanoviště %s jako %s.', $checkpoint, $order), 'success');
		}
	}


	protected function createComponentExitTimeInput(): Form
	{
		$form = new Form;
		$form->addText('exitTime', '')
			->setHtmlType('time')
			->setDefaultValue($this->lastCheckpointData?->exit_time_fmt ?: AppConstants::EmptyTimeValue);
		$form->addSubmit('send', '');
		$form->onSuccess[] = $this->exitTimeInputSucceeded(...);
		return $form;
	}


	/**
	 * @param array{exitTime: string} $values
	 */
	private function exitTimeInputSucceeded(Form $form, array $values): void
	{
		$teamId = $this->requireTeamId();
		$checkpoint = $this->resultsModel->getLastCheckpointNumber($teamId, $this->year);

		try {
			// an empty value means "now", the same as the "TEĎ" button
			$time = new DateTime($values['exitTime'] ?: 'now');
		} catch (\Exception) {
			$this->flashMessage('Nesprávně zadaný čas odchodu.', 'error');
			$this->redirect('this');
		}

		if ($checkpoint !== null) {
			$this->resultsModel->insertResultsRow($teamId, $this->year, $checkpoint, exitTime: $time);
			$this->flashMessage('Odchod ze stanoviště byl nastaven na ' . $time->format('H:i'), 'success');
		}

		$this->redirect('this');
	}


	protected function createComponentExitTimeNow(): Form
	{
		$form = new Form;
		$form->addSubmit('send', 'TEĎ')->setHtmlAttribute('class', 'now');
		$form->onSuccess[] = $this->exitTimeNowSucceeded(...);
		return $form;
	}


	private function exitTimeNowSucceeded(): void
	{
		$teamId = $this->requireTeamId();
		$checkpoint = $this->resultsModel->getLastCheckpointNumber($teamId, $this->year);
		$now = new DateTime;

		if ($checkpoint !== null) {
			$this->resultsModel->insertResultsRow($teamId, $this->year, $checkpoint, exitTime: $now);
			$this->flashMessage('Odchod ze stanoviště byl nastaven na ' . $now->format('H:i'), 'success');
		}

		$this->redirect('this');
	}


	protected function createComponentAskForDead(): Form
	{
		$form = new Form;
		$form->addText('code');
		$form->addSubmit('send', '');
		$form->onSuccess[] = $this->askForDeadSucceeded(...);
		return $form;
	}


	/**
	 * The team asks for the total hint; it has to confirm it by typing the code of the current checkpoint again.
	 * @param array{code: string} $values
	 */
	private function askForDeadSucceeded(Form $form, array $values): void
	{
		$teamId = $this->requireTeamId();
		$checkpoint = $this->resultsModel->getLastCheckpointNumber($teamId, $this->year);

		if ($checkpoint === null || !$this->ciphersModel->checkCode($this->year, $checkpoint, $values['code'])) {
			$this->flashMessage('Nesprávně zadaný kód', 'error');
			$this->getPresenter()->redirect('PalaInfo:', self::DeadScreen);
		}

		$deadSolution = $this->ciphersModel->getDeadSolution($this->year, $checkpoint);
		$this->resultsModel->insertResultsRow($teamId, $this->year, $checkpoint, exitTime: new DateTime, usedHint: true);
		$this->flashMessage(sprintf('Řešením šifry číslo %s je: %s', $checkpoint, $deadSolution), 'info');
		$this->logModel->log(LogType::OpenDead, $teamId, $checkpoint, $this->year);
		$this->redirect('this');
	}


	protected function createComponentAskForEnd(): Form
	{
		$form = new Form;
		$form->addText('code');
		$form->addSubmit('send', '');
		$form->onSuccess[] = $this->askForEndSucceeded(...);
		return $form;
	}


	/**
	 * @param array{code: string} $values
	 */
	private function askForEndSucceeded(Form $form, array $values): void
	{
		$teamId = $this->requireTeamId();
		if (mb_strtoupper($values['code']) !== self::EndCode) {
			$this->flashMessage('Nesprávně zadaný kód', 'error');
			$this->getPresenter()->redirect('PalaInfo:', self::EndScreen);
		}

		$this->teamsModel->teamEnded($teamId, $this->year);
		$this->logModel->log(LogType::EndGame, $teamId, null, $this->year);
		$this->redirect('this');
	}


	/**
	 * Whether the team stands on a checkpoint whose cipher is solved by entering a password
	 * and has not entered it yet.
	 */
	private function isWaitingForPasswordSolution(int $nextCheckpoint): bool
	{
		$teamId = $this->requireTeamId();
		return $nextCheckpoint !== 0
			&& $this->ciphersModel->hasCheckpointPasswordSolution($this->year, $nextCheckpoint - 1)
			&& !$this->resultsModel->hasTeamLeft($teamId, $this->year, $nextCheckpoint - 1);
	}


	/**
	 * Whether the team got past the last checkpoint (the finish), i.e. it completed the game
	 * instead of quitting it.
	 */
	public static function hasReachedFinish(int $nextCheckpoint, int $checkpointCount): bool
	{
		return $checkpointCount > 0 && $nextCheckpoint >= $checkpointCount;
	}


	private function flashAfterparty(?Row $endgame): void
	{
		if ($endgame?->afterparty_location) {
			$this->flashMessage(sprintf('Rádi vás uvidíme i na afterparty: %s (od %s)', $endgame->afterparty_location, $endgame->afterparty_time), 'info');
		}
	}
}
