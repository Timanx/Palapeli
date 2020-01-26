<?php

namespace App\Models;

use App\Presenters\BasePresenter;
use Nette;

class ResultsModel {

    /**
     * @var Nette\Database\Context
     */
    private $database;

    private $year;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function setYear($year)
    {
        $this->year = $year;
    }

    public function getTeamStandings()
    {
        return $this->database->query('
            SELECT teams.id, teams.name, MAX(results.checkpoint_number) AS max_checkpoint, SUM(results.used_hint) AS total_hints, TIME_FORMAT(MAX(results.entry_time), \'%H:%i\') AS finish_time
            FROM results
            LEFT JOIN teams ON teams.id = results.team_id
            WHERE year = ?
            GROUP BY teams.name
            ORDER BY (MAX(results.checkpoint_number) - SUM(results.used_hint)) DESC, MAX(results.checkpoint_number) DESC, MAX(results.entry_time) ASC',
            $this->year
        )->fetchAssoc('id');
    }

    public function getCompleteResults()
    {
        return $this->database->query('
            SELECT team_id, checkpoint_number, TIME_FORMAT(results.entry_time, \'%H:%i\') AS entry_time, TIME_FORMAT(results.exit_time, \'%H:%i\') AS exit_time, CASE WHEN used_hint = 1 THEN ? WHEN used_hint = 0 THEN ? ELSE ? END AS background_color, results.used_hint
            FROM results
            WHERE year = ?',
            BasePresenter::YELLOW_TINT, 'initial', BasePresenter::BLUE_TINT, $this->year
        )->fetchAssoc('team_id|checkpoint_number');
    }

    public function getResultsPublic()
    {
        return $this->database->query('
            SELECT results_public
            FROM years
            WHERE year = ?
        ', $this->year)->fetchField();
    }

    public function publishResults()
    {
        $this->database->query('
            UPDATE years SET results_public = 1
            WHERE year = ?
        ', $this->year);
    }


    public function removeTrailingHints()
    {
        $this->database->query('
            UPDATE results 
            SET used_hint = 0
            WHERE 
              year = ? AND
              NOT EXISTS (
                SELECT 1 
                FROM (
                  SELECT * 
                  FROM results AS next_checkpoint
                  ) tmp
                WHERE 
                  tmp.team_id = results.team_id AND
                  tmp.year = results.year AND 
                  tmp.checkpoint_number = (results.checkpoint_number + 1)
              )
        ', $this->year);

    }

    public function getStatsData()
    {
        return $this->database->query('
            SELECT 
                team_id, 
                used_hint, 
                entry_time, 
                used_hint IS NOT NULL AS filled, 
                checkpoint_number, 
                EXISTS(
                    SELECT 1 
                    FROM results r
                    WHERE 
                        r.team_id = results.team_id AND
                        r.year = results.year AND
                        r.checkpoint_number > results.checkpoint_number
                ) AS continued
            FROM
                results
                JOIN years ON years.year = results.year
            WHERE 
                results.year = ? AND
                (
                    years.has_finish_cipher OR 
                    results.checkpoint_number < years.checkpoint_count - 1
                )
        ', $this->year)->fetchAll();
    }

    public function getFastestSolution($checkpoint)
    {
        return $this->database->query('
            SELECT TIME_TO_SEC(TIMEDIFF(results.exit_time, results.entry_time)) / 60 AS time, GROUP_CONCAT(teams.name SEPARATOR \', \') AS name
            FROM results
            LEFT JOIN teams ON results.team_id = teams.id
            WHERE checkpoint_number = ? AND year = ? AND results.exit_time IS NOT NULL AND NOT results.used_hint AND results.entry_time IS NOT NULL AND (results.exit_time - results.entry_time) = (SELECT MIN(exit_time - entry_time) FROM results WHERE checkpoint_number = ? AND year = ? AND NOT used_hint)
            GROUP BY time
        ', $checkpoint, $this->year, $checkpoint, $this->year)->fetch();
    }

    public function getTeamsFilledCount($checkpoint)
    {
        return $this->database->query('
            SELECT COUNT(id) AS teams_filled FROM (

            SELECT teams.id, (CASE WHEN (MAX(results.exit_time) IS NOT NULL AND MAX(results.exit_time) != \'00:00\' OR EXISTS (SELECT 1 FROM results r WHERE r.year = teamsyear.year AND r.team_id = teams.id AND r.used_hint IS NOT NULL)) THEN 1 ELSE 0 END) AS team_filled
            FROM teams
              LEFT JOIN teamsyear ON teams.id = teamsyear.team_id
              LEFT JOIN results ON results.year = ? AND teams.id = results.team_id AND results.checkpoint_number = ?
            WHERE teamsyear.year = ?
              GROUP BY teams.id
            ) t
            WHERE team_filled
        ', $this->year, $checkpoint, $this->year)->fetchField();
    }

    public function getTeamsFilledIds($checkpoint)
    {
        return array_keys($this->database->query('
            SELECT id
            FROM (

            SELECT teams.id,
              (
                CASE WHEN
                  MAX(results.exit_time) IS NOT NULL AND
                  MAX(results.exit_time) != \'00:00\'

                  OR

                  EXISTS (
                    SELECT 1
                    FROM results r
                    WHERE r.year = teamsyear.year AND r.team_id = teams.id AND r.used_hint IS NOT NULL
                  )


                  OR

                    NOT EXISTS (
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
        ', $this->year, $this->year, $checkpoint)->fetchAssoc('id')

        );
    }

    public function geTeamsArrivedCount($checkpoint)
    {
        return $this->database->query('
            SELECT COUNT(DISTINCT results.team_id) AS teams_arrived
            FROM results
            WHERE checkpoint_number >= ? AND year = ? AND results.entry_time IS NOT NULL
        ', $checkpoint, $this->year)->fetchField();
    }

    public function getTeamsContinuedIds($checkpoint)
    {
        return $this->database->query('
            SELECT DISTINCT (results.team_id) AS teams_continued
            FROM results
            WHERE checkpoint_number > ? AND year = ? AND results.entry_time IS NOT NULL
        ', $checkpoint, $this->year)->fetchAssoc('teams_continued');
    }

    public function getUsedHintsCount($checkpoint, $teamIds)
    {
        if(count($teamIds) == 0) {
            return 0;
        }

        return $this->database->query('
            SELECT SUM(results.used_hint) AS used_hints
            FROM results
            WHERE checkpoint_number = ? AND year = ? AND results.team_id IN (?)
        ', $checkpoint, $this->year, $teamIds)->fetchField('used_hints');
    }

    public function getTeamsWithFilledStatus()
    {
        return $this->database->query('
            SELECT name, teams.id, (MAX(results.exit_time) IS NOT NULL AND MAX(results.exit_time) != \'00:00\' || EXISTS (SELECT 1 FROM results r WHERE r.year = teamsyear.year AND r.team_id = teams.id AND r.used_hint IS NOT NULL)) AS team_filled
            FROM teams
            LEFT JOIN teamsyear ON teams.id = teamsyear.team_id
            LEFT JOIN results ON results.year = ? AND teams.id = results.team_id
            WHERE teamsyear.year = ?
            GROUP BY name, teams.id
            ORDER BY LTRIM(name) COLLATE utf8_czech_ci
        ', $this->year, $this->year)->fetchAll();
    }

    public function getTeamResults($teamId)
    {
        return $this->database->query('
            SELECT TIME_FORMAT(results.entry_time, \'%H:%i\') AS entry_time,TIME_FORMAT(results.exit_time, \'%H:%i\') AS exit_time, used_hint, checkpoint_number
            FROM results
            WHERE team_id = ? AND year = ?
            ORDER BY checkpoint_number
        ', $teamId, $this->year)->fetchAssoc('checkpoint_number');
    }

    public function insertResultsRow($teamId, $checkpointNumber, $entryTime = NULL, $exitTime = NULL, $usedHint = NULL)
    {
        $this->database->query('
                INSERT IGNORE INTO results (team_id, year, checkpoint_number) VALUES
                (?, ?, ?)
            ',
            $teamId,
            $this->year,
            $checkpointNumber
        );

        if($entryTime !== NULL) {
            $this->database->query('
                UPDATE results SET entry_time = ? WHERE team_id = ? AND year = ? AND checkpoint_number = ?
            ',
                (strlen($entryTime) == 0 ? NULL : $entryTime),
                $teamId,
                $this->year,
                $checkpointNumber
            );
        }

        if($exitTime !== NULL) {
            $this->database->query('
                UPDATE results SET exit_time = ? WHERE team_id = ? AND year = ? AND checkpoint_number = ?
            ',
                (strlen($exitTime) == 0 ? NULL : $exitTime),
                $teamId,
                $this->year,
                $checkpointNumber
            );
        }

        if($usedHint !== NULL) {
            $this->database->query('
                UPDATE results SET used_hint = ? WHERE team_id = ? AND year = ? AND checkpoint_number = ?
            ',
                ($usedHint ? 1 : 0),
                $teamId,
                $this->year,
                $checkpointNumber
            );
        }
    }

    public function getCheckpointEntryTimes($checkpointNumber, $orderByPrevious = false, $notNulls = false)
    {
        if($orderByPrevious) {
            return $this->database->query('
            SELECT TIME_FORMAT(results.entry_time, \'%H:%i\') AS entry_time, teams.id, teams.name, previous_results.entry_time IS NOT NULL AS visited_previous
            FROM teamsyear
            LEFT JOIN results ON teamsyear.year = results.year AND teamsyear.team_id = results.team_id AND results.checkpoint_number = ?
            LEFT JOIN teams ON teamsyear.team_id = teams.id
            LEFT JOIN results AS previous_results ON previous_results.year = ? AND previous_results.checkpoint_number = ? AND previous_results.team_id = teamsyear.team_id
            WHERE teamsyear.year = ? AND ?
            ORDER BY results.entry_time, previous_results.entry_time IS NOT NULL DESC, previous_results.entry_time, LTRIM(name) COLLATE utf8_czech_ci ASC
        ', $checkpointNumber, $this->year, ($checkpointNumber == 0 ? 0 : $checkpointNumber - 1), $this->year, $this->database::literal($notNulls ? 'results.entry_time IS NOT NULL' : 'TRUE'))->fetchAll();
        } else {
            return $this->database->query('
            SELECT TIME_FORMAT(results.entry_time, \'%H:%i\') AS entry_time, teams.id, teams.name, previous_results.entry_time IS NOT NULL AS visited_previous
            FROM teamsyear
            LEFT JOIN results ON teamsyear.year = results.year AND teamsyear.team_id = results.team_id AND results.checkpoint_number = ?
            LEFT JOIN teams ON teamsyear.team_id = teams.id
            LEFT JOIN results AS previous_results ON previous_results.year = ? AND previous_results.checkpoint_number = ? AND previous_results.team_id = teamsyear.team_id
            WHERE teamsyear.year = ? AND ?
            ORDER BY results.entry_time, LTRIM(name) COLLATE utf8_czech_ci ASC
        ', $checkpointNumber, $this->year, ($checkpointNumber == 0 ? 0 : $checkpointNumber - 1), $this->year, $this->database::literal($notNulls ? 'results.entry_time IS NOT NULL' : 'TRUE'))->fetchAll();
        }
    }

    public function getLastCheckpointData($teamId)
    {
        return $this->database->query('
            SELECT *, TIME_FORMAT(results.exit_time, \'%H:%i\') AS exit_time_fmt 
            FROM results
            WHERE 
              team_id = ? AND 
              year = ? AND 
              entry_time IS NOT NULL
            ORDER BY checkpoint_number DESC
        ', $teamId, $this->year
        )->fetch();
    }

    public function getLastCheckpointNumber($teamId)
    {
        return $this->database->query('
            SELECT checkpoint_number 
            FROM results
            WHERE 
              team_id = ? AND 
              year = ? AND 
              entry_time IS NOT NULL
            ORDER BY checkpoint_number DESC
        ', $teamId, $this->year
        )->fetchField('checkpoint_number');
    }

    public function getFirstEmptyCheckpoint($teamId)
    {
        return $this->database->query('
           SELECT COALESCE(MAX(checkpoint_number) + 1, 0) AS checkpoint_number
           FROM results
           WHERE team_id = ? AND year = ? AND entry_time IS NOT NULL
        ', $teamId, $this->year
        )->fetchField('checkpoint_number');
    }

    public function hasTeamOpenedDead($teamId, $checkpointNumber)
    {
        return $this->database->query('
            SELECT used_hint
            FROM results
            WHERE team_id = ? AND checkpoint_number = ? AND year = ?       
        ', $teamId, $checkpointNumber, $this->year
        )->fetchField();
    }
}