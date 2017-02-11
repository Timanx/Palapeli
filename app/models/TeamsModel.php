<?php

namespace App\Models;

use App\Presenters\BasePresenter;
use Nette;

class TeamsModel {

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

    public function getTeamsCount()
    {
        return $this->database->query('
            SELECT COUNT(team_id) AS count
            FROM teamsyear
            WHERE year = ?
        ', $this->year)->fetchField('count');
    }

    public function getPlayingTeamsIds()
    {
        return array_keys($this->database->query('
            SELECT team_id
            FROM teamsyear
            WHERE year = ?
            ORDER BY registered
            LIMIT ?
        ', $this->year, BasePresenter::TEAM_LIMIT)->fetchAssoc('team_id'));
    }

    public function getFirstStandby()
    {
        return $this->database->query('
            SELECT *
            FROM teamsyear
            LEFT JOIN teams ON teams.id = teamsyear.team_id
            WHERE year = ?
            ORDER BY registered
            LIMIT 1 OFFSET ?
        ', BasePresenter::CURRENT_YEAR, BasePresenter::TEAM_LIMIT - 1)->fetchAll();
    }

    public function getPlayingTeams()
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
            ORDER BY LTRIM(name) COLLATE utf8_czech_ci',
            $this->year, (BasePresenter::TEAM_LIMIT > 0 ? BasePresenter::TEAM_LIMIT : PHP_INT_MAX)
        )->fetchAll();
    }

    public function getStandbyTeams()
    {
        return $this->database->query('
            SELECT teams.id, LTRIM(teams.name) AS name, ty.*, teams.phone1, teams.phone2, teams.email1, teams.email2
            FROM teams
            LEFT JOIN teamsyear ty ON teams.id = ty.team_id
            WHERE year = ?
            ORDER BY registered
            LIMIT ? OFFSET ?
            ',
            $this->year, PHP_INT_MAX, (BasePresenter::TEAM_LIMIT > 0 ? BasePresenter::TEAM_LIMIT : PHP_INT_MAX)
        )->fetchAll();
    }

    public function getPaidTeamsCount()
    {
        return $this->database->query('
            SELECT COUNT(*) AS paid
            FROM teamsyear
            WHERE year = ? AND paid = ?
            ',
            $this->year, BasePresenter::PAY_OK
        )->fetchField('paid');
    }

    public function getStartPaymentTeamsCount()
    {
        return $this->database->query('
            SELECT COUNT(*) AS start
            FROM teamsyear
            WHERE year = ? AND paid = ?
            ',
            $this->year, BasePresenter::PAY_START
        )->fetchField('start');
    }

    public function getTeamName(Integer $teamId)
    {
        return $this->database->query('
            SELECT name
            FROM teams
            WHERE id = ?
        ', $teamId)->fetchField();
    }

}