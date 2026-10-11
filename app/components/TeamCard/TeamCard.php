<?php

declare(strict_types=1);

namespace App\Components\TeamCard;

use App\Components\BaseControl;
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use App\Utils\AppConstants;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;
use Nette\Database\Row;


/**
 * Administration: passage of one team through the game ("karta týmu").
 * The team is selected by the component parameter "team" (URL parameter teamCard-team).
 */
final class TeamCard extends BaseControl
{
	/** Selected team; persistent so that a form shown again with errors keeps it. */
	#[Persistent]
	public ?int $team = null;


	/**
	 * @param bool $editingEnabled  saving of the card; disabled by default because teams enter their
	 *                              data themselves in Palainfo (parameter cardEditing in common.neon)
	 */
	public function __construct(
		private readonly bool $editingEnabled,
		private readonly ResultsModel $resultsModel,
		private readonly YearsModel $yearsModel,
		private readonly TeamsModel $teamsModel,
	) {
	}


	public function render(): void
	{
		$teamId = $this->getSelectedTeamId();

		$this->renderTemplate(__DIR__ . '/teamCard.latte', [
			'selectedYear' => $this->year,
			'checkpointCount' => $this->yearsModel->getCheckpointCount($this->year),
			'hasHintForStart' => $this->yearsModel->hasHintForStart($this->year),
			'teamId' => $teamId,
			'teamName' => $teamId === null ? null : $this->teamsModel->getTeamName($teamId),
			'isTeamFinalized' => $teamId !== null && $this->teamsModel->isTeamFinalized($teamId, $this->year),
		]);
	}


	protected function createComponentTeamCardForm(): Form
	{
		$teamId = $this->getSelectedTeamId();
		$results = $teamId === null ? [] : $this->resultsModel->getTeamResults($teamId, $this->year);
		$yearData = $this->yearsModel->getYearData($this->year);
		$checkpointCount = (int) $yearData?->checkpoint_count;
		$last = $checkpointCount - 1;

		$form = new Form;
		for ($i = 0; $i < $checkpointCount; $i++) {
			$entryLabel = match ($i) {
				0 => 'Začátek hry:',
				$last => 'Příchod do cíle:',
				default => 'Příchod na ' . $i . '. stanoviště:',
			};
			$exitLabel = match ($i) {
				0 => 'Odchod ze startu:',
				$last => 'Vyřešení cílového hesla:',
				default => 'Odchod z ' . $i . '. stanoviště:',
			};

			// the start time defaults to the official start of the game
			$defaultEntry = $results[$i]['entry_time']
				?? ($i === 0 && $yearData->game_start ? $yearData->game_start->format('H:i') : AppConstants::EmptyTimeValue);

			$checkpoint = $form->addContainer('checkpoint' . $i);
			$checkpoint->addText('entryTime', $entryLabel)
				->setHtmlType('time')
				->setDefaultValue($defaultEntry);
			$checkpoint->addText('exitTime', $exitLabel)
				->setHtmlType('time')
				->setDefaultValue($results[$i]['exit_time'] ?? AppConstants::EmptyTimeValue);

			if ($i !== $last) {
				$checkpoint->addCheckbox('usedHint')
					->setDefaultValue((bool) ($results[$i]['used_hint'] ?? false))
					->setRequired(false);
			}
		}

		$form->addHidden('teamId', $teamId);
		$form->addSubmit('send', 'ODESLAT KARTU TÝMU');
		$form->onValidate[] = $this->validateTeamCardForm(...);
		$form->onSuccess[] = $this->teamCardFormSucceeded(...);
		return $form;
	}


	/**
	 * Arrival to a checkpoint must not be later than the departure from it, nor earlier than the
	 * departure from the previous checkpoint.
	 * @param array<string, mixed> $values
	 */
	private function validateTeamCardForm(Form $form, array $values): void
	{
		$teamId = (int) $values['teamId'];
		$results = $this->resultsModel->getTeamResults($teamId, $this->year);
		$changes = $this->getTimeChanges($values, $results);
		$checkpointCount = $this->yearsModel->getCheckpointCount($this->year);
		$previousExit = null;

		foreach ($changes as $number => $change) {
			if ($change['entry'] === false || $change['exit'] === false) {
				$form->addError(self::checkpointLabel($number, $checkpointCount) . ': neplatný čas.');
				continue;
			}

			// the time after the change, or the stored one
			$entry = $change['entry'] ?? $results[$number]['entry_datetime'] ?? null;
			$exit = $change['exit'] ?? $results[$number]['exit_datetime'] ?? null;
			$entry = $entry === '' ? null : $entry;
			$exit = $exit === '' ? null : $exit;

			if ($entry !== null && $exit !== null && $exit < $entry) {
				$form->addError(self::checkpointLabel($number, $checkpointCount) . ': odchod je dříve než příchod.');
			}

			if ($entry !== null && $previousExit !== null && $entry < $previousExit) {
				$form->addError(self::checkpointLabel($number, $checkpointCount) . ': příchod je dříve než odchod z předchozího stanoviště.');
			}

			$previousExit = $exit;
		}
	}


