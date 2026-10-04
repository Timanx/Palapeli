<?php

declare(strict_types=1);

namespace App\Models;

use Nette\Database\Row;


/**
 * Game editions (table years).
 */
final class YearsModel extends BaseModel
{
	/** Columns that can be edited in the administration (YearForm). */
	public const EditableColumns = [
		'calendar_year', 'date', 'game_start', 'game_end', 'word_numbering', 'registration_start',
		'registration_end', 'checkpoint_count', 'entry_fee', 'entry_fee_account', 'entry_fee_deadline',
		'entry_fee_return_deadline', 'last_info_time', 'team_limit', 'results_public',
		'show_tester_notification', 'is_current', 'has_finish_cipher', 'hint_for_start_exists',
		'afterparty_location', 'afterparty_time', 'finish_location', 'finish_open_time',
	];


	/**
	 * @return array<int, Row>  year => row with year, calendar_year, is_current
	 */
	public function getArchiveSwitchData(): array
	{
		return $this->database->query('
			SELECT year, calendar_year, is_current
			FROM years
			ORDER BY year
		')->fetchAssoc('year');
	}


	public function getCheckpointCount(int $year): int
	{
		return (int) $this->getColumn($year, 'checkpoint_count');
	}


	public function hasFinishCipher(int $year): bool
	{
		return (bool) $this->getColumn($year, 'has_finish_cipher');
	}


	public function getTeamLimit(int $year): ?int
	{
		$limit = $this->getColumn($year, 'team_limit');
		return $limit === null ? null : (int) $limit;
	}


	public function getYearData(int $year): ?Row
	{
		return $this->database->query('SELECT * FROM years WHERE year = ?', $year)->fetch();
	}


	/**
	 * Returns the edition marked as current, or the most recent one when no edition is marked.
	 */
	public function getCurrentYearData(): Row
	{
		$row = $this->database->query('SELECT * FROM years WHERE is_current')->fetch()
			?? $this->database->query('SELECT * FROM years ORDER BY date DESC LIMIT 1')->fetch();

		return $row ?? throw new \RuntimeException('There is no game edition in the table "years".');
	}


	public function getCurrentYearNumber(): int
	{
		return (int) $this->getCurrentYearData()->year;
	}


	public function getCalendarYear(int $year): ?int
	{
		$calendarYear = $this->getColumn($year, 'calendar_year');
		return $calendarYear === null ? null : (int) $calendarYear;
	}


	/**
	 * @return Row[]  rows with year, calendar_year, word_numbering; newest first
	 */
	public function getYearNames(): array
	{
		return $this->database->query('
			SELECT year, calendar_year, word_numbering
			FROM years
			ORDER BY year DESC
		')->fetchAll();
	}


	public function isRegistrationOpen(int $year): bool
	{
		return $this->evaluate($year, 'CURRENT_TIMESTAMP BETWEEN registration_start AND registration_end');
	}


	public function hasRegistrationStarted(int $year): bool
	{
		return $this->evaluate($year, 'CURRENT_TIMESTAMP >= registration_start');
	}


	public function hasGameStarted(int $year): bool
	{
		return $this->evaluate($year, 'CURRENT_TIMESTAMP >= game_start');
	}


	public function hasGameEnded(int $year): bool
	{
		return $this->evaluate($year, 'CURRENT_TIMESTAMP > game_end');
	}


	/**
	 * Registration start formatted for humans, e.g. "1. 12. 2025 v 20:00".
	 */
	public function getRegistrationStart(int $year): ?string
	{
		return $this->database->query("
			SELECT DATE_FORMAT(registration_start, '%e. %c. %Y v %H:%i')
			FROM years
			WHERE year = ?
		", $year)->fetchField();
	}


	/**
	 * Information shown to teams that finished or quit the game.
	 */
	public function getEndgameData(int $year): ?Row
	{
		return $this->database->query("
			SELECT
				afterparty_location,
				COALESCE(TIME_FORMAT(afterparty_time, '%H:%i'), '(dozvíte se v cíli)') AS afterparty_time,
				finish_location,
				COALESCE(TIME_FORMAT(finish_open_time, '%H:%i'), '09:00') AS finish_open_time,
				checkpoint_count,
				has_finish_cipher
			FROM years
			WHERE year = ?
		", $year)->fetch();
	}


	public function isTeamInCurrentYear(int $teamId): bool
	{
		return (bool) $this->database->query('
			SELECT 1
			FROM years
			JOIN teamsyear t ON years.year = t.year
			WHERE years.is_current AND t.team_id = ?
		', $teamId)->fetchField();
	}


	/**
	 * @param array<string, mixed> $values  'year' + all self::EditableColumns
	 */
	public function addYear(array $values): void
	{
		$this->database->transaction(function () use ($values): void {
			if ($values['is_current']) {
				$this->removeCurrentFlag();
			}

			$this->database->query('INSERT INTO years ?', $this->pickColumns($values) + ['year' => $values['year']]);
		});
	}


	/**
	 * @param array<string, mixed> $values  'year' + all self::EditableColumns
	 */
	public function editYear(array $values): void
	{
		$this->database->transaction(function () use ($values): void {
			if ($values['is_current']) {
				$this->removeCurrentFlag();
			}

			$this->database->query('UPDATE years SET ? WHERE year = ?', $this->pickColumns($values), $values['year']);
		});
	}


	private function removeCurrentFlag(): void
	{
		$this->database->query('UPDATE years SET is_current = 0');
	}


	/**
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	private function pickColumns(array $values): array
	{
		$columns = [];
		foreach (self::EditableColumns as $column) {
			$columns[$column] = $values[$column];
		}

		return $columns;
	}


	private function getColumn(int $year, string $column): mixed
	{
		return $this->database->query('SELECT ?name FROM years WHERE year = ?', $column, $year)->fetchField();
	}


	private function evaluate(int $year, string $condition): bool
	{
		return (bool) $this->database->query(
			'SELECT ? FROM years WHERE year = ?',
			$this->database::literal($condition),
			$year,
		)->fetchField();
	}
}
