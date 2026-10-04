<?php

declare(strict_types=1);

namespace App\Components\TeamCard;

use App\Components\BaseControl;
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use App\Utils\AppConstants;
use Nette\Application\UI\Form;


/**
 * Administration: passage of one team through the game ("karta týmu").
 * The team is selected by the component parameter "team" (URL parameter teamCard-team).
 */
final class TeamCard extends BaseControl
{
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
		$form->onSuccess[] = $this->teamCardFormSucceeded(...);
		return $form;
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
		unset($values['teamId']);
		$isEmpty = static fn(?string $time): bool => $time === null || $time === '' || $time === AppConstants::EmptyTimeValue;

		foreach ($values as $name => $checkpoint) {
			$number = (int) substr($name, strlen('checkpoint'));
			$usedHint = isset($checkpoint['usedHint']) ? (bool) $checkpoint['usedHint'] : null;

			if ($usedHint || !$isEmpty($checkpoint['exitTime']) || !$isEmpty($checkpoint['entryTime'])) {
				$this->resultsModel->insertResultsRow(
					$teamId,
					$this->year,
					$number,
					$isEmpty($checkpoint['entryTime']) ? '' : $checkpoint['entryTime'],
					$isEmpty($checkpoint['exitTime']) ? '' : $checkpoint['exitTime'],
					$usedHint,
				);
			}

			// solving the finish password is stored as an extra checkpoint
			if ($number === count($values) - 1 && !$isEmpty($checkpoint['exitTime'])) {
				$this->resultsModel->insertResultsRow($teamId, $this->year, $number + 1, $checkpoint['exitTime'], $checkpoint['exitTime']);
			}
		}

		$this->flashMessage('Údaje z karty týmu byly úspěšně uloženy', 'success');
		$this->redirect('this', ['team' => $teamId]);
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
		$team = $this->getParameter('team');
		return $team === null || $team === '' ? null : (int) $team;
	}
}
