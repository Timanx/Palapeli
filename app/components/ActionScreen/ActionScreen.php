<?php

use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use App\Models\LogModel;
use App\Models\CiphersModel;
use Nette\Application\UI;

class ActionScreen extends BaseControl
{
    /** @var ResultsModel */
    private $resultsModel;
    /** @var YearsModel */
    private $yearsModel;
    /** @var TeamsModel */
    private $teamsModel;
    /** @var  LogModel */
    private $logModel;
    /** @var CiphersModel */
    private $ciphersModel;
    /** @var Nette\Http\Session */
    private $session;

    private $lastCheckpointData;
    private $defaultScreen;

    const END_CODE = 'JEZIMADEMDOM';

    const DEAD_SCREEN = 0;
    const END_SCREEN = 1;


    public function __construct(
        ResultsModel $resultsModel,
        YearsModel $yearsModel,
        TeamsModel $teamsModel,
        CiphersModel $ciphersModel,
        LogModel $logModel,
        \Nette\Http\Session $session
    )
    {
        parent::__construct();
        $this->resultsModel = $resultsModel;
        $this->yearsModel = $yearsModel;
        $this->teamsModel = $teamsModel;
        $this->ciphersModel = $ciphersModel;
        $this->logModel = $logModel;
        $this->session = $session;
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . '/actionScreen.latte');

        $teamId = $this->session->getSection('team')->teamId ?? NULL;

        $this->teamsModel->setYear($this->year);
        $this->resultsModel->setYear($this->year);
        $this->ciphersModel->setYear($this->year);

        $this->yearsModel->setYear($this->year);

        $checkpointNumber = $this->resultsModel->getFirstEmptyCheckpoint($teamId);

        if ($checkpointNumber === null) {
            $checkpointNumber = 0;
        }


        $data = $this->yearsModel->getEndgameData();

        $this->template->checkpointCount = $data->checkpoint_count;
        $this->template->nextCheckpointNumber = $checkpointNumber;
        $this->template->hasFinishCipher = $data->has_finish_cipher;

        $isLastCheckpoint = $checkpointNumber > ($data->has_finish_cipher ? $data->checkpoint_count : $data->checkpoint_count - 1);

        if ($this->teamsModel->hasTeamEnded($teamId) || $isLastCheckpoint) {
            $this->template->teamEnded = true;
            if ($isLastCheckpoint) {
                $this->flashMessage('Hru jste úspěšně dokončili! Gratulujeme.', 'success');
            } else {
                $this->flashMessage('Již jste ukončili hru a ve hře tak nemůžete pokračovat.');
                $this->flashMessage(sprintf('Přijďte se podívat do cíle: %s (otevřen od %s)', $data->finish_location, $data->finish_open_time), 'info');
            }
            if ($data->afterparty_location !== null) {
                $this->flashMessage(sprintf('Rádi vás uvidíme i na afterparty: %s (od %s)', $data->afterparty_location, $data->afterparty_time), 'info');
            }
        } elseif ($this->yearsModel->hasGameEnded()) {

            $this->template->teamEnded = true;
            $this->flashMessage(sprintf('Hra již skončila, děkujeme za účast. Již nelze zadávat příchody na stanoviště, můžete pouze upravit odchody ze stanovišť na záložce KARTA. Přijďte se za námi podívat do cíle: %s', $data->finish_location), 'info');
            if ($data->afterparty_location !== null) {
                $this->flashMessage(sprintf('Rádi vás uvidíme i na afterparty: %s (od %s)', $data->afterparty_location, $data->afterparty_time), 'info');
            }
        } else {
            $this->template->teamEnded = false;


            $this->lastCheckpointData = $lastCheckpointData = $this->resultsModel->getLastCheckpointData($teamId);

            $this->template->deadOpened = $lastCheckpointData && $this->resultsModel->hasTeamOpenedDead($this->teamId, $lastCheckpointData->checkpoint_number);

            if ($lastCheckpointData) {
                $this->template->deadSolution = $this->ciphersModel->getDeadSolution($lastCheckpointData->checkpoint_number);
            }

            $this->template->endCode = self::END_CODE;


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
        $this->yearsModel->setYear($this->year);
        $this->teamsModel->setYear($this->year);

        $teamId = $this->session->getSection('team')->teamId ?? NULL;
        $checkpointNumber = $this->resultsModel->getFirstEmptyCheckpoint($teamId);

        $codeCorrect = $this->ciphersModel->checkCode($form->values['code'], $checkpointNumber);

        if ($this->yearsModel->hasGameEnded()) {
            $this->flashMessage('Bohužel jste kód nestihli zadat před koncem hry.', 'error');
        } elseif ($codeCorrect) {
            $now = new \Nette\Utils\DateTime('now', new DateTimeZone('Europe/Prague'));
            $this->resultsModel->insertResultsRow($teamId, $checkpointNumber, $now, null, false);
            $this->logModel->log(LogModel::LT_ENTER_CHECKPOINT, $teamId, $checkpointNumber, $this->year);

            if ($this->yearsModel->getCheckpointCount() == $checkpointNumber) {
                $this->teamsModel->teamEnded($teamId);
                $this->flashMessage(sprintf('Gratulujeme k dokončení Palapeli! Hru jste dokončili jako %s., výsledky se započítanými totálkami budou vyhlášeny po skončení hry.', $this->resultsModel->geTeamsArrivedCount($checkpointNumber)), 'success');
            } elseif ($this->yearsModel->getCheckpointCount() == $checkpointNumber + 1) {
                $this->flashMessage(sprintf('Dorazili jste do cíle jako %s.', $this->resultsModel->geTeamsArrivedCount($checkpointNumber)), 'success');
            } elseif ($checkpointNumber == 0) {
                $this->flashMessage(sprintf('Vítejte na startu Palapeli. Kód startovní šifry jste zadali jako %s.', $this->resultsModel->geTeamsArrivedCount($checkpointNumber)), 'success');
            } else {
                $this->flashMessage(sprintf('Dorazili jste na stanoviště %s jako %s.', $checkpointNumber, $this->resultsModel->geTeamsArrivedCount($checkpointNumber)), 'success');
            }
        } else {
            $this->flashMessage('Nesprávně zadaný kód', 'error');
        }


        $this->redirect('this');
    }


