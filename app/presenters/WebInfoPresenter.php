<?php

namespace App\Presenters;

use App\Models\YearsModel;
use Nette;

class WebInfoPresenter extends BasePresenter
{
    /** @var YearsModel $yearsModel */
    private $yearsModel;
    /** @var \IActionScreenFactory $actionScreen */
    private $actionScreen;

    public function __construct(
        YearsModel $yearsModel,
        \IActionScreenFactory $actionScreen
    )
    {
        parent::__construct();
        $this->actionScreen = $actionScreen;
        $this->yearsModel = $yearsModel;
    }

    public function renderDefault()
    {
        parent::render();
    }

    protected function createComponentActionScreen()
    {
        /** @var \ActionScreen $control */
        $control = $this->actionScreen->create();

        $control->setTeamId($this->session->getSection('team')->teamId);
        $control->setYear($this->yearsModel->getCurrentYearNumber());

        return $control;
    }
}
