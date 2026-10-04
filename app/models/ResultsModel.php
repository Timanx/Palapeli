<?php

declare(strict_types=1);

namespace App\Models;

use App\Utils\AppConstants;
use App\Utils\Colors;
use Nette\Database\Row;


/**
 * Passage of teams through checkpoints (table results; one row per team, year and checkpoint).
 *
 * entry_datetime = arrival to the checkpoint (the team entered the checkpoint code),
 * exit_datetime = departure (the team solved the cipher or took the total hint),
 * used_hint = the team took the total hint ("totálka"); NULL means the team did not fill it in.
 */
final class ResultsModel extends BaseModel
{
	/**
	 * Final standings: highest checkpoint minus used hints, then highest checkpoint, then arrival time.
	 * @return array<int, Row>  team ID => row with id, name, max_checkpoint, total_hints, finish_time (+ start_datetime)
	 */
	public function getTeamStandings(int $year): array
	{
		if ($year === AppConstants::IndividualStartYear) {
			// times are relative to the moment each team started
			return $this->database->query("
				SELECT
					teams.id,
					teams.name,
					MAX(results.checkpoint_number) AS max_checkpoint,
					SUM(results.used_hint) AS total_hints,
					TIME_FORMAT(TIMEDIFF(MAX(results.entry_datetime), vts.start_datetime), '%H:%i') AS finish_time,
					DATE_FORMAT(vts.start_datetime, '%e.%c. %H:%i') AS start_datetime
				FROM results
				JOIN teams ON teams.id = results.team_id
				JOIN vw_team_start_datetime vts ON vts.year = results.year AND vts.team_id = results.team_id
				WHERE results.year = ?
				GROUP BY teams.name
				ORDER BY
					(MAX(results.checkpoint_number) - SUM(results.used_hint)) DESC,
					MAX(results.checkpoint_number) DESC,
					TIMEDIFF(MAX(results.entry_datetime), vts.start_datetime) ASC
			", $year)->fetchAssoc('id');
		}

		return $this->database->query("
			SELECT
				teams.id,
				teams.name,
				MAX(results.checkpoint_number) AS max_checkpoint,
				SUM(results.used_hint) AS total_hints,
				TIME_FORMAT(MAX(results.entry_datetime), '%H:%i') AS finish_time
			FROM results
			JOIN teams ON teams.id = results.team_id
			WHERE year = ?
			GROUP BY teams.name
			ORDER BY
				(MAX(results.checkpoint_number) - SUM(results.used_hint)) DESC,
				MAX(results.checkpoint_number) DESC,
				MAX(results.entry_datetime) ASC
		", $year)->fetchAssoc('id');
	}


	/**
	 * All results of the year for the scorecard ("listina").
	 * @return array<int, array<int, Row>>  team ID => checkpoint => row with entry_time, exit_time, background_color, used_hint
	 */
	public function getCompleteResults(int $year): array
	{
		if ($year === AppConstants::IndividualStartYear) {
			return $this->database->query("
				SELECT
					results.team_id,
					checkpoint_number,
					TIME_FORMAT(TIMEDIFF(results.entry_datetime, vts.start_datetime), '%H:%i') AS entry_time,
					TIME_FORMAT(TIMEDIFF(results.exit_datetime, vts.start_datetime), '%H:%i') AS exit_time,
					CASE WHEN used_hint = 1 THEN ? WHEN used_hint = 0 THEN ? ELSE ? END AS background_color,
					results.used_hint
				FROM results
				JOIN vw_team_start_datetime vts ON vts.year = results.year AND vts.team_id = results.team_id
				WHERE results.year = ?
			", Colors::YellowTint, 'initial', Colors::BlueTint, $year)->fetchAssoc('team_id|checkpoint_number');
		}

		return $this->database->query("
			SELECT
				team_id,
				checkpoint_number,
				TIME_FORMAT(results.entry_datetime, '%H:%i') AS entry_time,
				TIME_FORMAT(results.exit_datetime, '%H:%i') AS exit_time,
				CASE WHEN used_hint = 1 THEN ? WHEN used_hint = 0 THEN ? ELSE ? END AS background_color,
				results.used_hint
			FROM results
			WHERE year = ?
		", Colors::YellowTint, 'initial', Colors::BlueTint, $year)->fetchAssoc('team_id|checkpoint_number');
	}


	public function getResultsPublic(int $year): bool
	{
		return (bool) $this->database->query('SELECT results_public FROM years WHERE year = ?', $year)->fetchField();
	}


	public function publishResults(int $year): void
	{
		$this->database->query('UPDATE years SET results_public = 1 WHERE year = ?', $year);
	}


	/**
	 * Total hints count only when the team managed to reach the next checkpoint before the
	 * end of the game. This removes hints (and the departure time) of teams that did not.
	 */
	public function removeTrailingHints(int $year): void
	{
		$this->database->query('
			UPDATE results
			SET used_hint = 0, exit_time = NULL, exit_datetime = NULL
			WHERE
				used_hint = 1
				AND year = ?
				AND NOT EXISTS (
					SELECT 1
					FROM (SELECT * FROM results AS next_checkpoint) tmp
					WHERE
						tmp.team_id = results.team_id
						AND tmp.year = results.year
						AND tmp.checkpoint_number = (results.checkpoint_number + 1)
				)
		', $year);
	}


	/**
	 * Data for the cipher difficulty chart.
	 * @return Row[]  rows with team_id, used_hint, filled, checkpoint_number, continued
	 */
	public function getStatsData(int $year): array
	{
		return $this->database->query('
			SELECT
				team_id,
				used_hint,
				used_hint IS NOT NULL AS filled,
				checkpoint_number,
				EXISTS(
					SELECT 1
					FROM results r
					WHERE
						r.team_id = results.team_id
						AND r.year = results.year
						AND r.checkpoint_number > results.checkpoint_number
				) AS continued
			FROM results
			JOIN years ON years.year = results.year
			WHERE
				results.year = ?
				AND (years.has_finish_cipher OR results.checkpoint_number < years.checkpoint_count - 1)
		', $year)->fetchAll();
	}


	/**
	 * Fastest solution of a cipher without the total hint.
	 * @return ?Row  row with time (minutes) and name (comma separated team names)
	 */
	public function getFastestSolution(int $year, int $checkpoint): ?Row
	{
		return $this->database->query("
			SELECT
				TIME_TO_SEC(TIMEDIFF(results.exit_datetime, results.entry_datetime)) / 60 AS time,
				GROUP_CONCAT(teams.name SEPARATOR ', ') AS name
			FROM results
			LEFT JOIN teams ON results.team_id = teams.id
			WHERE
				results.entry_datetime < results.exit_datetime
				AND checkpoint_number = ?
				AND year = ?
				AND results.exit_datetime IS NOT NULL
				AND NOT results.used_hint
				AND results.entry_datetime IS NOT NULL
				AND (results.exit_datetime - results.entry_datetime) = (
					SELECT MIN(exit_datetime - entry_datetime)
					FROM results
					WHERE
						results.entry_datetime < results.exit_datetime
						AND checkpoint_number = ?
						AND year = ?
						AND NOT used_hint
				)
			GROUP BY time
		", $checkpoint, $year, $checkpoint, $year)->fetch();
	}


	/**
	 * IDs of teams that filled in their passage through the checkpoint.
	 * @return int[]
	 */
	public function getTeamsFilledIds(int $year, int $checkpoint): array
	{
		return array_map('intval', $this->database->query("
			SELECT id
			FROM (
				SELECT
					teams.id,
					(CASE WHEN
						MAX(results.exit_datetime) IS NOT NULL AND MAX(results.exit_datetime) != '00:00'
						OR EXISTS (
							SELECT 1
							FROM results r
							WHERE r.year = teamsyear.year AND r.team_id = teams.id AND r.used_hint IS NOT NULL
						)
						OR NOT EXISTS (
							SELECT 1
							FROM results r2
							WHERE r2.year = teamsyear.year AND r2.team_id = teams.id AND r2.checkpoint_number > results.checkpoint_number
						)
					THEN 1 ELSE 0 END) AS team_filled
				FROM teams
				LEFT JOIN teamsyear ON teams.id = teamsyear.team_id
				LEFT JOIN results ON results.year = ? AND teams.id = results.team_id
				WHERE teamsyear.year = ? AND results.checkpoint_number = ?
				GROUP BY teams.id
			) t
			WHERE team_filled
		", $year, $year, $checkpoint)->fetchPairs(null, 'id'));
	}


	/**
	 * Number of teams that reached the checkpoint (or any later one).
	 */
	public function getTeamsArrivedCount(int $year, int $checkpoint): int
	{
		return (int) $this->database->query('
			SELECT COUNT(DISTINCT results.team_id)
			FROM results
			WHERE checkpoint_number >= ? AND year = ? AND results.entry_datetime IS NOT NULL
		', $checkpoint, $year)->fetchField();
	}


	/**
	 * IDs of teams that reached a checkpoint after the given one.
	 * @return int[]
	 */
	public function getTeamsContinuedIds(int $year, int $checkpoint): array
	{
		return array_map('intval', $this->database->query('
			SELECT DISTINCT results.team_id
			FROM results
			WHERE checkpoint_number > ? AND year = ? AND results.entry_datetime IS NOT NULL
		', $checkpoint, $year)->fetchPairs(null, 'team_id'));
	}


	/**
	 * @param int[] $teamIds
	 */
	public function getUsedHintsCount(int $year, int $checkpoint, array $teamIds): int
	{
		if ($teamIds === []) {
			return 0;
		}

		return (int) $this->database->query('
			SELECT SUM(results.used_hint)
			FROM results
			WHERE checkpoint_number = ? AND year = ? AND results.team_id IN (?)
		', $checkpoint, $year, $teamIds)->fetchField();
	}


	/**
	 * All registered teams with a flag whether they filled in their team card.
	 * @return Row[]  rows with name, id, team_filled
	 */
	public function getTeamsWithFilledStatus(int $year): array
	{
		return $this->database->query('
			SELECT
				name,
				teams.id,
				(
					MAX(results.exit_datetime) IS NOT NULL
					OR EXISTS (SELECT 1 FROM results r WHERE r.year = teamsyear.year AND r.team_id = teams.id AND r.used_hint IS NOT NULL)
				) AS team_filled
			FROM teams
			LEFT JOIN teamsyear ON teams.id = teamsyear.team_id
			LEFT JOIN results ON results.year = ? AND teams.id = results.team_id
			WHERE teamsyear.year = ?
			GROUP BY name, teams.id
			ORDER BY LTRIM(name) COLLATE utf8_czech_ci
		', $year, $year)->fetchAll();
	}


	/**
	 * @return array<int, Row>  checkpoint => row with entry_time, exit_time (H:i), used_hint, checkpoint_number
	 */
	public function getTeamResults(int $teamId, int $year): array
	{
		return $this->database->query("
			SELECT
				TIME_FORMAT(results.entry_datetime, '%H:%i') AS entry_time,
				TIME_FORMAT(results.exit_datetime, '%H:%i') AS exit_time,
				used_hint,
				checkpoint_number
			FROM results
			WHERE team_id = ? AND year = ?
			ORDER BY checkpoint_number
		", $teamId, $year)->fetchAssoc('checkpoint_number');
	}


	public function hasTeamLeft(int $teamId, int $year, int $checkpoint): bool
	{
		return (bool) $this->database->query('
			SELECT results.exit_datetime IS NOT NULL
			FROM results
			WHERE team_id = ? AND year = ? AND checkpoint_number = ?
		', $teamId, $year, $checkpoint)->fetchField();
	}


	/**
	 * Creates the result row when missing and updates the given values.
	 * A null argument means "keep the current value"; an empty string clears a time.
	 */
	public function insertResultsRow(
		int $teamId,
		int $year,
		int $checkpoint,
		\DateTimeInterface|string|null $entryTime = null,
		\DateTimeInterface|string|null $exitTime = null,
		?bool $usedHint = null,
	): void
	{
		$this->database->query('
			INSERT IGNORE INTO results (team_id, year, checkpoint_number)
			VALUES (?, ?, ?)
		', $teamId, $year, $checkpoint);

		$changes = [];
		if ($entryTime !== null) {
			$changes['entry_datetime'] = $entryTime === '' ? null : $entryTime;
		}

		if ($exitTime !== null) {
			$changes['exit_datetime'] = $exitTime === '' ? null : $exitTime;
		}

		if ($usedHint !== null) {
			$changes['used_hint'] = (int) $usedHint;
		}

		if ($changes) {
			$this->database->query(
				'UPDATE results SET ? WHERE team_id = ? AND year = ? AND checkpoint_number = ?',
				$changes,
				$teamId,
				$year,
				$checkpoint,
			);
		}
	}


	/**
	 * Arrivals of all registered teams to the checkpoint.
	 * @param bool $orderByPrevious  order teams that have not arrived yet by their arrival to the previous checkpoint
	 * @param bool $arrivedOnly  only teams that already arrived
	 * @return Row[]  rows with entry_time (H:i), id, name, visited_previous
	 */
	public function getCheckpointEntryTimes(int $year, int $checkpoint, bool $orderByPrevious = false, bool $arrivedOnly = false): array
	{
		$orderBy = $orderByPrevious
			? 'results.entry_datetime, previous_results.entry_datetime IS NOT NULL DESC, previous_results.entry_datetime, LTRIM(name) COLLATE utf8_czech_ci ASC'
			: 'results.entry_datetime, LTRIM(name) COLLATE utf8_czech_ci ASC';

		return $this->database->query("
			SELECT
				TIME_FORMAT(results.entry_datetime, '%H:%i') AS entry_time,
				teams.id,
				teams.name,
				previous_results.entry_datetime IS NOT NULL AS visited_previous
			FROM teamsyear
			LEFT JOIN results
				ON teamsyear.year = results.year AND teamsyear.team_id = results.team_id AND results.checkpoint_number = ?
			LEFT JOIN teams ON teamsyear.team_id = teams.id
			LEFT JOIN results AS previous_results
				ON previous_results.year = ? AND previous_results.checkpoint_number = ? AND previous_results.team_id = teamsyear.team_id
			WHERE teamsyear.year = ? AND ?
			ORDER BY ?
		",
			$checkpoint,
			$year,
			max(0, $checkpoint - 1),
			$year,
			$this->database::literal($arrivedOnly ? 'results.entry_datetime IS NOT NULL' : 'TRUE'),
			$this->database::literal($orderBy),
		)->fetchAll();
	}


	/**
	 * The last checkpoint the team arrived to.
	 * @return ?Row  results row + exit_time_fmt (H:i) and exit_date_fmt (day of week)
	 */
	public function getLastCheckpointData(?int $teamId, int $year): ?Row
	{
		return $this->database->query("
			SELECT *, TIME_FORMAT(results.exit_datetime, '%H:%i') AS exit_time_fmt, DATE_FORMAT(results.exit_datetime, '%w') AS exit_date_fmt
			FROM results
			WHERE team_id = ? AND year = ? AND entry_datetime IS NOT NULL
			ORDER BY checkpoint_number DESC
		", $teamId, $year)->fetch();
	}


	/**
	 * Number of the last checkpoint the team arrived to, null when it did not arrive anywhere yet.
	 */
	public function getLastCheckpointNumber(?int $teamId, int $year): ?int
	{
		$number = $this->database->query('
			SELECT checkpoint_number
			FROM results
			WHERE team_id = ? AND year = ? AND entry_datetime IS NOT NULL
			ORDER BY checkpoint_number DESC
		', $teamId, $year)->fetchField();

		return $number === null ? null : (int) $number;
	}


	/**
	 * Number of the checkpoint the team is heading to (0 = start).
	 */
	public function getFirstEmptyCheckpoint(?int $teamId, int $year): int
	{
		return (int) $this->database->query('
			SELECT COALESCE(MAX(checkpoint_number) + 1, 0)
			FROM results
			WHERE team_id = ? AND year = ? AND entry_datetime IS NOT NULL
		', $teamId, $year)->fetchField();
	}


	public function hasTeamOpenedDead(?int $teamId, int $year, int $checkpoint): bool
	{
		return (bool) $this->database->query('
			SELECT used_hint
			FROM results
			WHERE team_id = ? AND checkpoint_number = ? AND year = ?
		', $teamId, $checkpoint, $year)->fetchField();
	}


	/**
	 * Current position of every team: its last visited checkpoint.
	 * @return Row[]  rows with checkpoint_number, entry_datetime, exit_datetime, name, ended
	 */
	public function whereIsWho(int $year): array
	{
		return $this->database->query('
			SELECT
				results.checkpoint_number,
				results.entry_datetime,
				results.exit_datetime,
				teams.name,
				(ty.end_time IS NOT NULL) AS ended
			FROM results
			JOIN teams ON teams.id = results.team_id
			JOIN teamsyear ty ON ty.team_id = results.team_id AND results.year = ty.year
			WHERE
				results.entry_datetime IS NOT NULL
				AND results.year = ?
				AND NOT EXISTS (
					SELECT 1
					FROM results next
					WHERE
						next.team_id = results.team_id
						AND next.year = results.year
						AND next.checkpoint_number > results.checkpoint_number
						AND next.entry_datetime IS NOT NULL
				)
			ORDER BY
				results.checkpoint_number DESC,
				results.exit_datetime IS NOT NULL DESC,
				results.exit_datetime,
				results.entry_datetime
		', $year)->fetchAll();
	}
}
