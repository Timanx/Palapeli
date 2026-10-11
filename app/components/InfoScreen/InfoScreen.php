<?php

declare(strict_types=1);

namespace App\Components\InfoScreen;

use App\Components\BaseControl;
use App\Models\CiphersModel;
use App\Models\LogModel;
use App\Models\LogType;
use App\Models\YearsModel;
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
					'Otevřeli jste totálku %s. Znění: %s',
					$checkpoint === 0 ? 'na startovní šifru' : 'na stanovišti&nbsp;' . $checkpoint,
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

		// text revealed with the code (ciphers.entry_message) is logged as a separate message
		return match ($checkpoint) {
			0 => 'Zadali jste kód startovní šifry.',
			$checkpointCount - 1 => $this->yearsModel->hasFinishCipher($this->year)
				? 'Přišli jste do cíle.'
				: 'Přišli jste do cíle a dokončili hru! Gratulujeme!',
			$checkpointCount => 'Vyřešili jste cílové heslo a dokončili hru! Gratulujeme!',
			default => sprintf('Přišli jste na stanoviště číslo&nbsp;%s.', $checkpoint),
		};
	}
}
