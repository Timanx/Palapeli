<?php
use Nette\Application\UI\Control;
use Nette\Application\UI;

class BaseControl extends Control {
    protected $year;

    public function __construct()
    {
        parent::__construct();
    }

    public function setYear($year)
    {
        $this->year = $year;
    }
}