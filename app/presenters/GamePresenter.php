<?php
namespace App\Presenters;

use Nette\Application\UI;
use Nette;


class GamePresenter extends BasePresenter
{

    /** @var Nette\Database\Context */
    private $database;

    private $checkpoint;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function renderDefault()
    {
        parent::render();
        $this->prepareHeading('Seznam týmů');
        $data = $this->database->query('
            SELECT * FROM (
            SELECT teams.id, LTRIM(teams.name) AS name, ty.paid, ty.member1, ty.member2, ty.member3, ty.member4, ty.registered
            FROM teams
            LEFT JOIN teamsyear ty ON teams.id = ty.team_id
            WHERE year = ?
            ORDER BY registered
            LIMIT ?
            ) t
            ORDER BY LTRIM(name) COLLATE utf8_czech_ci',
            $this->selectedYear, (self::TEAM_LIMIT > 0 ? self::TEAM_LIMIT : PHP_INT_MAX)
        )->fetchAll();

        $standby = $this->database->query('
            SELECT teams.id, LTRIM(teams.name) AS name, ty.paid, ty.member1, ty.member2, ty.member3, ty.member4, ty.registered
            FROM teams
            LEFT JOIN teamsyear ty ON teams.id = ty.team_id
            WHERE year = ?
            ORDER BY registered
            LIMIT ? OFFSET ?
            ',
            $this->selectedYear, PHP_INT_MAX, (self::TEAM_LIMIT > 0 ? self::TEAM_LIMIT : PHP_INT_MAX)
        )->fetchAll();

        $this->template->paid = $this->database->query('
            SELECT COUNT(*) AS paid
            FROM teamsyear
            WHERE year = ? AND paid = ?
            ',
            $this->selectedYear, self::PAY_OK
        )->fetchField('paid');

        $this->template->startPayment = $this->database->query('
            SELECT COUNT(*) AS start
            FROM teamsyear
            WHERE year = ? AND paid = ?
            ',
            $this->selectedYear, self::PAY_START
        )->fetchField('start');

        $this->template->data = $data;
        $this->template->standby = $standby;
        $this->template->teamsCount = count($data) + count($standby);
        $this->template->standbyCount = count($standby);
    }

