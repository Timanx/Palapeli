<?php

declare(strict_types=1);

namespace App\Models;

use Nette\Database\Row;


/**
 * Palainfo event log (table log). Entries with team_id/year NULL are broadcast to all teams.
 */
final class LogModel extends BaseModel
{
	public function log(
		LogType|int $type,
		?int $teamId = null,
		?int $checkpoint = null,
		?int $year = null,
		?string $message = null,
	): void
	{
		$this->database->query('INSERT INTO log ?', [
			'team_id' => $teamId,
			'year' => $year,
			'checkpoint_number' => $checkpoint,
			'message' => $message,
			'type_id' => $type instanceof LogType ? $type->value : $type,
		]);
	}


	/**
	 * Entries for the team plus broadcast entries, newest first.
	 * @return Row[]  rows with checkpoint_number, log_time (H:i), message, type_id
	 */
	public function getLogsForTeam(?int $teamId, int $year): array
	{
		return $this->database->query("
			SELECT checkpoint_number, TIME_FORMAT(time, '%H:%i') AS log_time, message, type_id
			FROM log
			WHERE (team_id = ? OR team_id IS NULL) AND (year = ? OR year IS NULL)
			ORDER BY time DESC
		", $teamId, $year)->fetchAll();
	}


	/**
	 * @return array<int, string>  id => name
	 */
	public function getLogTypes(): array
	{
		return $this->database->query('SELECT id, name FROM log_type')->fetchPairs('id', 'name');
	}
}
