<?php

namespace App\Models;

use App\Presenters\BasePresenter;
use Nette;

class LogModel
{
    const LT_ENTER_CHECKPOINT = 1;
    const LT_OPEN_DEAD = 2;
    const LT_END_GAME = 3;
    const LT_MESSAGE_TO_ORG = 4;
    const LT_MESSAGE_FROM_ORG = 5;
    const LT_CLOSE_CHECKPOINT = 6;
    const LT_GAME_START = 7;
    const LT_GAME_FINISH = 8;


    /** @var Nette\Database\Context */
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

    public function log($typeId, $teamId = null, $checkpointNumber = null, $year = null, $message = null)
    {
        if ($year === null && $this->year !== null) {
            $year = $this->year;
        }

        $this->database->query('
                INSERT INTO log (team_id, year, checkpoint_number, message, type_id)
                VALUES (?, ?, ?, ?, ?)
                ', $teamId, $year, $checkpointNumber, $message, $typeId
        );
    }

    public function getLogsForTeam($teamId, $year = null)
    {
        if ($year === null && $this->year !== null) {
            $year = $this->year;
        }

        return $this->database->query('
            SELECT checkpoint_number, TIME_FORMAT(time, \'%H:%i\') AS log_time,  message, type_id
            FROM log
            WHERE (team_id = ? OR team_id IS NULL) AND (year = ? OR year IS NULL)
            ORDER BY time DESC
        ', $teamId, $year)->fetchAll();
    }

    public function getOrgRelevantLogs()
    {
        //TODO
    }
}