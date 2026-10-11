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
 * departure times, ask for total hints ("totálka") and can quit the game. On a checkpoint with
 * a mandatory wait for the cipher (ciphers.wait_minutes, e.g. a campfire) it shows a countdown
 * until the team enters the pickup code it gets with the cipher.
 *
 * In an edition with a finish cipher, the finish cipher is always solved by a password typed into
 * Palainfo; solving it is stored as an extra checkpoint number "checkpoint_count" (see docs/game-logic.md).
 */
final class ActionScreen extends BaseControl
{
	/** Code the team has to type to confirm it wants to quit the game. */
	public const EndCode = 'JEZIMADEMDOM';

	/** Confirmation screens that can be opened right after the page loads. */
	public const DeadScreen = 0;
	public const EndScreen = 1;

	/** A typed departure later than now by more than this is taken as a time before midnight. */
	private const FutureDepartureTolerance = '+10 minutes';

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
		$hasFinishCipher = (bool) $endgame?->has_finish_cipher;

		$params = [
			'checkpointCount' => $checkpointCount,
			'nextCheckpointNumber' => $nextCheckpoint,
			'hasPasswordSolution' => $this->isWaitingForPasswordSolution($nextCheckpoint),
			'hasFinishCipher' => $hasFinishCipher,
			'defaultScreen' => $this->defaultScreen,
			'teamEnded' => true,
			'canAskForDead' => false,
			'endCode' => self::EndCode,
			'lastCheckpointData' => null,
			'specification' => null,
			'cipherWait' => null,
		];

