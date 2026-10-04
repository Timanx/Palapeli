<?php

declare(strict_types=1);

namespace App\Models;

use Nette\Database\Row;


/**
 * News ("aktuality") shown in the Info section (table updates).
 */
final class UpdatesModel extends BaseModel
{
	/**
	 * @return Row[]  newest first
	 */
	public function getUpdates(int $year): array
	{
		return $this->database->query('
			SELECT *
			FROM updates
			WHERE year = ?
			ORDER BY date DESC
		', $year)->fetchAll();
	}


	/**
	 * Stores the message as HTML; new lines are converted to <br>.
	 */
	public function addUpdate(int $year, string $date, string $message): void
	{
		$this->database->query('INSERT INTO updates ?', [
			'date' => $date,
			'year' => $year,
			'message' => nl2br($message),
		]);
	}
}
