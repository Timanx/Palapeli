<?php

namespace App\Presenters;

use Nette;

class TeamsModel extends BasePresenter {

    public static function getTeamsCount(Nette\Database\Context $database, $year = self::CURRENT_YEAR) {

        return $database->query('
            SELECT COUNT(team_id) AS count
            FROM teamsyear
            WHERE year = ?
        ', $year)->fetchField('count');
    }

    public static function getPlayingTeamsIds(Nette\Database\Context $database, $year = self::CURRENT_YEAR) {
        return array_keys($database->query('
            SELECT team_id
            FROM teamsyear
            WHERE year = ?
            ORDER BY registered
            LIMIT ?
        ', $year, self::TEAM_LIMIT)->fetchAssoc('team_id'));
    }

    public static function getFirstStandby(Nette\Database\Context $database) {

        return $database->query('
            SELECT *
            FROM teamsyear
            LEFT JOIN teams ON teams.id = teamsyear.team_id
            WHERE year = ?
            ORDER BY registered
            LIMIT 1 OFFSET ?
        ', self::CURRENT_YEAR, self::TEAM_LIMIT)->fetchAll();
    }
}