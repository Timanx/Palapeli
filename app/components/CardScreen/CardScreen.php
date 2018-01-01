<?php

use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use App\Models\LogModel;
use App\Models\CiphersModel;
use Nette\Application\UI;

class CardScreen extends BaseControl
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
        $this->logModel = $logModel;
        $this->session = $session;
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . '/cardScreen.latte');

        $this->teamsModel->setYear($this->year);
        $this->resultsModel->setYear($this->year);
        $this->yearsModel->setYear($this->year);

        $checkpointNumber = $this->resultsModel->getLastCheckpointNumber($this->teamId);

        if ($this->teamsModel->hasTeamEnded($this->teamId)) {
            $this->template->teamEnded = true;
            $data = $this->yearsModel->getEndgameData();
            if ($checkpointNumber > $data->checkpoint_count) {
                $this->flashMessage('Hru jste úspěšně dokončili! Gratulujeme.', 'success');
            } else {
                $this->flashMessage('Již jste ukončili hru a ve hře tak nemůžete pokračovat.');
                $this->flashMessage(sprintf('Pozice cíle: %s (otevřen od %s)', $data->finish_location, $data->finish_open_time), 'info');
            }
            if ($data->afterparty_location !== null) {
                $this->flashMessage(sprintf('Místo konání afterparty: %s (od %s)', $data->afterparty_location, $data->afterparty_time), 'info');
            }
        }

        $this->template->checkpointCount = $this->yearsModel->getCheckpointCount();
        $this->template->current = $this->teamId;

        $this->template->render();
    }

    public function createComponentTeamCardForm()
    {
        $this->resultsModel->setYear($this->year);
        $this->yearsModel->setYear($this->year);

        $results = $this->resultsModel->getTeamResults($this->teamId);
        $yearData = $this->yearsModel->getYearData();

        $form = new UI\Form;

        for ($i = 0; $i < $yearData->checkpoint_count; $i++) {
            $checkpoint = $form->addContainer('checkpoint' . $i);
            $checkpoint->addText('entryTime', ($i == 0 ? 'Začátek hry:' : ($i == $yearData->checkpoint_count - 1 ? 'Příchod do cíle:' : 'Příchod na ' . $i . '. stanoviště:')))->setType('time')->setDisabled()->setDefaultValue(((isset($results[$i]) && isset($results[$i]['entry_time'])) ? $results[$i]['entry_time'] : ($i == 0 && isset($yearData->game_start) ? $yearData->game_start->format('H:i') : \App\Presenters\BasePresenter::EMPTY_TIME_VALUE)));

            $exit = $checkpoint->addText('exitTime', ($i == 0 ? 'Odchod ze startu:' : ($i == $yearData->checkpoint_count - 1 ? 'Vyřešení cílového hesla:' : 'Odchod z ' . $i . '. stanoviště:')))->setType('time');

            if (!isset($results[$i])) {
                $exit->setDisabled();
            }

            $exit->setDefaultValue((isset($results[$i]) && isset($results[$i]['exit_time']) ? $results[$i]['exit_time'] : \App\Presenters\BasePresenter::EMPTY_TIME_VALUE));;
            if ($i != $yearData->checkpoint_count - 1) {
                $checkpoint->addCheckbox('usedHint')->setDisabled()->setDefaultValue((isset($results[$i]) && isset($results[$i]['used_hint']) ? $results[$i]['used_hint'] : 0))->setRequired(false);
            }
        }

        $form->addHidden('teamId', $this->teamId);
        $form->addSubmit('send', 'ODESLAT KARTU TÝMU');
        $form->onSuccess[] = [$this, 'teamCardFormSucceeded'];
        return $form;
    }

    public function teamCardFormSucceeded(UI\Form $form, array $values)
    {
        $this->resultsModel->setYear($this->year);

        $teamId = $values['teamId'];
        foreach ($values as $number => $checkpoint) {
            if ($number != 'teamId') {
                $number = substr($number, 10);

                if ($checkpoint['exitTime'] != '' &&
                    $checkpoint['exitTime'] != \App\Presenters\BasePresenter::EMPTY_TIME_VALUE


                ) {
                    if ($checkpoint['exitTime'] == \App\Presenters\BasePresenter::EMPTY_TIME_VALUE || strlen($checkpoint['exitTime']) == 0) {
                        $checkpoint['exitTime'] = null;
                    }

                    $this->resultsModel->insertResultsRow($teamId, $number, null, $checkpoint['exitTime'], null);
                }

                //Handle finish
                if ($number == count($values) - 1 && $checkpoint['exitTime'] != '' && $checkpoint['exitTime'] != \App\Presenters\BasePresenter::EMPTY_TIME_VALUE) {

                    $this->resultsModel->insertResultsRow($teamId, ((int)$number + 1), $checkpoint['exitTime'], $checkpoint['exitTime']);
                }
            }
        }

        $this->flashMessage('Údaje z karty týmu byly úspěšně uloženy', 'success');
        $this->getPresenter()->redirect('this');
    }
}
