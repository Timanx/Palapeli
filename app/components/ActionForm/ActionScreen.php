<?php
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use Nette\Application\UI;

class ActionScreen extends BaseControl
{
    /** @var ResultsModel */
    private $resultsModel;
    /** @var YearsModel */
    private $yearsModel;
    /** @var TeamsModel */
    private $teamsModel;
    /** @var \App\Models\CiphersModel */
    private $ciphersModel;
    /** @var Nette\Http\Session */
    private $session;

    private $lastCheckpointData;

    public function __construct(
        ResultsModel $resultsModel,
        YearsModel $yearsModel,
        TeamsModel $teamsModel,
        \App\Models\CiphersModel $ciphersModel,
        \Nette\Http\Session $session
    )
    {
        parent::__construct();
        $this->resultsModel = $resultsModel;
        $this->yearsModel = $yearsModel;
        $this->teamsModel = $teamsModel;
        $this->ciphersModel = $ciphersModel;
        $this->session = $session;
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . '/ActionScreen.latte');

        $teamId = $this->session->getSection('team')->teamId ?? NULL;

        $this->teamsModel->setYear($this->year);
        if ($this->teamsModel->hasTeamEnded($teamId)) {
            $this->template->teamEnded = true;

            $this->yearsModel->setYear($this->year);

            $data = $this->yearsModel->getEndgameData();
            $this->flashMessage('Již jste ukončili hru a ve hře tak nemůžete pokračovat.');
            $this->flashMessage(sprintf('Pozice cíle: %s (otevřen od %s)', $data->finish_location, $data->finish_open_time), 'info');
            if ($data->afterparty_location !== null) {
                $this->flashMessage(sprintf('Místo konání afterparty: %s (od %s)', $data->afterparty_location, $data->afterparty_time), 'info');
            }
        } else {

            $this->template->teamEnded = false;
            $this->resultsModel->setYear($this->year);

            $this->template->nextCheckpointNumber = $this->resultsModel->getFirstEmptyCheckpoint($teamId);

            $this->lastCheckpointData = $this->resultsModel->getLastCheckpointData($teamId);

            $this->template->lastCheckpointData = $this->lastCheckpointData;
        }

        $this->template->render();
    }

    public function createComponentCodeInput()
    {

        $form = new UI\Form;
        $form->addText('code');
        $form->addSubmit('send', '');
        $form->onSuccess[] = [$this, 'codeInputSucceeded'];
        return $form;
    }

    public function codeInputSucceeded(UI\Form $form)
    {
        $this->ciphersModel->setYear($this->year);
        $this->resultsModel->setYear($this->year);

        $teamId = $this->session->getSection('team')->teamId ?? NULL;
        $checkpointNumber = $this->resultsModel->getFirstEmptyCheckpoint($teamId);


        $codeCorrect = $this->ciphersModel->checkCode($form->values['code'], $checkpointNumber);

        if ($codeCorrect) {
            $this->flashMessage('Údaje z karty týmu byly úspěšně uloženy', 'success');
            $now = new \Nette\Utils\DateTime();
            $this->resultsModel->insertResultsRow($teamId, $checkpointNumber, $now);
        } else {
            $this->flashMessage('Nesprávně zadané heslo', 'error');
        }


        $this->redirect('this');
    }


    public function createComponentExitTimeInput($name, $time = null)
    {
        $form = new UI\Form;
        $form->addText('exitTime', '')->setType('time')->setDefaultValue((!empty($this->lastCheckpointData->exit_time_fmt) ? $this->lastCheckpointData->exit_time_fmt : \App\Presenters\BasePresenter::EMPTY_TIME_VALUE));
        $form->addSubmit('send', '');
        $form->onSuccess[] = [$this, 'exitTimeInputSucceeded'];
        return $form;
    }

    public function exitTimeInputSucceeded(UI\Form $form)
    {
        $this->ciphersModel->setYear($this->year);
        $this->resultsModel->setYear($this->year);

        $teamId = $this->session->getSection('team')->teamId ?? NULL;
        $checkpointNumber = $this->resultsModel->getLastCheckpointData($teamId)->checkpoint_number;


        $this->resultsModel->insertResultsRow($teamId, $checkpointNumber, null, $form->values['exitTime']);
        $this->flashMessage('Odchod ze stanoviště byl nastaven na ' . $form->values['exitTime'], 'success');


        $this->redirect('this');
    }

    public function createComponentExitTimeNow($name, $time = null)
    {
        $form = new UI\Form;
        $form->addSubmit('send', 'TEĎ')->setAttribute('class', 'now');
        $form->onSuccess[] = [$this, 'exitTimeNowSucceeded'];
        return $form;
    }

    public function exitTimeNowSucceeded(UI\Form $form)
    {
        $this->ciphersModel->setYear($this->year);
        $this->resultsModel->setYear($this->year);

        $now = new \Nette\Utils\DateTime();

        $teamId = $this->session->getSection('team')->teamId ?? NULL;
        $checkpointNumber = $this->resultsModel->getLastCheckpointData($teamId)->checkpoint_number;


        $this->resultsModel->insertResultsRow($teamId, $checkpointNumber, null, $now);
        $this->flashMessage('Odchod ze stanoviště byl nastaven na ' . $now->format('H:i'), 'success');


        $this->redirect('this');
    }

       public function createComponentAskForDead()
    {

        $form = new UI\Form;
        $form->addText('code');
        $form->addSubmit('send', '');
        $form->onSuccess[] = [$this, 'askForDeadSucceeded'];
        return $form;
    }

    public function createComponentAskForEnd()
    {

        $form = new UI\Form;
        $form->addText('code');
        $form->addSubmit('send', '');
        $form->onSuccess[] = [$this, 'askForEndSucceeded'];
        return $form;
    }

    public function askForDeadSucceeded(UI\Form $form)
    {
        $this->ciphersModel->setYear($this->year);
        $this->resultsModel->setYear($this->year);

        $teamId = $this->session->getSection('team')->teamId ?? NULL;


        $checkpointNumber = $this->resultsModel->getLastCheckpointNumber($teamId);

        $codeCorrect = $this->ciphersModel->checkCode($form->values['code'], $checkpointNumber);

        if ($codeCorrect) {
            $deadSolution = $this->ciphersModel->getDeadSolution($checkpointNumber);
            $now = new \Nette\Utils\DateTime();
            $this->resultsModel->insertResultsRow($teamId, $checkpointNumber, null, $now, true);
            $this->flashMessage(sprintf('Řešením šifry číslo %s je: %s', $checkpointNumber, $deadSolution), 'info');
        } else {
            $this->flashMessage('Nesprávně zadané heslo', 'error');
        }


        $this->redirect('this');
    }

    public function askForEndSucceeded(UI\Form $form)
    {
        $this->ciphersModel->setYear($this->year);
        $this->resultsModel->setYear($this->year);
        $this->yearsModel->setYear($this->year);
        $this->teamsModel->setYear($this->year);

        $teamId = $this->session->getSection('team')->teamId ?? NULL;


        $checkpointNumber = $this->resultsModel->getLastCheckpointNumber($teamId);

        $codeCorrect = $this->ciphersModel->checkCode($form->values['code'], $checkpointNumber);

        if ($codeCorrect) {
            $this->flashMessage('Ukončili jste hru.', 'info');
            $this->teamsModel->teamEnded($teamId);
        } else {
            $this->flashMessage('Nesprávně zadané heslo', 'error');
        }


        $this->redirect('this');
    }

}
