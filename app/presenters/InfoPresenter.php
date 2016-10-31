<?php
namespace App\Presenters;

use Nette;


class InfoPresenter extends BasePresenter
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
        $this->prepareHeading('Aktuality');
        $data = $this->database->query('
            SELECT *
            FROM updates
            WHERE year = ?
            ORDER BY date DESC
        ', $this->selectedYear)->fetchAll();

        $this->template->updates = $data;

    }

    public function renderRules()
    {
        parent::render();
        $this->prepareHeading('Pravidla');
    }

    public function renderEquipment()
    {
        parent::render();
        $this->prepareHeading('Doporučená výbava');
    }
}