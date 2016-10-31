<?php
namespace App\Presenters;

use Nette;


class ArchivePresenter extends BasePresenter
{

    public function renderDefault($year, $calendarYear, $link)
    {
        $this->session->getSection('selected')->year = $year;
        $this->session->getSection('selected')->calendarYear = $calendarYear;
        $this->redirect($link);
    }
}