	/**
	 * @param array<string, mixed> $values
	 */
	private function teamCardFormSucceeded(Form $form, array $values): void
	{
		if (!$this->editingEnabled) {
			$this->flashMessage('Zadávání znemožněno', 'error');
			$this->redirect('this');
		}

		$teamId = (int) $values['teamId'];
		$changes = $this->getTimeChanges($values, $this->resultsModel->getTeamResults($teamId, $this->year));
		$last = $this->yearsModel->getCheckpointCount($this->year) - 1;

		foreach ($changes as $number => $change) {
			$checkpoint = $values['checkpoint' . $number];
			$usedHint = isset($checkpoint['usedHint']) ? (bool) $checkpoint['usedHint'] : null;
			assert($change['entry'] !== false && $change['exit'] !== false); // checked in validateTeamCardForm()

			if ($usedHint || !self::isEmptyTime($checkpoint['exitTime']) || !self::isEmptyTime($checkpoint['entryTime'])) {
				$this->resultsModel->insertResultsRow($teamId, $this->year, $number, $change['entry'], $change['exit'], $usedHint);
			}

			// solving the finish password is stored as an extra checkpoint
			if ($number === $last && $change['exit'] instanceof \DateTimeInterface) {
				$this->resultsModel->insertResultsRow($teamId, $this->year, $number + 1, $change['exit'], $change['exit']);
			}
		}

		$this->flashMessage('Údaje z karty týmu byly úspěšně uloženy', 'success');
		$this->redirect('this', ['team' => $teamId]);
	}


	/**
	 * Arrival and departure times of the card as the values for ResultsModel::insertResultsRow(): null = the time
	 * was not changed (keeps the stored value incl. seconds), '' = cleared, false = invalid time.
	 * @param array<string, mixed> $values
	 * @param array<int, Row> $results
	 * @return array<int, array{entry: \DateTimeImmutable|string|false|null, exit: \DateTimeImmutable|string|false|null}>
	 */
	private function getTimeChanges(array $values, array $results): array
	{
		$changes = [];
		foreach ($values as $name => $checkpoint) {
			if (!str_starts_with($name, 'checkpoint')) {
				continue;
			}

			$number = (int) substr($name, strlen('checkpoint'));
			foreach (['entry' => 'entryTime', 'exit' => 'exitTime'] as $key => $input) {
				$time = self::isEmptyTime($checkpoint[$input]) ? '' : $checkpoint[$input];
				$changes[$number][$key] = match (true) {
					$time === ($results[$number][$key . '_time'] ?? '') => null,
					$time === '' => '',
					default => $this->yearsModel->toGameDateTime($this->year, $time) ?? false,
				};
			}
		}

		ksort($changes);
		return $changes;
	}


	private static function isEmptyTime(?string $time): bool
	{
		return $time === null || $time === '' || $time === AppConstants::EmptyTimeValue;
	}


	private static function checkpointLabel(int $number, int $checkpointCount): string
	{
		return match ($number) {
			0 => 'Start',
			$checkpointCount - 1 => 'Cíl',
			default => $number . '. stanoviště',
		};
	}


	protected function createComponentSelectTeamForm(): Form
	{
		$options = ['Nevyplněné týmy' => [], 'Vyplněné týmy' => []];
		foreach ($this->resultsModel->getTeamsWithFilledStatus($this->year) as $team) {
			$options[$team->team_filled ? 'Vyplněné týmy' : 'Nevyplněné týmy'][$team->id] = $team->name;
		}

		$form = new Form;
		$form->addSelect('teams', null, $options, 1)
			->setPrompt('Vyberte tým')
			->setHtmlAttribute('onchange', 'this.form.submit()');
		$form->onSuccess[] = $this->teamSelected(...);
		return $form;
	}


	/**
	 * @param array{teams: ?int} $values
	 */
	private function teamSelected(Form $form, array $values): void
	{
		$this->redirect('this', ['team' => $values['teams']]);
	}


	protected function createComponentFinalizeTeam(): Form
	{
		$form = new Form;
		$form->addSubmit('finalize', 'FINALIZOVAT TÝM');
		$form->addHidden('team_id', $this->getSelectedTeamId());
		$form->onSuccess[] = $this->teamFinalized(...);
		return $form;
	}


	/**
	 * @param array{team_id: string} $values
	 */
	private function teamFinalized(Form $form, array $values): void
	{
		$teamId = (int) $values['team_id'];
		$this->teamsModel->finalizeTeam($teamId, $this->year);
		$this->redirect('this', ['team' => $teamId]);
	}


	private function getSelectedTeamId(): ?int
	{
		return $this->team;
	}
}
