<?php

declare(strict_types=1);

namespace App\Models;

use Nette\Database\Row;
use Nette\Utils\DateTime;


/**
 * Teams (table teams) and their registrations to individual years (table teamsyear).
 *
 * When a year has a team limit, only the first "team_limit" registered teams (ordered by
 * registration time) are playing; the rest are substitutes ("náhradníci").
 */
final class TeamsModel extends BaseModel
{
	public function getTeamsCount(int $year): int
	{
		return (int) $this->database->query('
			SELECT COUNT(team_id)
			FROM teamsyear
			WHERE year = ?
		', $year)->fetchField();
	}


	/**
	 * @return int[]
	 */
	public function getPlayingTeamsIds(int $year): array
	{
		return array_map('intval', $this->database->query('
			SELECT team_id
			FROM teamsyear
			WHERE year = ?
			ORDER BY registered
			LIMIT ?
		', $year, $this->getTeamLimitOrMax($year))->fetchPairs(null, 'team_id'));
	}


	/**
	 * First substitute team. Meant to be called right after a playing team cancelled its
	 * registration, therefore the offset is one less than the team limit.
	 */
	public function getFirstStandby(int $year): ?Row
	{
		return $this->database->query('
			SELECT *
			FROM teamsyear
			LEFT JOIN teams ON teams.id = teamsyear.team_id
			WHERE year = ?
			ORDER BY registered
			LIMIT 1 OFFSET ?
		', $year, max(0, $this->getTeamLimitOrMax($year) - 1))->fetch();
	}


	/**
	 * Playing (non-substitute) teams ordered by name.
	 * @return Row[]
	 */
	public function getPlayingTeams(int $year): array
	{
		return $this->database->query('
			SELECT * FROM (
				SELECT teams.id, LTRIM(teams.name) AS name, ty.*, teams.phone1, teams.phone2, teams.email1, teams.email2
				FROM teams
				LEFT JOIN teamsyear ty ON teams.id = ty.team_id
				WHERE year = ?
				ORDER BY registered
				LIMIT ?
			) t
			ORDER BY LTRIM(name) COLLATE utf8_czech_ci
		', $year, $this->getTeamLimitOrMax($year))->fetchAll();
	}


	/**
	 * Playing (non-substitute) teams ordered by registration time.
	 * @return Row[]
	 */
	public function getPlayingTeamsByRegistration(int $year): array
	{
		return $this->database->query('
			SELECT teams.id, LTRIM(teams.name) AS name, ty.*, teams.phone1, teams.phone2, teams.email1, teams.email2
			FROM teams
			LEFT JOIN teamsyear ty ON teams.id = ty.team_id
			WHERE year = ?
			ORDER BY registered
			LIMIT ?
		', $year, $this->getTeamLimitOrMax($year))->fetchAll();
	}


	/**
	 * Substitute teams ordered by registration time.
	 * @return Row[]
	 */
	public function getStandbyTeams(int $year): array
	{
		return $this->database->query('
			SELECT teams.id, LTRIM(teams.name) AS name, ty.*, teams.phone1, teams.phone2, teams.email1, teams.email2
			FROM teams
			LEFT JOIN teamsyear ty ON teams.id = ty.team_id
			WHERE year = ?
			ORDER BY registered
			LIMIT ? OFFSET ?
		', $year, PHP_INT_MAX, $this->getTeamLimitOrMax($year))->fetchAll();
	}


	public function countTeamsWithPaymentStatus(int $year, PaymentStatus $status): int
	{
		return (int) $this->database->query('
			SELECT COUNT(*)
			FROM teamsyear
			WHERE year = ? AND paid = ?
		', $year, $status->value)->fetchField();
	}


	public function getTeamName(int $teamId): ?string
	{
		return $this->database->query('SELECT name FROM teams WHERE id = ?', $teamId)->fetchField();
	}


	public function isTeamRegistered(int $teamId, int $year): bool
	{
		return (bool) $this->database->query('
			SELECT 1
			FROM teamsyear
			WHERE team_id = ? AND year = ?
		', $teamId, $year)->fetchField();
	}


	public function getTeamPaymentStatus(int $teamId, int $year): ?PaymentStatus
	{
		$paid = $this->database->query('
			SELECT paid
			FROM teamsyear
			WHERE team_id = ? AND year = ?
		', $teamId, $year)->fetchField();

		return $paid === null ? null : PaymentStatus::tryFrom((int) $paid);
	}


	/**
	 * Number of teams registered before the given team.
	 */
	public function getTeamRegistrationOrder(int $teamId, int $year): int
	{
		return (int) $this->database->query('
			SELECT COUNT(team_id)
			FROM teamsyear
			WHERE year = ? AND registered < (
				SELECT registered
				FROM teamsyear
				WHERE year = ? AND team_id = ?
			)
		', $year, $year, $teamId)->fetchField();
	}


	/**
	 * Registration of the team in the most recent year it played (used to prefill members).
	 */
	public function getMostRecentTeamYearData(int $teamId): ?Row
	{
		return $this->database->query('
			SELECT *
			FROM teamsyear
			WHERE team_id = ?
			ORDER BY year DESC
			LIMIT 1
		', $teamId)->fetch();
	}


	/**
	 * Team contact data joined with its registration in the given year (if any).
	 */
	public function getTeamData(int $teamId, int $year): ?Row
	{
		return $this->database->query('
			SELECT *
			FROM teams t
			LEFT JOIN teamsyear ty ON t.id = ty.team_id AND ty.year = ?
			WHERE t.id = ?
		', $year, $teamId)->fetch();
	}


	public function registerTeam(
		int $teamId,
		int $year,
		?string $member1 = '',
		?string $member2 = '',
		?string $member3 = '',
		?string $member4 = '',
	): void
	{
		$this->database->query('INSERT INTO teamsyear ?', [
			'team_id' => $teamId,
			'year' => $year,
			'paid' => PaymentStatus::Unpaid->value,
			'member1' => $member1,
			'member2' => $member2,
			'member3' => $member3,
			'member4' => $member4,
			'registered' => new DateTime,
		]);
	}


	/**
	 * @return int  ID of the new team
	 */
	public function addNewTeam(string $name, string $passwordHash, string $phone1, string $phone2, string $email1, string $email2): int
	{
		$this->database->query('INSERT INTO teams ?', [
			'name' => $name,
			'password' => $passwordHash,
			'phone1' => $phone1,
			'phone2' => $phone2,
			'email1' => $email1,
			'email2' => $email2,
		]);

		return (int) $this->database->getInsertId();
	}


	public function getTeamId(string $teamName): ?int
	{
		$id = $this->database->query('SELECT id FROM teams WHERE name = ?', $teamName)->fetchField();
		return $id === null ? null : (int) $id;
	}


	public function getPasswordHash(int $teamId): ?string
	{
		return $this->database->query('SELECT password FROM teams WHERE id = ?', $teamId)->fetchField();
	}


	public function updatePassword(int $teamId, string $passwordHash): void
	{
		$this->database->query('UPDATE teams SET password = ? WHERE id = ?', $passwordHash, $teamId);
	}


	public function updateTeamMembers(int $teamId, int $year, string $member1, string $member2, string $member3, string $member4): void
	{
		$this->database->query('
			UPDATE teamsyear
			SET member1 = ?, member2 = ?, member3 = ?, member4 = ?
			WHERE team_id = ? AND year = ?
		', $member1, $member2, $member3, $member4, $teamId, $year);
	}


	public function updateTeamContactInfo(int $teamId, string $email1, string $email2, string $phone1, string $phone2): void
	{
		$this->database->query('
			UPDATE teams
			SET email1 = ?, email2 = ?, phone1 = ?, phone2 = ?
			WHERE id = ?
		', $email1, $email2, $phone1, $phone2, $teamId);
	}


	public function deleteTeamRegistration(int $teamId, int $year): void
	{
		$this->database->query('DELETE FROM teamsyear WHERE year = ? AND team_id = ?', $year, $teamId);
	}


	public function getEmailsByName(string $teamName): ?Row
	{
		return $this->database->query('
			SELECT email1, email2, id
			FROM teams
			WHERE name = ?
		', $teamName)->fetch();
	}


	public function isNameTaken(string $teamName): bool
	{
		// teams.name uses a binary collation, so the comparison is case sensitive
		return (bool) $this->database->query('SELECT 1 FROM teams WHERE name = ?', $teamName)->fetchField();
	}


	/**
	 * E-mail addresses of playing teams that did not pay the entry fee yet.
	 * @return Row[]  rows with "email" (one or two addresses separated by comma) and "paid"
	 */
	public function getUnpaidTeamsData(int $year): array
	{
		return $this->database->query("
			SELECT * FROM (
				SELECT
					CASE WHEN teams.email2 IS NULL OR teams.email2 = '' THEN email1 ELSE CONCAT(email1, ', ', email2) END AS email,
					paid
				FROM teams
				LEFT JOIN teamsyear ON teamsyear.team_id = teams.id
				WHERE year = ?
				ORDER BY registered
				LIMIT ?
			) t
			WHERE paid = ?
		", $year, $this->getTeamLimitOrMax($year), PaymentStatus::Unpaid->value)->fetchAll();
	}


	/**
	 * E-mail addresses of all playing teams.
	 * @return Row[]  rows with "email" (one or two addresses separated by comma) and "paid"
	 */
	public function getPlayingTeamsEmails(int $year): array
	{
		return $this->database->query("
			SELECT
				CASE WHEN teams.email2 IS NULL OR teams.email2 = '' THEN email1 ELSE CONCAT(email1, ', ', email2) END AS email,
				paid
			FROM teams
			LEFT JOIN teamsyear ON teamsyear.team_id = teams.id
			WHERE year = ?
			ORDER BY registered
			LIMIT ?
		", $year, $this->getTeamLimitOrMax($year))->fetchAll();
	}


	/**
	 * All registered teams (including substitutes) with their payment status, ordered by name.
	 * @return Row[]  rows with id, name, paid
	 */
	public function getTeamsPaymentStatus(int $year): array
	{
		return $this->database->query('
			SELECT id, name, paid
			FROM teams
			LEFT JOIN teamsyear ON teams.id = teamsyear.team_id
			WHERE teamsyear.year = ?
			ORDER BY LTRIM(name) COLLATE utf8_czech_ci
		', $year)->fetchAll();
	}


	public function editTeamPayment(int $teamId, int $year, PaymentStatus $status): void
	{
		$this->database->query('
			UPDATE teamsyear SET paid = ?
			WHERE team_id = ? AND year = ?
		', $status->value, $teamId, $year);
	}


	/**
	 * Marks that the team finished or quit the game.
	 */
	public function teamEnded(int $teamId, int $year): void
	{
		$this->database->query('
			UPDATE teamsyear SET end_time = ?
			WHERE team_id = ? AND year = ?
		', new DateTime, $teamId, $year);
	}


	public function hasTeamEnded(int $teamId, int $year): bool
	{
		return $this->database->query('
			SELECT end_time
			FROM teamsyear
			WHERE team_id = ? AND year = ?
		', $teamId, $year)->fetchField() !== null;
	}


	public function isTeamFinalized(int $teamId, int $year): bool
	{
		return (bool) $this->database->query('
			SELECT finalized
			FROM teamsyear
			WHERE team_id = ? AND year = ?
		', $teamId, $year)->fetchField();
	}


	public function finalizeTeam(int $teamId, int $year): void
	{
		$this->database->query('
			UPDATE teamsyear
			SET finalized = 1
			WHERE team_id = ? AND year = ?
		', $teamId, $year);
	}


	private function getTeamLimitOrMax(int $year): int
	{
		$limit = $this->database->query('SELECT team_limit FROM years WHERE year = ?', $year)->fetchField();
		return $limit === null ? PHP_INT_MAX : (int) $limit;
	}
}
