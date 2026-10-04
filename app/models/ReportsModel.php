<?php

declare(strict_types=1);

namespace App\Models;

use Nette\Database\Row;


/**
 * Links to reports ("reportáže") written by teams about the game (table reports).
 */
final class ReportsModel extends BaseModel
{
	/**
	 * @return array<int, list<Row>>  year => reports
	 */
	public function getReports(): array
	{
		return $this->database->query('
			SELECT reports.year, reports.link, reports.name, reports.description, teams.name AS team
			FROM reports
			LEFT JOIN teams ON reports.team_id = teams.id
			ORDER BY reports.year
		')->fetchAssoc('year[]');
	}


	public function insertReport(int $year, string $link, int $teamId, ?string $name, ?string $description): void
	{
		$this->database->query('INSERT INTO reports ?', [
			'year' => $year,
			'link' => $link,
			'team_id' => $teamId,
			'name' => $name,
			'description' => $description,
		]);
	}
}
