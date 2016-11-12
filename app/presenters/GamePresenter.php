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
            SELECT teams.id, LTRIM(teams.name) AS name, ty.paid, ty.member1, ty.member2, ty.member3, ty.member4, ty.registered
            FROM teams
            LEFT JOIN teamsyear ty ON teams.id = ty.team_id
            WHERE year = ?
            ORDER BY LTRIM(name) COLLATE utf8_czech_ci',
            $this->selectedYear
        )->fetchAll();

        $this->template->data = $data;
        $this->template->teamsCount = count($data);
    }

    public function renderCiphers($checkpoint = 0)
    {
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

        $this->template->data = $data;

    }

    public function renderReports()
    {
        parent::render();
        $this->prepareHeading('Reportáže');
    }

    protected function createComponentDiscussion() {
        return new \DiscussionControl($this->database, $this->session->getSection('team')->teamId, $this->session->getSection('team')->teamName, \DiscussionControl::CIPHER_THREAD_PREFIX . '_' . $this->selectedYear . '_' . $this->checkpoint);
    }
}