<?php

namespace App\Models;

use App\Presenters\BasePresenter;
use Nette;

class UpdatesModel {

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

    public function getUpdates()
    {
        return $this->database->query('
            SELECT *
            FROM updates
            WHERE year = ?
            ORDER BY date DESC
        ', $this->year)->fetchAll();
    }

    public function addUpdate($date, $message)
    {
        $this->database->query('
            INSERT INTO updates (date, year, message)
              VALUES (?, ?, ?)

        ', $date, $this->year, nl2br($message));
    }
}