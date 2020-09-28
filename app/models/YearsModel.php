<?php

namespace App\Models;

use App\Presenters\BasePresenter;
use Nette;

class YearsModel
{

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

    public function getArchiveSwitchData()
    {
        return $this->database->query(
            '
                SELECT
                    year,
                    calendar_year,
                    is_current
                FROM years
                ORDER BY year
            '
        )->fetchAssoc('year');

    }

    public function getCheckpointCount()
    {
        return $this->database->query('
            SELECT checkpoint_count
            FROM years
            WHERE year = ?',
            $this->year
        )->fetchField();
    }

    public function hasFinishCipher()
    {
        return $this->database->query('
            SELECT has_finish_cipher
            FROM years
            WHERE year = ?',
            $this->year
        )->fetchField();
    }

    public function hintForStartExists()
    {
        return $this->database->query('
            SELECT hint_for_start_exists
            FROM years
            WHERE year = ?',
            $this->year
        )->fetchField();
    }

    public function getYearData()
    {
        return $this->database->query('
            SELECT *
            FROM years
            WHERE year = ?',
            $this->year
        )->fetch();
    }

    public function getCurrentYearData()
    {
        $year = $this->database->query('
            SELECT *
            FROM years
            WHERE is_current'
        )->fetch();

        if ($year === false) {
            $year = $this->database->query('
            SELECT *
            FROM years
            ORDER BY date DESC
            LIMIT 1'
            )->fetch();
        }

        return $year;
    }

    public function getYearNames()
    {
        return $this->database->query('
            SELECT year, calendar_year, word_numbering
            FROM years
            ORDER BY year DESC
        ')->fetchAll();
    }

    public function isRegistrationOpen(): bool
    {
        return (bool)$this->database->query('
            SELECT (CURRENT_TIMESTAMP BETWEEN registration_start AND registration_end)
            FROM years
            WHERE year = ?

        ', $this->year)->fetchField();
    }

    public function hasRegistrationStarted(): bool
    {
        return (bool)$this->database->query('
            SELECT CURRENT_TIMESTAMP >= registration_start
            FROM years
            WHERE year = ?

        ', $this->year)->fetchField();
    }

    public function hasRegistrationEnded(): bool
    {
        return (bool)$this->database->query('
            SELECT CURRENT_TIMESTAMP > registration_end
            FROM years
            WHERE year = ?

        ', $this->year)->fetchField();
    }

    public function getRegistrationStart()
    {
        return $this->database->query('
            SELECT DATE_FORMAT(registration_start, \'%e. %c. %Y v %H:%i\')
            FROM years
            WHERE year = ?
        ', $this->year)->fetchField();
    }

    public function getRegistrationEnd()
    {
        return $this->database->query('
            SELECT registration_end
            FROM years
            WHERE year = ?
        ', $this->year)->fetchField();
    }

    public function hasGameStarted(): bool
    {
        return (bool)$this->database->query('
            SELECT CURRENT_TIMESTAMP >= game_start
            FROM years
            WHERE year = ?

        ', $this->year)->fetchField();
    }

    public function hasGameEnded(): bool
    {
        return (bool)$this->database->query('
            SELECT CURRENT_TIMESTAMP > game_end
            FROM years
            WHERE year = ?

        ', $this->year)->fetchField();
    }

    public function getTesterNotificationDisplay()
    {
        return $this->database->query('
            SELECT show_tester_notification
            FROM years
            WHERE year = ?
        ', $this->year)->fetchField();
    }

    public function getTeamLimit()
    {
        return $this->database->query('
            SELECT team_limit
            FROM years
            WHERE year = ?
        ', $this->year)->fetchField();
    }

    public function getCurrentYearNumber()
    {
        return $this->database->query('
            SELECT year
            FROM years
            WHERE is_current
        ')->fetchField();
    }

    public function getEndgameData()
    {
        return $this->database->query('
            SELECT afterparty_location, COALESCE(TIME_FORMAT(afterparty_time, \'%H:%i\'), \'(dozvíte se v cíli)\') AS afterparty_time, finish_location, COALESCE(TIME_FORMAT(finish_open_time, \'%H:%i\'), \'09:00\') AS finish_open_time, checkpoint_count, has_finish_cipher
            FROM years
            WHERE year = ?
        ', $this->year
        )->fetch();
    }

    public function isTeamInCurrentYear($teamId)
    {
        return $this->database->query('
            SELECT 1
            FROM 
              years
              JOIN teamsyear t on years.year = t.year
            WHERE
              years.is_current AND
              t.team_id = ?
            ',
            $teamId
        )->fetch();
    }

    public function addYear(array $values): void
    {
        if ($values['is_current']) {
            $this->removeCurrentFlag();
        }

        $this->database->query('
            INSERT INTO years(year, calendar_year, date, game_start, game_end, word_numbering, registration_start, registration_end, checkpoint_count, entry_fee, entry_fee_account, entry_fee_deadline, entry_fee_return_deadline, last_info_time, team_limit, results_public, show_tester_notification, is_current, has_finish_cipher, hint_for_start_exists, afterparty_location, afterparty_time, finish_location, finish_open_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
            ',
            $values['year'],
            $values['calendar_year'],
            $values['date'],
            $values['game_start'],
            $values['game_end'],
            $values['word_numbering'],
            $values['registration_start'],
            $values['registration_end'],
            $values['checkpoint_count'],
            $values['entry_fee'],
            $values['entry_fee_account'],
            $values['entry_fee_deadline'],
            $values['entry_fee_return_deadline'],
            $values['last_info_time'],
            $values['team_limit'],
            $values['results_public'],
            $values['show_tester_notification'],
            $values['is_current'],
            $values['has_finish_cipher'],
            $values['hint_for_start_exists'],
            $values['afterparty_location'],
            $values['afterparty_time'],
            $values['finish_location'],
            $values['finish_open_time']
        );
    }

    public function editYear(array $values): void
    {
        if ($values['is_current']) {
            $this->removeCurrentFlag();
        }

        $this->database->query('
            UPDATE years
            SET calendar_year = ?, date = ?, game_start = ?, game_end = ?, word_numbering = ?, registration_start = ?, registration_end = ?, checkpoint_count = ?, entry_fee = ?, entry_fee_account = ?, entry_fee_deadline = ?, entry_fee_return_deadline = ?, last_info_time = ?, team_limit = ?, results_public = ?, show_tester_notification = ?, is_current = ?, has_finish_cipher = ?, hint_for_start_exists = ?, afterparty_location = ?, afterparty_time = ?, finish_location = ?, finish_open_time = ?
            ',
            $values['calendar_year'],
            $values['date'],
            $values['game_start'],
            $values['game_end'],
            $values['word_numbering'],
            $values['registration_start'],
            $values['registration_end'],
            $values['checkpoint_count'],
            $values['entry_fee'],
            $values['entry_fee_account'],
            $values['entry_fee_deadline'],
            $values['entry_fee_return_deadline'],
            $values['last_info_time'],
            $values['team_limit'],
            $values['results_public'],
            $values['show_tester_notification'],
            $values['is_current'],
            $values['has_finish_cipher'],
            $values['hint_for_start_exists'],
            $values['afterparty_location'],
            $values['afterparty_time'],
            $values['finish_location'],
            $values['finish_open_time']
        );
    }

    private function removeCurrentFlag()
    {
        $this->database->query('UPDATE years SET is_current = 0');
    }
}
