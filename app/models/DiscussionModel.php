<?php

declare(strict_types=1);

namespace App\Models;

use Nette\Database\Row;
use Nette\Utils\DateTime;


/**
 * Discussion posts (table discussion). Posts are grouped into threads, see DiscussionControl.
 */
final class DiscussionModel extends BaseModel
{
	/**
	 * @return Row[]  newest first
	 */
	public function getAll(): array
	{
		return $this->database->query('
			SELECT d.*, COALESCE(teams.name, d.unlogged_team_name) AS team_name
			FROM discussion d
			LEFT JOIN teams ON teams.id = d.team_id
			ORDER BY created DESC
		')->fetchAll();
	}


	/**
	 * @return Row[]  newest first
	 */
	public function getAllByThread(string $thread): array
	{
		return $this->database->query('
			SELECT d.*, COALESCE(teams.name, d.unlogged_team_name) AS team_name
			FROM discussion d
			LEFT JOIN teams ON teams.id = d.team_id
			WHERE thread = ?
			ORDER BY created DESC
		', $thread)->fetchAll();
	}


	/**
	 * @return list<string>  names of all threads, the most recently active first
	 */
	public function getThreads(): array
	{
		return array_values($this->database->query('
			SELECT thread, MAX(created) AS created
			FROM discussion
			GROUP BY thread
			ORDER BY created DESC
		')->fetchPairs(null, 'thread'));
	}


	/**
	 * @param ?int $teamId  logged-in team, null for anonymous visitors
	 * @param string $unloggedTeamName  team name typed by an anonymous visitor (stored only for anonymous posts)
	 */
	public function insertPost(string $message, string $author, ?int $teamId, string $unloggedTeamName, string $thread): void
	{
		$this->database->query('INSERT INTO discussion ?', [
			'name' => $author,
			'team_id' => $teamId,
			'unlogged_team_name' => $teamId === null && $unloggedTeamName !== '' ? $unloggedTeamName : null,
			'created' => new DateTime,
			'message' => $message,
			'thread' => $thread,
		]);
	}
}
