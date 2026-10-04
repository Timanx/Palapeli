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
		$form->onSuccess[] = $this->checkpointCardFormSucceeded(...);
		return $form;
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

		foreach ($values as $team) {
			$entryTime = $team['entryTime'];
			if ($entryTime === '' || $entryTime === AppConstants::EmptyTimeValue) {
				continue;
			}

			$this->resultsModel->insertResultsRow((int) $team['teamId'], $this->year, $this->checkpoint, entryTime: $entryTime);

			if ($this->checkpoint === $checkpointCount) {
				$this->resultsModel->insertResultsRow((int) $team['teamId'], $this->year, $this->checkpoint, exitTime: $entryTime);
			}
		}

		$this->flashMessage('Údaje z karty stanoviště byly úspěšně uloženy', 'success');
		$this->redirect('this');
	}
}
