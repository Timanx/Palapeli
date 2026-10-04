<?php

declare(strict_types=1);

namespace App\Components\InfoScreen;

use App\Components\BaseControl;
use App\Models\CiphersModel;
use App\Models\LogModel;
use App\Models\LogType;
use App\Models\YearsModel;
use App\Utils\AppConstants;
use Nette\Database\Row;


/**
 * Palainfo "INFO" screen: the team's log (arrivals, hints, messages from organizers), newest first.
 */
final class InfoScreen extends BaseControl
{
	public function __construct(
		private readonly LogModel $logModel,
		private readonly CiphersModel $ciphersModel,
		private readonly YearsModel $yearsModel,
	) {
	}


	public function render(): void
	{
		$messages = array_map(
			$this->createMessage(...),
			$this->logModel->getLogsForTeam($this->requireTeamId(), $this->year),
		);

		$this->renderTemplate(__DIR__ . '/infoScreen.latte', [
			'messages' => $messages,
		]);
	}


	/**
	 * @return object{message: string, type: string}  message is HTML
	 */
	private function createMessage(Row $row): object
	{
		$type = 'info';
		$text = $row->message;

		if ($text === null) {
			$checkpoint = $row->checkpoint_number === null ? null : (int) $row->checkpoint_number;
			$text = match (LogType::tryFrom((int) $row->type_id)) {
				LogType::EndGame => 'Ukončili jste hru.',
				LogType::OpenDead => sprintf(
					'Otevřeli jste totálku na stanovišti&nbsp;%s. Znění: %s',
					$checkpoint,
					$checkpoint === null ? '' : $this->ciphersModel->getDeadSolution($this->year, $checkpoint),
				),
				LogType::EnterCheckpoint => $this->describeArrival($checkpoint),
				LogType::GameStart => 'Hra začala.',
				default => '',
			};

			if ($row->type_id == LogType::EnterCheckpoint->value) {
				$type = 'success';
			}
		}

		return (object) [
			'message' => $row->log_time . ' - ' . $text,
			'type' => $type,
		];
	}


	private function describeArrival(?int $checkpoint): string
	{
		$checkpointCount = $this->yearsModel->getCheckpointCount($this->year);

		if ($checkpoint === 0) {
			$text = 'Zadali jste kód startovní šifry.';
			if ($this->year === AppConstants::IndividualStartYear) {
				$text .= 'První šifra se nachází v Lelekovicích u pumptracku, v dutině křoví asi 10 metrů jižně od dřevěné sochy. Upřesnítka k dalším šifrám naleznete v záložce Karta.';
			}

			return $text;
		}

		return match ($checkpoint) {
			$checkpointCount - 1 => 'Přišli jste do cíle.',
			$checkpointCount => 'Dokončili jste hru! Gratulujeme!',
			default => sprintf('Přišli jste na stanoviště číslo&nbsp;%s.', $checkpoint),
		};
	}
}