		if ($this->teamsModel->hasTeamEnded($teamId, $this->year)) {
			if (self::hasReachedFinish($nextCheckpoint, $checkpointCount, $hasFinishCipher)) {
				$this->flashMessage('Hru jste úspěšně dokončili! Gratulujeme.', 'success');
			} else {
				$this->flashMessage('Již jste ukončili hru a ve hře tak nemůžete pokračovat.');
				$this->flashMessage(sprintf('Přijďte se podívat do cíle: %s (otevřen od %s)', $endgame?->finish_location, $endgame?->finish_open_time), 'info');
			}

			$this->flashAfterparty($endgame);

		} elseif ($this->yearsModel->hasGameEnded($this->year)) {
			$this->flashMessage(sprintf('Hra již skončila, děkujeme za účast. Již nelze zadávat kódy stanovišť ani hesla. Přijďte se za námi podívat do cíle: %s', $endgame?->finish_location), 'info');
			$this->flashAfterparty($endgame);

		} else {
			$this->lastCheckpointData = $last = $this->resultsModel->getLastCheckpointData($teamId, $this->year);
			$params['teamEnded'] = false;
			$params['lastCheckpointData'] = $last;
			$params['canAskForDead'] = $last !== null && $this->canAskForDead((int) $last->checkpoint_number);
			$params['cipherWait'] = $last !== null ? $this->getCipherWait($last) : null;
			// specification of the checkpoint the team is heading to (none for the finish password)
			$params['specification'] = $nextCheckpoint < $checkpointCount
				? $this->ciphersModel->getSpecification($this->year, $nextCheckpoint)
				: null;
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
	 * Handles checkpoint codes, pickup codes of ciphers handed out after a wait and solutions of password-type ciphers.
	 * @param array{code: string} $values
	 */
	private function codeInputSucceeded(Form $form, array $values): void
	{
		$this->assertCanAct('Bohužel jste kód nestihli zadat před koncem hry.');
		$teamId = $this->requireTeamId();
		$nextCheckpoint = $this->resultsModel->getFirstEmptyCheckpoint($teamId, $this->year);
		$last = $this->resultsModel->getLastCheckpointData($teamId, $this->year);

		if ($last !== null && $this->isWaitingForPickup($last)) {
			$this->handlePickupCode($last, $nextCheckpoint, $values['code']);
		} elseif ($this->isWaitingForPasswordSolution($nextCheckpoint)) {
			$this->handlePasswordSolution($nextCheckpoint - 1, $values['code']);
		} else {
			$this->handleCheckpointCode($nextCheckpoint, $values['code']);
		}

		$this->redirect('this');
	}


	/**
	 * The team waited for the cipher (ciphers.wait_minutes) and got it from organizers; entering the code
	 * written on the cipher (pickup code) records the pickup and removes the green screen. The code is accepted
	 * even before the countdown ends (organizers may shorten the wait). A team that forgot the pickup
	 * code can go on with the password solution or the code of the next checkpoint.
	 */
	private function handlePickupCode(Row $last, int $nextCheckpoint, string $code): void
	{
		$teamId = $this->requireTeamId();
		$checkpoint = (int) $last->checkpoint_number;
		$isPasswordCipher = $this->isWaitingForPasswordSolution($nextCheckpoint);

		if ($this->ciphersModel->checkPickupCode($this->year, $checkpoint, $code)) {
			$this->resultsModel->insertResultsRow($teamId, $this->year, $checkpoint, pickupTime: new DateTime);
			$this->flashMessage('Převzetí šifry bylo zapsáno. Hodně štěstí při luštění!', 'success');

		} elseif ($isPasswordCipher && $this->ciphersModel->checkSolution($this->year, $checkpoint, $code)) {
			$this->handlePasswordSolution($checkpoint, $code);

		} elseif (!$isPasswordCipher && $this->ciphersModel->checkCode($this->year, $nextCheckpoint, $code)) {
			$this->handleCheckpointCode($nextCheckpoint, $code);

		} elseif ($this->ciphersModel->checkCode($this->year, $checkpoint, $code)) {
			// typically a second phone with an outdated page
			$this->flashMessage('Tento kód už máte zadaný. Po převzetí šifry zadejte kód, který je na ní napsaný.', 'error');

		} else {
			$this->flashMessage('Nesprávně zadaný kód na šifře.', 'error');
		}
	}


	/**
	 * The team solved a cipher whose solution is a password: the password counts as leaving the
	 * checkpoint and reveals the location of the next one. The finish password ends the game.
	 */
	private function handlePasswordSolution(int $checkpoint, string $solution): void
	{
		$teamId = $this->requireTeamId();
		if (!$this->ciphersModel->checkSolution($this->year, $checkpoint, $solution)) {
			$this->flashMessage('Řešení není správně.', 'error');
			return;
		}

		$now = new DateTime;
		$this->resultsModel->insertResultsRow($teamId, $this->year, $checkpoint, exitTime: $now);
		$checkpointCount = $this->yearsModel->getCheckpointCount($this->year);

		if ($checkpoint === $checkpointCount - 1) {
			// the finish password counts as one more checkpoint, the same way the administration stores it
			$this->resultsModel->insertResultsRow($teamId, $this->year, $checkpointCount, entryTime: $now, exitTime: $now, usedHint: false);
			$this->logModel->log(LogType::EnterCheckpoint, $teamId, $checkpointCount, $this->year);
			$this->teamsModel->teamEnded($teamId, $this->year);
			$this->flashFinished($this->resultsModel->getTeamsArrivedCount($this->year, $checkpointCount));
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
		if (!$this->ciphersModel->checkCode($this->year, $checkpoint, $code)) {
			// typically a second phone with an outdated page
			$alreadyEntered = $checkpoint > 0 && $this->ciphersModel->checkCode($this->year, $checkpoint - 1, $code);
			$this->flashMessage($alreadyEntered ? 'Tento kód už máte zadaný.' : 'Nesprávně zadaný kód', 'error');
			return;
		}

		$this->resultsModel->insertResultsRow($teamId, $this->year, $checkpoint, entryTime: new DateTime, usedHint: false);
		$this->logModel->log(LogType::EnterCheckpoint, $teamId, $checkpoint, $this->year);

		$checkpointCount = $this->yearsModel->getCheckpointCount($this->year);
		$order = $this->resultsModel->getTeamsArrivedCount($this->year, $checkpoint);

		if ($checkpoint === $checkpointCount - 1 && !$this->yearsModel->hasFinishCipher($this->year)) {
			// without a finish cipher, reaching the finish completes the game
			$this->teamsModel->teamEnded($teamId, $this->year);
			$this->flashFinished($order);
		} elseif ($checkpoint === $checkpointCount - 1) {
			$this->flashMessage(sprintf('Dorazili jste do cíle jako %s.', $order), 'success');
		} elseif ($checkpoint === 0) {
			$this->flashMessage(sprintf('Vítejte na startu Palapeli. Kód startovní šifry jste zadali jako %s.', $order), 'success');
		} else {
			$this->flashMessage(sprintf('Dorazili jste na stanoviště %s jako %s.', $checkpoint, $order), 'success');
		}

		$entryMessage = $this->ciphersModel->getEntryMessage($this->year, $checkpoint);
		if ($entryMessage !== null) {
			$this->flashMessage($entryMessage, 'info');
			$this->logModel->log(LogType::MessageFromOrg, $teamId, $checkpoint, $this->year, $entryMessage);
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
		$this->assertCanAct();

		try {
			// an empty value means "now", the same as the "TEĎ" button
			$time = new DateTime($values['exitTime'] ?: 'now');
		} catch (\Exception) {
			$this->flashMessage('Nesprávně zadaný čas odchodu.', 'error');
			$this->redirect('this');
		}

		// only the time is entered, a time in the future means the departure was before midnight
		if ($time > new DateTime(self::FutureDepartureTolerance)) {
			$time = $time->modify('-1 day');
		}

		$this->setExitTime($time);
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
		$this->assertCanAct();
		$this->setExitTime(new DateTime);
	}


	/**
	 * Sets the departure from the last checkpoint the team arrived to.
	 */
	private function setExitTime(DateTime $time): void
	{
		$teamId = $this->requireTeamId();
		$last = $this->resultsModel->getLastCheckpointData($teamId, $this->year);

		if ($last === null) {
			$this->redirect('this');

		} elseif ($this->isWaitingForPasswordSolution((int) $last->checkpoint_number + 1)) {
			// a password cipher is left only by entering the password
			$this->flashMessage('Nejprve zadejte řešení šifry.', 'error');

		} elseif ($this->isWaitingForPickup($last)) {
			// the team has not got the cipher yet
			$this->flashMessage('Odchod ze stanoviště lze zadat až po převzetí šifry – zadejte kód, který je na ní napsaný.', 'error');

		} elseif ($time < $last->entry_datetime) {
			$this->flashMessage('Odchod nemůže být dříve než příchod na stanoviště.', 'error');

		} else {
			$this->resultsModel->insertResultsRow($teamId, $this->year, (int) $last->checkpoint_number, exitTime: $time);
			$this->flashMessage('Odchod ze stanoviště byl nastaven na ' . $time->format('H:i'), 'success');
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
		$this->assertCanAct();
		$teamId = $this->requireTeamId();
		$checkpoint = $this->resultsModel->getLastCheckpointNumber($teamId, $this->year);

		if ($checkpoint === null || !$this->canAskForDead($checkpoint)) {
			$this->flashMessage('Totální nápovědu teď nelze otevřít.', 'error');
			$this->redirect('this');
		}

		if (!$this->ciphersModel->checkCode($this->year, $checkpoint, $values['code'])) {
			$this->flashMessage('Nesprávně zadaný kód', 'error');
			$this->getPresenter()->redirect('PalaInfo:', self::DeadScreen);
		}

		$deadSolution = $this->ciphersModel->getDeadSolution($this->year, $checkpoint);
		$this->resultsModel->insertResultsRow($teamId, $this->year, $checkpoint, exitTime: new DateTime, usedHint: true);
		$cipherName = $checkpoint === 0 ? 'startovní šifry' : 'šifry číslo ' . $checkpoint;
		$this->flashMessage(sprintf('Řešením %s je: %s', $cipherName, $deadSolution), 'info');
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
		$this->assertCanAct();
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
	 * Refuses actions of a team that finished or quit the game and actions after the end of the game.
	 * The forms are not displayed then, but an outdated page (e.g. on another phone) can still submit them.
	 */
	private function assertCanAct(string $gameEndedMessage = 'Hra již skončila, tuto akci už nelze provést.'): void
	{
		if ($this->teamsModel->hasTeamEnded($this->requireTeamId(), $this->year)) {
			$this->flashMessage('Již jste ukončili hru a ve hře tak nemůžete pokračovat.', 'error');
			$this->redirect('this');
		}

		if ($this->yearsModel->hasGameEnded($this->year)) {
			$this->flashMessage($gameEndedMessage, 'error');
			$this->redirect('this');
		}
	}


	/**
	 * Whether the team stands on a checkpoint whose cipher is solved by entering a password
	 * and has not entered it yet. The finish cipher (if the edition has one) is always solved by a password.
	 */
	private function isWaitingForPasswordSolution(int $nextCheckpoint): bool
	{
		if ($nextCheckpoint === 0) {
			return false;
		}

		$checkpoint = $nextCheckpoint - 1;
		$isPasswordCipher = $checkpoint === $this->yearsModel->getCheckpointCount($this->year) - 1
			? $this->yearsModel->hasFinishCipher($this->year)
			: $this->ciphersModel->hasCheckpointPasswordSolution($this->year, $checkpoint);

		return $isPasswordCipher
			&& !$this->resultsModel->hasTeamLeft($this->requireTeamId(), $this->year, $checkpoint);
	}


	/**
	 * Mandatory wait for the cipher of the checkpoint the team stands on (e.g. at a campfire): organizers
	 * hand out the cipher only "wait_minutes" after the team entered the code. Palainfo shows an orange
	 * countdown that turns green with the team name – the team shows it to organizers as a proof.
	 * Null when the checkpoint has no wait, the team entered the pickup code or left (password or total hint).
	 * @return ?array{end: DateTime, remaining: int, countdown: string, message: ?string, teamName: ?string}
	 */
	private function getCipherWait(Row $last): ?array
	{
		$end = $this->getCipherWaitEnd($last);
		if ($end === null) {
			return null;
		}

		$checkpoint = (int) $last->checkpoint_number;
		$remaining = max(0, $end->getTimestamp() - time());
		return [
			'end' => $end,
			'remaining' => $remaining,
			'countdown' => sprintf('%02d:%02d', intdiv($remaining, 60), $remaining % 60),
			'message' => $this->ciphersModel->getWaitMessage($this->year, $checkpoint),
			'teamName' => $this->teamsModel->getTeamName($this->requireTeamId()),
		];
	}


	/**
	 * Whether the team stands on a checkpoint with a wait for the cipher and has not entered its pickup
	 * code yet (whether the countdown has run out or not). The departure cannot be set then.
	 */
	private function isWaitingForPickup(Row $last): bool
	{
		return $this->getCipherWaitEnd($last) !== null;
	}


	private function getCipherWaitEnd(Row $last): ?DateTime
	{
		if ($last->pickup_datetime !== null || $last->exit_datetime !== null) {
			return null;
		}

		$minutes = $this->ciphersModel->getWaitMinutes($this->year, (int) $last->checkpoint_number);
		if ($minutes === null) {
			return null;
		}

		return DateTime::from($last->entry_datetime)->modify(sprintf('+%d minutes', $minutes));
	}


	/**
	 * Total hint is available on regular checkpoints and on the start when the edition allows it
	 * (hint_for_start_exists), never on the finish; once, and not for a password cipher the team
	 * has already solved.
	 */
	private function canAskForDead(int $checkpoint): bool
	{
		$teamId = $this->requireTeamId();
		$checkpointCount = $this->yearsModel->getCheckpointCount($this->year);
		$firstCheckpoint = $this->yearsModel->hasHintForStart($this->year) ? 0 : 1;

		return $checkpoint >= $firstCheckpoint
			&& $checkpoint <= $checkpointCount - 2
			&& !$this->resultsModel->hasTeamOpenedDead($teamId, $this->year, $checkpoint)
			&& !(
				$this->ciphersModel->hasCheckpointPasswordSolution($this->year, $checkpoint)
				&& $this->resultsModel->hasTeamLeft($teamId, $this->year, $checkpoint)
			);
	}


	/**
	 * Whether the team completed the game (instead of quitting it): it got past the finish, and in an
	 * edition with a finish cipher also solved the finish password (stored as checkpoint "checkpoint_count").
	 */
	public static function hasReachedFinish(int $nextCheckpoint, int $checkpointCount, bool $hasFinishCipher): bool
	{
		return $checkpointCount > 0
			&& ($hasFinishCipher ? $nextCheckpoint > $checkpointCount : $nextCheckpoint >= $checkpointCount);
	}


	private function flashFinished(int $order): void
	{
		$this->flashMessage(sprintf('Gratulujeme k dokončení Palapeli! Hru jste dokončili jako %s., výsledky se započítanými totálkami budou vyhlášeny po skončení hry.', $order), 'success');
	}


	private function flashAfterparty(?Row $endgame): void
	{
		if ($endgame?->afterparty_location) {
			$this->flashMessage(sprintf('Rádi vás uvidíme i na afterparty: %s (od %s)', $endgame->afterparty_location, $endgame->afterparty_time), 'info');
		}
	}
}
