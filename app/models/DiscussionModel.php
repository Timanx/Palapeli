<?php

namespace App\Models;

use App\Presenters\BasePresenter;
use Nette;

class DiscussionModel {

    /** @var Nette\Database\Context */
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function getAll()
    {
        return $this->database->query('
            SELECT d.*, COALESCE(teams.name, d.unlogged_team_name) AS team_name
            FROM discussion d
            LEFT JOIN teams ON teams.id = d.team_id
            ORDER BY created DESC
        '
        )->fetchAll();
    }

    public function getAllByThread($thread)
    {
        return $this->database->query('
            SELECT d.*, COALESCE(teams.name, d.unlogged_team_name) AS team_name
            FROM discussion d
            LEFT JOIN teams ON teams.id = d.team_id
            WHERE thread = ?
            ORDER BY created DESC',
                $thread
        )->fetchAll();
    }

    public function getThreads()
    {
        return array_keys($this->database->query('SELECT MAX(created) AS created, thread
            FROM discussion
            GROUP BY thread
            ORDER BY created DESC')->fetchAssoc('thread')
        );
    }

    public function insertPost($message, $author, $teamId, $unloggedTeamName, $thread)
    {
        $this->database->query('
            INSERT INTO discussion (name, team_id, unlogged_team_name, created, message, thread) VALUES (?, ?, ?, ?, ?, ?)
        ',
            $author,
            $teamId,
            (!isset($teamId) && strlen($unloggedTeamName) > 0 ? $unloggedTeamName : null),
            date('Y-m-d H:i:s', time()),
            $message,
            $thread
        );
    }
}