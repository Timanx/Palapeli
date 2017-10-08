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
    /** @var \IInfoScreenFactory $actionScreen */
    private $infoScreen;

    public function __construct(
        YearsModel $yearsModel,
        \IActionScreenFactory $actionScreen,
        \IInfoScreenFactory $infoScreen
    )
    {
        parent::__construct();
        $this->actionScreen = $actionScreen;
        $this->yearsModel = $yearsModel;
        $this->infoScreen = $infoScreen;
    }

    public function renderDefault($defaultScreen = null)
    {
        parent::render();

        /** @var \ActionScreen $component */
        $component = $this->getComponent('actionScreen');
        $component->template->defaultScreen = $defaultScreen;
    }


    public function renderInfo()
    {
        parent::render();

        /** @var \InfoScreen $component */
        $component = $this->getComponent('infoScreen');
    }



    protected function createComponentActionScreen()
    {
        /** @var \ActionScreen $control */
        $control = $this->actionScreen->create();

        $control->setTeamId($this->session->getSection('team')->teamId);
        $control->setYear($this->yearsModel->getCurrentYearNumber());

        return $control;
    }

    protected function createComponentInfoScreen()
    {
        /** @var \InfoScreen $control */
        $control = $this->infoScreen->create();

        $control->setTeamId($this->session->getSection('team')->teamId);
        $control->setYear($this->yearsModel->getCurrentYearNumber());

        return $control;
    }
}
