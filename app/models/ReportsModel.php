<?php

namespace App\Models;

use App\Presenters\BasePresenter;
use Nette;

class ReportsModel {

    /** @var Nette\Database\Context */
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function getReports()
    {
        return $this->database->query('
            SELECT reports.year, reports.link, reports.name, reports.description, teams.name AS team
            FROM reports
            LEFT JOIN teams ON reports.team_id = teams.id
            ORDER BY reports.year
        ')->fetchAssoc('year[]');
    }
}