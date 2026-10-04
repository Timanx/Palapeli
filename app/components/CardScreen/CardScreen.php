<?php

declare(strict_types=1);

namespace App\Components\CardScreen;

use App\Components\BaseControl;
use App\Components\EndgameMessages;
use App\Models\CiphersModel;
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use App\Utils\AppConstants;
use Nette\Application\UI\Form;


/**
 * Palainfo "KARTA" screen: read-only team card with arrival/departure times, total hints,
 * closing times of checkpoints and their specifications ("upřesnítka").
 */
final class CardScreen extends BaseControl
{
	use EndgameMessages;


	public function __construct(
		private readonly ResultsModel $resultsModel,
		private readonly YearsModel $yearsModel,
		private readonly TeamsModel $teamsModel,
		private readonly CiphersModel $ciphersModel,
	) {
	}


	public function render(): void
	{
		$this->flashEndgameMessages($this->requireTeamId());

		$this->renderTemplate(__DIR__ . '/cardScreen.latte', [
			'checkpointCloseTimes' => $this->ciphersModel->getCheckpointCloseTimes($this->year),
			'specifications' => $this->ciphersModel->getSpecifications($this->year),
			'checkpointCount' => $this->yearsModel->getCheckpointCount($this->year),
			'hasFinishCipher' => $this->yearsModel->hasFinishCipher($this->year),
		]);
	}


	/**
	 * The card is displayed using disabled form inputs, teams cannot change it.
	 */
	protected function createComponentTeamCardForm(): Form
	{
		$results = $this->resultsModel->getTeamResults($this->requireTeamId(), $this->year);
		$checkpointCount = $this->yearsModel->getCheckpointCount($this->year);
		$hasFinishCipher = $this->yearsModel->hasFinishCipher($this->year);
		$last = $checkpointCount - 1;

		$form = new Form;
		for ($i = 0; $i < $checkpointCount; $i++) {
			$entryLabel = match (true) {
				$i === 0 => 'Začátek hry:',
				$i === $last && $hasFinishCipher => 'Příchod do cíle:',
				default => 'Příchod na ' . $i . '. stanoviště:',
			};
			$exitLabel = match (true) {
				$i === 0 => 'Odchod ze startu:',
				$i === $last => 'Vyřešení cílového hesla:',
				default => 'Odchod z ' . $i . '. stanoviště:',
			};

			$checkpoint = $form->addContainer('checkpoint' . $i);
			$checkpoint->addText('entryTime', $entryLabel)
				->setHtmlType('time')
				->setDisabled()
				->setDefaultValue($results[$i]['entry_time'] ?? AppConstants::EmptyTimeValue);
			$checkpoint->addText('exitTime', $exitLabel)
				->setHtmlType('time')
				->setDisabled()
				->setDefaultValue($results[$i]['exit_time'] ?? AppConstants::EmptyTimeValue);

			if ($i !== $last) {
				$checkpoint->addCheckbox('usedHint')
					->setDisabled()
					->setDefaultValue((bool) ($results[$i]['used_hint'] ?? false));
			}
		}

		return $form;
	}
}
