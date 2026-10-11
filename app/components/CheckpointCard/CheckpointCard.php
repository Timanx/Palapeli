<?php

declare(strict_types=1);

namespace App\Components\CheckpointCard;

use App\Components\BaseControl;
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use App\Utils\AppConstants;
use Nette\Application\UI\Form;


/**
 * Administration: arrival times of all teams to one checkpoint ("karta stanoviště").
 *
 * The checkpoint is taken from the presenter parameters "checkpoint" and "previous".
 */
final class CheckpointCard extends BaseControl
{
	private ?int $checkpoint = null;
	private bool $orderByPrevious = false;


	/**
	 * @param bool $editingEnabled  saving of the card; disabled by default because teams enter
	 *                              arrivals themselves in Palainfo (parameter cardEditing in common.neon)
	 */
	public function __construct(
		private readonly bool $editingEnabled,
		private readonly ResultsModel $resultsModel,
		private readonly YearsModel $yearsModel,
		private readonly TeamsModel $teamsModel,
	) {
	}


	public function setCheckpoint(?int $checkpoint, bool $orderByPrevious): static
	{
		$this->checkpoint = $checkpoint;
		$this->orderByPrevious = $orderByPrevious;
		return $this;
	}


	public function render(): void
	{
		$this->renderTemplate(__DIR__ . '/checkpointCard.latte', [
			'selectedYear' => $this->year,
			'checkpoint' => $this->checkpoint,
			'teamsCount' => $this->teamsModel->getTeamsCount($this->year),
			'checkpointCount' => $this->yearsModel->getCheckpointCount($this->year),
		]);
	}


	protected function createComponentSelectCheckpointForm(): Form
	{
		$checkpointCount = $this->yearsModel->getCheckpointCount($this->year);

		// option value = checkpoint number + 1, 0 is the prompt
		$options = ['Vyberte stanoviště'];
		for ($i = 0; $i <= $checkpointCount; $i++) {
			$options[] = match ($i) {
				$checkpointCount - 1 => 'Příchod do cíle',
				$checkpointCount => 'Vyřešení cílového hesla',
				0 => 'Start',
				default => $i . '. stanoviště',
			};
		}

		$form = new Form;
		$form->addCheckbox('previous', 'Řadit týmy podle příchodu na předchozí stanoviště')
			->setHtmlAttribute('onchange', 'this.form.submit()');
		$form->addSelect('checkpoint', '', $options, 1)
			->setHtmlAttribute('onchange', 'this.form.submit()');
		$form->addHidden('currentCheckpoint', $this->checkpoint);
		$form->onSuccess[] = $this->checkpointSelected(...);
		return $form;
	}


	/**
	 * @param array{previous: bool, checkpoint: ?int, currentCheckpoint: string} $values
	 */
	private function checkpointSelected(Form $form, array $values): void
	{
		$selected = (int) $values['checkpoint'];
		$checkpoint = match (true) {
			$values['previous'] => $values['currentCheckpoint'] === '' ? null : (int) $values['currentCheckpoint'],
			$selected === 0 => 0,
			default => $selected - 1,
		};

		$this->getPresenter()->redirect('Administration:checkpointCard', [
			'checkpoint' => $checkpoint,
			'previous' => $values['previous'],
		]);
	}


	protected function createComponentCheckpointCardForm(): Form
	{
		$form = new Form;
		if ($this->checkpoint === null) {
			return $form;
		}

		$teams = $this->resultsModel->getCheckpointEntryTimes($this->year, $this->checkpoint, $this->orderByPrevious);
		foreach ($teams as $i => $team) {
			$container = $form->addContainer('team' . $i);
			$entryTime = $container->addText('entryTime', $team->name)
				->setHtmlType('time')
				->setDefaultValue($team->entry_time ?? AppConstants::EmptyTimeValue);

			if ($this->checkpoint > 1 && !$team->visited_previous) {
				$entryTime->getLabelPrototype()->addAttributes([
					'class' => 'dead',
					'title' => 'Tým nemá vyplněný příchod na předchozím stanovišti',
				]);
			}

			$container->addHidden('teamId', $team->id);
			$container->addButton('currentTime', 'Teď')
				->setHtmlAttribute('onclick', 'submitCurrentTime(' . $i . ', this.form)');
			$container->addButton('inputtedTime', 'Zadáno')
				->setHtmlAttribute('onclick', 'this.form.submit()');
		}

		$form->addSubmit('send', 'ODESLAT KARTU STANOVIŠTĚ');
		$form->onValidate[] = $this->validateCheckpointCardForm(...);
		$form->onSuccess[] = $this->checkpointCardFormSucceeded(...);
		return $form;
	}


