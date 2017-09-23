<?php
use Nette\Application\UI\Control;
use Nette\Application\UI;

class BaseControl extends Control {
    protected $year;
    protected $teamId;

    public function __construct()
    {
        parent::__construct();
    }

    public function setYear($year)
    {
        $this->year = $year;
    }

    public function setTeamId($teamId)
    {
        $this->teamId = $teamId;
    }
}