<?php

namespace App\Presenters;

use App\Models\YearsModel;
use Nette;

class PalaInfoPresenter extends BasePresenter
{
    /** @var YearsModel $yearsModel */
    private $yearsModel;
    /** @var \IActionScreenFactory $actionScreen */
    private $actionScreen;
    /** @var \IInfoScreenFactory $infoScreen */
    private $infoScreen;
    /** @var \ICheckpointScreenFactory $checkpointScreen */
    private $checkpointScreen;
    /** @var  \ICardScreenFactory $cardScreen */
    private $cardScreen;

    public function __construct(
        YearsModel $yearsModel,
        \IActionScreenFactory $actionScreen,
        \IInfoScreenFactory $infoScreen,
        \ICheckpointScreenFactory $checkpointScreen,
        \ICardScreenFactory $cardScreen
    )
    {
        parent::__construct();
        $this->actionScreen = $actionScreen;
        $this->yearsModel = $yearsModel;
        $this->infoScreen = $infoScreen;
        $this->checkpointScreen = $checkpointScreen;
        $this->cardScreen = $cardScreen;
    }

    private function prepareTemplateParams()
    {
        $this->template->hasGameStarted = $this->yearsModel->hasGameStarted();
        $this->template->hasGameEnded = $this->yearsModel->hasGameEnded();
    }

    public function renderDefault($defaultScreen = null)
    {
        parent::render();

        /** @var \ActionScreen $component */
        $component = $this->getComponent('actionScreen');
        $component->template->defaultScreen = $defaultScreen;
        $this->yearsModel->setYear($this->selectedYear);
        $this->prepareTemplateParams();
    }


    public function renderInfo()
    {
        parent::render();

        /** @var \InfoScreen $component */
        $component = $this->getComponent('infoScreen');
        $this->yearsModel->setYear($this->selectedYear);
        $this->prepareTemplateParams();
    }

    public function renderCheckpoint()
    {
        parent::render();
        $this->yearsModel->setYear($this->selectedYear);
        $this->prepareTemplateParams();
    }

    public function renderCard()
    {
        parent::render();
        $this->yearsModel->setYear($this->selectedYear);
        $this->prepareTemplateParams();
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

    protected function createComponentCardScreen()
    {
        /** @var \CardScreen $control */
        $control = $this->cardScreen->create();

        $control->setTeamId($this->session->getSection('team')->teamId);
        $control->setYear($this->yearsModel->getCurrentYearNumber());

        return $control;
    }

    protected function createComponentCheckpointScreen()
    {
        /** @var \CheckpointScreen $control */
        $control = $this->checkpointScreen->create();

        $control->setTeamId($this->session->getSection('team')->teamId);
        $control->setYear($this->yearsModel->getCurrentYearNumber());

        return $control;
    }

}