    public function renderCiphers($checkpoint = 0, $year = null)
    {
        if(isset($year)) {
            $this->session->getSection('selected')->year = $year;
            $this->session->getSection('selected')->calendarYear = $year + 2011;
        }
        parent::render();
        $this->prepareHeading('Šifry');
        $this->checkpoint = $checkpoint;
        $this->template->checkpointCount = $this->database->query('
            SELECT checkpoint_count
            FROM years
            WHERE year = ?
        ', $this->selectedYear)->fetchField('checkpoint_count');

        $data = $this->database->query('
            SELECT ciphers.*, CONCAT(cipher_image.path, cipher_image.name) AS cipher_image, CONCAT(solution_image.path, solution_image.name) AS solution_image, CONCAT(pdf_file.path, pdf_file.name) AS pdf_file
            FROM ciphers
            LEFT JOIN files AS cipher_image ON cipher_image.id = ciphers.cipher_image_id
            LEFT JOIN files AS solution_image ON solution_image.id = ciphers.solution_image_id
            LEFT JOIN files AS pdf_file ON pdf_file.id = ciphers.pdf_file_id
            WHERE year = ? AND checkpoint_number = ?
        ', $this->selectedYear, $checkpoint)->fetch();

        $this->template->fastestSolution = $this->database->query('
            SELECT TIME_TO_SEC(TIMEDIFF(results.exit_time, results.entry_time)) / 60 AS time, GROUP_CONCAT(teams.name SEPARATOR \', \') AS name
            FROM results
            LEFT JOIN teams ON results.team_id = teams.id
            WHERE checkpoint_number = ? AND year = ? AND results.exit_time IS NOT NULL AND NOT results.used_hint AND results.entry_time IS NOT NULL AND (results.exit_time - results.entry_time) = (SELECT MIN(exit_time - entry_time) FROM results WHERE checkpoint_number = ? AND year = ? AND NOT used_hint)
            GROUP BY time
        ', $checkpoint, $this->selectedYear, $checkpoint, $this->selectedYear)->fetch();

        $this->template->teamsTotal = $this->getTeamsTotalCount($this->selectedYear);

        $teamsFilled = array_keys($this->database->query('
            SELECT id FROM (

            SELECT teams.id, (CASE WHEN (MAX(results.exit_time) IS NOT NULL AND MAX(results.exit_time) != \'00:00\' || EXISTS (SELECT 1 FROM results r WHERE r.year = teamsyear.year AND r.team_id = teams.id AND r.used_hint IS NOT NULL)) THEN 1 ELSE 0 END) AS team_filled
            FROM teams
              LEFT JOIN teamsyear ON teams.id = teamsyear.team_id
              LEFT JOIN results ON results.year = ? AND teams.id = results.team_id AND results.checkpoint_number = ?
            WHERE teamsyear.year = ?
              GROUP BY teams.id
            ) t
            WHERE team_filled
        ', $this->selectedYear, $checkpoint, $this->selectedYear)->fetchAssoc('id'));

        $this->template->teamsFilled = count($teamsFilled);

        $this->template->teamsArrived = $this->getTeamsArrivedCount($this->selectedYear, $checkpoint);

        $teamsContinued = array_keys($this->database->query('
            SELECT DISTINCT (results.team_id) AS teams_continued
            FROM results
            WHERE checkpoint_number > ? AND year = ? AND results.entry_time IS NOT NULL
        ', $checkpoint, $this->selectedYear)->fetchAssoc('teams_continued'));

        if(count($teamsContinued) > 0) {
            $this->template->usedHints = $this->database->query('
            SELECT SUM(results.used_hint) AS used_hints
            FROM results
            WHERE checkpoint_number = ? AND year = ? AND results.team_id IN (?)
        ', $checkpoint, $this->selectedYear, $teamsContinued)->fetchField('used_hints');
        } else {
            $this->template->usedHints = 0;
        }



        $this->template->teamsContinued = count($teamsContinued);

        $this->template->teamsFilledContinued = count(array_intersect($teamsFilled, $teamsContinued));

        $this->template->teamsEnded = $this->template->teamsArrived - $this->template->teamsContinued;

        $this->template->usedHintsPercentage = ($this->template->teamsFilledContinued > 0 ? ($this->template->usedHints / $this->template->teamsFilledContinued) * 100 : 0);
        $this->template->teamsEndedPercentage = ($this->template->teamsArrived > 0 ? ($this->template->teamsEnded / $this->template->teamsArrived) * 100 : 0);
        $this->template->teamsArrivedPercentage = ($this->template->teamsTotal > 0 ? ($this->template->teamsArrived / $this->template->teamsTotal) * 100 : 100);

        $this->template->missingData = max($this->template->teamsArrived, $this->template->teamsEnded + $this->template->teamsContinued) - $this->template->teamsFilled;





        $this->template->cipherData = $data;

        $this->template->checkpoint = $checkpoint;
    }

    public function renderPhotos()
    {
        parent::render();
        $this->prepareHeading('Fotky');
    }

    public function renderResults()
    {
        parent::render();
        $this->prepareHeading('Výsledky');

        $data = $this->database->query('
            SELECT teams.name, MAX(results.checkpoint_number) AS max_checkpoint, SUM(results.used_hint) AS total_hints, TIME_FORMAT(MAX(results.entry_time), \'%H:%i\') AS finish_time
            FROM results
            LEFT JOIN teams ON teams.id = results.team_id
            WHERE year = ?
            GROUP BY teams.name
            ORDER BY (MAX(results.checkpoint_number) - SUM(results.used_hint)) DESC, MAX(results.checkpoint_number) DESC, MAX(results.entry_time) ASC',
            $this->selectedYear
        )->fetchAll();

        $this->template->resultsPublic = $this->database->query('
            SELECT results_public
            FROM years
            WHERE year = ?
        ', $this->selectedYear)->fetchField();

        $this->template->resultsFinal = $this->database->query('
            SELECT results_final
            FROM years
            WHERE year = ?
        ', $this->selectedYear)->fetchField();

        $this->template->data = $data;

    }

    public function renderReports()
    {
        parent::render();
        $this->prepareHeading('Reportáže');

        $this->template->years = $this->database->query('
            SELECT year, calendar_year, word_numbering
            FROM years
            ORDER BY year DESC
        ')->fetchAll();

        $data = $this->database->query('
            SELECT reports.year, reports.link, reports.name, reports.description, teams.name AS team
            FROM reports
            LEFT JOIN teams ON reports.team_id = teams.id
            ORDER BY reports.year
        ')->fetchAll();

        $reports = [];

        foreach($data as $report) {
            $reports[$report->year][] = $report;
        }

        $this->template->reports = $reports;
    }

    public function renderStats()
    {
        parent::render();
        $this->prepareHeading('Statistiky');

        $teamsTotalCount = $this->getTeamsTotalCount($this->selectedYear);

        $checkpointCount = $this->database->query('
            SELECT checkpoint_count
            FROM years
            WHERE year = ?
        ', $this->selectedYear)->fetchField('checkpoint_count');

        $data = $this->database->query('
            SELECT team_id, used_hint, entry_time, used_hint IS NOT NULL AS filled, checkpoint_number, EXISTS(SELECT 1 FROM results r WHERE r.team_id = results.team_id AND r.year = results.year AND r.checkpoint_number > results.checkpoint_number) AS continued
            FROM results
            WHERE year = ?
        ', $this->selectedYear)->fetchAll();


        $cipherData = [];

        foreach($data as $row) {
            if(!isset($cipherData[$row->checkpoint_number])) {
                $cipherData[$row->checkpoint_number] = ['dead' => 0, 'hint' => 0, 'solved' => 0, 'no-data' => 0];
            }
            if($row->filled) {
                if(!$row->continued) {
                    $cipherData[$row->checkpoint_number]['dead']++;
                } elseif ($row->used_hint) {
                    $cipherData[$row->checkpoint_number]['hint']++;
                } else {
                    $cipherData[$row->checkpoint_number]['solved']++;
                }
            } else {
                $cipherData[$row->checkpoint_number]['no-data']++;
            }
        }

        for($i = 0; $i < count($cipherData) - 1; $i++) {
            $nextSum = array_sum($cipherData[$i + 1]);
            $thisSum = array_sum($cipherData[$i]);
            if($nextSum > $thisSum) {
                $cipherData[$i]['no-data'] += $nextSum - $thisSum;
            }
        }

        $this->template->cipherData = $cipherData;
        $this->template->teamsTotalCount = $teamsTotalCount;
        $this->template->checkpointCount = $checkpointCount;
    }

    protected function createComponentDiscussion() {
        return new \DiscussionControl($this->database, $this->session->getSection('team')->teamId, $this->session->getSection('team')->teamName, \DiscussionControl::CIPHER_THREAD_PREFIX . '_' . $this->selectedYear . '_' . $this->checkpoint);
    }

    private function getTeamsTotalCount($year) {
        return $this->database->query('
            SELECT COUNT(team_id) AS teams_total
            FROM teamsyear
            WHERE year = ?
        ', $year)->fetchField('teams_total');
    }

    private function getTeamsArrivedCount($year, $checkpoint) {
        return $this->database->query('
            SELECT COUNT(DISTINCT results.team_id) AS teams_arrived
            FROM results
            WHERE checkpoint_number >= ? AND year = ? AND results.entry_time IS NOT NULL
        ', $checkpoint, $year)->fetchField('teams_arrived');
    }

    public function renderScorecard()
    {
        parent::render();
        $this->prepareHeading('Podrobné výsledky');

        $this->template->totalCheckpoints = $totalCheckpoints = $this->database->query('
            SELECT checkpoint_count
            FROM years
            WHERE year = ?',
            $this->selectedYear
        )->fetchField();

        $this->template->teams = $this->database->query('
            SELECT teams.id, teams.name, MAX(results.checkpoint_number) AS max_checkpoint, SUM(results.used_hint) AS total_hints, TIME_FORMAT(MAX(results.entry_time), \'%H:%i\') AS finish_time
            FROM results
            LEFT JOIN teams ON teams.id = results.team_id
            WHERE year = ?
            GROUP BY teams.name
            ORDER BY (MAX(results.checkpoint_number) - SUM(results.used_hint)) DESC, MAX(results.checkpoint_number) DESC, MAX(results.entry_time) ASC',
            $this->selectedYear
        )->fetchAssoc('id');

        $this->template->results = $results = $this->database->query('
            SELECT team_id, checkpoint_number, TIME_FORMAT(results.entry_time, \'%H:%i\') AS entry_time, TIME_FORMAT(results.exit_time, \'%H:%i\') AS exit_time, CASE WHEN used_hint = 1 THEN ? WHEN used_hint = 0 THEN ? ELSE ? END AS background_color, results.used_hint
            FROM results
            WHERE year = ?',
            BasePresenter::YELLOW_TINT, 'initial', BasePresenter::BLUE_TINT, $this->selectedYear
        )->fetchAssoc('team_id|checkpoint_number');

        $this->template->resultsPublic = $this->database->query('
            SELECT results_public
            FROM years
            WHERE year = ?
        ', $this->selectedYear)->fetchField();

        $this->template->resultsFinal = $this->database->query('
            SELECT results_final
            FROM years
            WHERE year = ?
        ', $this->selectedYear)->fetchField();
    }
}