    public function createComponentExitTimeInput($name, $time = null)
    {
        $form = new UI\Form;

        $now = new DateTime('now', new DateTimeZone('Europe/Prague'));
        $now->format('N');

        $input = $form->addSelect(
            'day',
            '',
            [
                6 => 'Sobota',
                7 => 'Neděle',
            ]
        );
        $input->required = true;

        $day = $now->format('N');

        $default =
            !empty($this->lastCheckpointData->exit_date_fmt) ?
                ($this->lastCheckpointData->exit_date_fmt == '6' ? 6 : 7) :
                ($day > 5 ? $day : 6);

        $input->setDefaultValue($default);
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

        $time = new DateTime($form->values['exitTime'], new DateTimeZone('Europe/Prague'));

        if ($form->values['day'] == 6) {
            $time->setDate('2022', '01', '22');
        } else {
            $time->setDate('2022', '01', '23');
        }


        $this->resultsModel->insertResultsRow($teamId, $checkpointNumber, null, $time);
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

        $now = new \Nette\Utils\DateTime('now', new DateTimeZone('Europe/Prague'));

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
            $now = new \Nette\Utils\DateTime('now', new DateTimeZone('Europe/Prague'));
            $this->resultsModel->insertResultsRow($teamId, $checkpointNumber, null, $now, true);
            $this->flashMessage(sprintf('Řešením šifry číslo %s je: %s', $checkpointNumber, $deadSolution), 'info');
            $this->logModel->log(LogModel::LT_OPEN_DEAD, $teamId, $checkpointNumber, $this->year);
            $this->redirect('this');
        } else {
            $this->flashMessage('Nesprávně zadaný kód', 'error');
            $this->getPresenter()->redirect('PalaInfo:', self::  DEAD_SCREEN);
        }
    }

    public function askForEndSucceeded(UI\Form $form)
    {
        $this->ciphersModel->setYear($this->year);
        $this->resultsModel->setYear($this->year);
        $this->yearsModel->setYear($this->year);
        $this->teamsModel->setYear($this->year);

        $teamId = $this->session->getSection('team')->teamId ?? NULL;

        $codeCorrect = (mb_strtoupper($form->values['code']) === self::END_CODE);

        if ($codeCorrect) {
            $this->teamsModel->teamEnded($teamId);
            $this->logModel->log(LogModel::LT_END_GAME, $teamId, null, $this->year);
            $this->redirect('this');
        } else {
            $this->flashMessage('Nesprávně zadaný kód', 'error');
            $this->getPresenter()->redirect('PalaInfo:', self::END_SCREEN);
        }

    }

}
