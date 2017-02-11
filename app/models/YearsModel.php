<?php

namespace App\Models;

use App\Presenters\BasePresenter;
use Nette;

class YearsModel {

    /** @var Nette\Database\Context */
    private $database;

    private $year = BasePresenter::CURRENT_YEAR;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function setYear($year)
    {
        $this->year = $year;
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

    public function getYearNames()
    {
        return $this->database->query('
            SELECT year, calendar_year, word_numbering
            FROM years
            ORDER BY year DESC
        ')->fetchAll();
    }
}