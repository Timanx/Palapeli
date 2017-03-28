<?php
use Nette\Application\UI\Control;
use Nette\Application\UI;

class BaseControl extends Control
{
    /** @var Nette\Database\Context */
    protected $database;

    /** @var Nette\Http\Session */
    private $session;

    protected $selectedYear;
    protected $selectedCalendarYear;

    public function __construct(Nette\Database\Context $database, \Nette\Http\Session $session)
    {
        parent::__construct();
        $this->session = $session;
        $this->database = $database;
        $this->getYearData();
    }

    public function getYearData() {
        if($this->session->hasSection('selected') && !empty($this->session->getSection('selected')->year)) {
            $this->selectedYear = $this->session->getSection('selected')->year;
        } else {
            $this->session->getSection('selected')->year = \App\Presenters\BasePresenter::CURRENT_YEAR;
            $this->selectedYear = \App\Presenters\BasePresenter::CURRENT_YEAR;
        }
        if($this->session->hasSection('selected') && !empty($this->session->getSection('selected')->calendarYear)) {
            $this->selectedCalendarYear = $this->session->getSection('selected')->calendarYear;
        } else {
            $this->session->getSection('selected')->calendarYear = \App\Presenters\BasePresenter::CURRENT_CALENDAR_YEAR;
            $this->selectedCalendarYear = \App\Presenters\BasePresenter::CURRENT_CALENDAR_YEAR;
        }
    }
}