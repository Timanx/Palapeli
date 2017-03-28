<?php

namespace App\Models;

use App\Presenters\BasePresenter;
use Nette;

class TeamsModel
{

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
        ', $this->year
        )->fetchField('count');
    }

    public function getPlayingTeamsIds()
    {
        return array_keys($this->database->query('
            SELECT team_id
            FROM teamsyear
            WHERE year = ?
            ORDER BY registered
            LIMIT ?
        ', $this->year, BasePresenter::TEAM_LIMIT
        )->fetchAssoc('team_id'));
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
        ', BasePresenter::CURRENT_YEAR, BasePresenter::TEAM_LIMIT - 1
        )->fetch();
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

    public function getTeamName($teamId)
    {
        return $this->database->query('
            SELECT name
            FROM teams
            WHERE id = ?
        ', $teamId
        )->fetchField();
    }

    public function isTeamRegistered($teamId) : bool
    {
        $result = $this->database->query('
            SELECT 1
            FROM teamsyear ty
            WHERE team_id = ? AND year = ?
        ', $teamId, $this->year
        )->fetchField();

        if ($result == 1) {
            return true;
        } else {
            return false;
        }
    }

    public function getTeamPaymentStatus($teamId)
    {
        return $this->database->query('
            SELECT paid
            FROM teamsyear ty
            WHERE team_id = ? AND year = ?
        ', $teamId, $this->year
        )->fetchField();
    }

    public function getTeamRegistrationOrder($teamId)
    {
        return $this->database->query('
            SELECT COUNT(team_id)
            FROM teamsyear
            WHERE year = ? AND
                  registered < (
                    SELECT registered
                    FROM teamsyear
                    WHERE year = ? AND team_id = ?
                  )
        ', $this->year, $this->year, $teamId
        )->fetchField();
    }

    public function getMostRecentTeamYearData($teamId)
    {
        return $this->database->query('
            SELECT *
            FROM teamsyear
            WHERE team_id = ?
            ORDER BY year DESC
            LIMIT 1
        ', $teamId
        )->fetch();
    }

    public function getTeamData($teamId)
    {
        return $this->database->query('
            SELECT *
            FROM teams t
            LEFT JOIN teamsyear ty ON t.id = ty.team_id AND ty.year = ?
            WHERE t.id = ?
        ', $this->year, $teamId
        )->fetch();
    }

    public function registerTeam($teamId, $member1 = NULL, $member2 = NULL, $member3 = NULL, $member4 = NULL)
    {
        $this->database->query('
            INSERT
              INTO teamsyear (team_id, year, paid, member1, member2, member3, member4, registered)
              VALUES (?,?,?,?,?,?,?,?)
        ', $teamId, $this->year, BasePresenter::PAY_NOK, $member1, $member2, $member3, $member4, date('Y-m-d H:i:s', time())
        );
    }

    public function addNewTeam($name, $password, $phone1, $phone2, $email, $email2)
    {
        $this->database->query('
            INSERT
              INTO teams (name, password, phone1, phone2, email1, email2)
              VALUES (?,?,?,?,?,?)
        ', $name, $password, $phone1, $phone2, $email, $email2
        );

        return $this->database->getInsertId();
    }

    public function getTeamId($teamName)
    {
        return $this->database->query('
            SELECT id
            FROM teams
            WHERE name = ?
        ', $teamName)->fetchField();
    }

    public function checkPassword($teamId, $password) : bool
    {
        $passwordOK = $this->database->query('
                SELECT 1
                FROM teams
                WHERE password = ? AND id = ?',
            $password, $teamId
        )->fetchField();

        if($passwordOK == 1) {
            return true;
        } else {
            return false;
        }
    }

    public function updatePassword($teamId, $password)
    {
        $this->database->query('
            UPDATE teams SET password = ? WHERE id = ?
        ', $password, $teamId);
    }

    public function updateTeamMembers($teamId, $m1, $m2, $m3, $m4)
    {
        $this->database->query('
            UPDATE teamsyear SET member1 = ?, member2 = ?, member3 = ?, member4 = ? WHERE team_id = ? AND year  =?
        ', $m1, $m2, $m3, $m4, $teamId, $this->year);
    }

    public function updateTeamContactInfo($teamId, $email1, $email2, $phone1, $phone2)
    {
        $this->database->query('
            UPDATE teams SET email1 = ?, email2 = ?, phone1 = ?, phone2 = ? WHERE id = ?
        ', $email1, $email2, $phone1, $phone2, $teamId);
    }

    public function deleteTeamRegistration($teamId)
    {
        $this->database->query('
            DELETE
            FROM teamsyear
            WHERE year = ? AND team_id = ?
        ', $this->year, $teamId);
    }

    public function getEmailsByName($teamName)
    {
        return $this->database->query('
            SELECT email1, email2, id
            FROM teams
            WHERE name = ?
        ', $teamName
        )->fetch();
    }

    public function getTakenNames()
    {
        return $this->database->query('
            SELECT name
            FROM teams'
        )->fetchAssoc('[]=name');
    }

    public function getUnpaidTeamsData()
    {
        return $this->database->query('
          SELECT * FROM (
            SELECT CASE WHEN teams.email2 IS NULL OR teams.email2 = \'\' THEN email1 ELSE CONCAT(email1, \', \', email2) END AS email, paid
            FROM teams
            LEFT JOIN teamsyear ON teamsyear.team_id = teams.id
            WHERE year = ?
            ORDER BY registered
            LIMIT ?
          ) t
          WHERE paid = ?
            ', $this->year, (BasePresenter::TEAM_LIMIT > 0 ? BasePresenter::TEAM_LIMIT : PHP_INT_MAX), BasePresenter::PAY_NOK)->fetchAll();
    }

    public function getUnfilledTeamsData()
    {
        return $this->database->query('
            SELECT * FROM (
                SELECT CASE WHEN teams.email2 IS NULL OR teams.email2 = \'\' THEN email1 ELSE CONCAT(email1, \', \', email2) END AS email, (MAX(results.exit_time) IS NOT NULL AND MAX(results.exit_time) != \'00:00\' || EXISTS (SELECT 1 FROM results r WHERE r.year = teamsyear.year AND r.team_id = teams.id AND r.used_hint IS NOT NULL)) AS team_filled
                FROM teams
                LEFT JOIN teamsyear ON teams.id = teamsyear.team_id
                LEFT JOIN results ON results.year = ? AND teams.id = results.team_id
                WHERE teamsyear.year = ?
                GROUP BY email
            ) t
            WHERE team_filled = 0
        ', $this->year, $this->year)->fetchAll();
    }

    public function getPlayingTeamsEmails()
    {
        return $this->database->query('
                SELECT CASE WHEN teams.email2 IS NULL OR teams.email2 = \'\' THEN email1 ELSE CONCAT(email1, \', \', email2) END AS email, paid
                FROM teams
                LEFT JOIN teamsyear ON teamsyear.team_id = teams.id
                WHERE year = ?
                ORDER BY registered
                LIMIT ?
            ', $this->year, (BasePresenter::TEAM_LIMIT > 0 ? BasePresenter::TEAM_LIMIT : PHP_INT_MAX))->fetchAll();
    }

    public function getTeamsPaymentStatus()
    {
        return $this->database->query('
            SELECT id, name, paid
            FROM teams
            LEFT JOIN teamsyear ON teams.id = teamsyear.team_id
            WHERE teamsyear.year = ?
            ORDER BY LTRIM(name) COLLATE utf8_czech_ci
        ', $this->year)->fetchAll();
    }

    public function editTeamPayment($teamId, $paymentStatus)
    {
        $this->database->query('
                UPDATE teamsyear SET paid = ?
                WHERE team_id = ? AND year = ?
            ', $paymentStatus, $teamId, $this->year);
    }

}