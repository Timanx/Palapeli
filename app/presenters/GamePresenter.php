<?php
namespace App\Presenters;

use Nette\Application\UI;
use Nette;


class GamePresenter extends BasePresenter
{

    /** @var Nette\Database\Context */
    private $database;

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

    public function renderCiphers()
    {
        parent::render();
        $this->prepareHeading('Šifry');
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
}