	/**
	 * The arrival must not be later than the departure from this checkpoint, nor earlier than the
	 * departure from the previous one.
	 * @param array<string, array{entryTime: string, teamId: string}> $values
	 */
	private function validateCheckpointCardForm(Form $form, array $values): void
	{
		if ($this->checkpoint === null) {
			return;
		}

		$checkpointCount = $this->yearsModel->getCheckpointCount($this->year);
		foreach ($this->getChangedEntries($values) as $teamId => $entry) {
			$teamName = $this->teamsModel->getTeamName($teamId);
			if ($entry === null) {
				$form->addError($teamName . ': neplatný čas.');
				continue;
			}

			$results = $this->resultsModel->getTeamResults($teamId, $this->year);
			$exit = $results[$this->checkpoint]['exit_datetime'] ?? null;
			$previousExit = $results[$this->checkpoint - 1]['exit_datetime'] ?? null;

			// the finish password row has the departure equal to the arrival, it is overwritten
			if ($exit !== null && $exit < $entry && $this->checkpoint !== $checkpointCount) {
				$form->addError($teamName . ': příchod je později než odchod z tohoto stanoviště.');
			}

			if ($previousExit !== null && $entry < $previousExit) {
				$form->addError($teamName . ': příchod je dříve než odchod z předchozího stanoviště.');
			}
		}
	}


	/**
	 * @param array<string, array{entryTime: string, teamId: string}> $values
	 */
	private function checkpointCardFormSucceeded(Form $form, array $values): void
	{
		if (!$this->editingEnabled || $this->checkpoint === null) {
			$this->flashMessage('Zadávání znemožněno', 'error');
			$this->redirect('this');
		}

		$checkpointCount = $this->yearsModel->getCheckpointCount($this->year);

		foreach ($this->getChangedEntries($values) as $teamId => $entry) {
			assert($entry !== null); // checked in validateCheckpointCardForm()
			$this->resultsModel->insertResultsRow($teamId, $this->year, $this->checkpoint, entryTime: $entry);

			if ($this->checkpoint === $checkpointCount) {
				$this->resultsModel->insertResultsRow($teamId, $this->year, $this->checkpoint, exitTime: $entry);
			}
		}

		$this->flashMessage('Údaje z karty stanoviště byly úspěšně uloženy', 'success');
		$this->redirect('this');
	}


	/**
	 * Arrivals that were filled in or changed (unchanged ones keep the stored value incl. seconds).
	 * @param array<string, array{entryTime: string, teamId: string}> $values
	 * @return array<int, ?\DateTimeImmutable>  team ID => arrival, null = invalid time
	 */
	private function getChangedEntries(array $values): array
	{
		$stored = [];
		foreach ($this->resultsModel->getCheckpointEntryTimes($this->year, (int) $this->checkpoint) as $team) {
			$stored[(int) $team->id] = $team->entry_time;
		}

		$changed = [];
		foreach ($values as $team) {
			$teamId = (int) $team['teamId'];
			$entryTime = $team['entryTime'];
			if ($entryTime !== '' && $entryTime !== AppConstants::EmptyTimeValue && $entryTime !== ($stored[$teamId] ?? null)) {
				$changed[$teamId] = $this->yearsModel->toGameDateTime($this->year, $entryTime);
			}
		}

		return $changed;
	}
}
