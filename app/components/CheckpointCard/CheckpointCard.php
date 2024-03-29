<?php

use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use Nette\Application\UI;

class CheckpointCard extends BaseControl
{
    /** @var ResultsModel */
    private $resultsModel;
    /** @var YearsModel */
    private $yearsModel;
    /** @var TeamsModel */
    private $teamsModel;

    private $checkpointNumber;
    private $orderByPrevious;

    public function __construct(ResultsModel $resultsModel, YearsModel $yearsModel, TeamsModel $teamsModel)
    {
        parent::__construct();
        $this->resultsModel = $resultsModel;
        $this->yearsModel = $yearsModel;
        $this->teamsModel = $teamsModel;
    }

    public function setCheckpointNumber($number)
    {
        $this->checkpointNumber = $number;
    }

    public function setOrderByPrevious($value)
    {
        $this->orderByPrevious = $value;
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . '/checkpointCard.latte');

        $this->yearsModel->setYear($this->year);
        $this->teamsModel->setYear($this->year);
        $this->resultsModel->setYear($this->year);
        $this->template->selectedYear = $this->year;
        $this->template->checkpoint = $this->checkpointNumber;
        $this->template->teamsCount = $this->teamsModel->getTeamsCount();
        $this->template->checkpointCount = $this->yearsModel->getCheckpointCount();

        $this->template->render();
    }

    public function createComponentSelectCheckpointForm()
    {
        $checkpoint = (isset($_GET['checkpoint']) ? $_GET['checkpoint'] : null);
        $this->yearsModel->setYear($this->year);

        $checkpointCount = $this->yearsModel->getCheckpointCount();

        $options = [];
        for ($i = 0; $i <= $checkpointCount; $i++) {
            $options[$i] = ($i == $checkpointCount - 1 ? 'Příchod do cíle' : ($i == $checkpointCount ? 'Vyřešení cílového hesla' : ($i == 0 ? 'Start' : $i . '. stanoviště')));
        }

        array_unshift($options, 'Vyberte stanoviště');

        $form = new UI\Form;

        $form->addCheckbox('previous', 'Řadit týmy podle příchodu na předchozí stanoviště')->setAttribute('onchange', 'this.form.submit()');
        $form->addSelect('checkpoint', '', $options, 1)->setAttribute('onchange', 'this.form.submit()');
        $form->addHidden('currentCheckpoint', $checkpoint);
        $form->onSuccess[] = [$this, 'checkpointSelected'];
        return $form;
    }

    public function checkpointSelected(UI\Form $form, array $values)
    {
        if ($values['previous']) {
            $checkpoint = $values['currentCheckpoint'];
        } elseif ($values['checkpoint'] == 0) {
            $checkpoint = 0;
        } else {
            $checkpoint = $values['checkpoint'] - 1;
        }

        $this->getPresenter()->redirect('Administration:checkpointCard', ['checkpoint' => $checkpoint, 'previous' => $values['previous']]);
    }

    public function createComponentSelectOnlyCheckpointForm()
    {
        $this->yearsModel->setYear($this->year);

        $checkpointCount = $this->yearsModel->getCheckpointCount();

        $options = [];
        for ($i = 0; $i < $checkpointCount; $i++) {
            $options[$i] = ($i == $checkpointCount - 1 ? 'Cíl' : ($i == 0 ? 'Start' : $i . '. stanoviště'));
        }
        array_unshift($options, 'Vyberte stanoviště');


        $form = new UI\Form;
        $form->addSelect('checkpoint', '', $options, 1)->setAttribute('onchange', 'this.form.submit()');
        $form->onSuccess[] = [$this, 'onlyCheckpointSelected'];
        return $form;
    }

    public function onlyCheckpointSelected(UI\Form $form, array $values)
    {
        $this->redirect('this', ['checkpoint' => ($values['checkpoint'] == 0 ? 0 : $values['checkpoint'] - 1)]);
    }

    public function createComponentCheckpointCardForm()
    {
        $checkpoint = $_GET['checkpoint'];
        $previous = array_key_exists('previous', $_GET) && $_GET['previous'];
        $this->resultsModel->setYear($this->year);

        $data = $this->resultsModel->getCheckpointEntryTimes($checkpoint, (bool)$previous);

        $form = new UI\Form;

        for ($i = 0; $i < count($data); $i++) {
            $teamContainer = $form->addContainer('team' . $i);
            $teamName = $teamContainer->addText('entryTime', $data[$i]['name'])->setType('time')->setDefaultValue((isset($data[$i]['entry_time']) ? $data[$i]['entry_time'] : \App\Presenters\BasePresenter::EMPTY_TIME_VALUE));
            if ($checkpoint > 1 && !$data[$i]['visited_previous']) {
                $teamName->getLabelPrototype()->addAttributes(['class' => 'dead', 'title' => 'Tým nemá vyplněný příchod na předchozím stanovišti']);
            }
            $teamContainer->addHidden('teamId', $data[$i]['id']);
            $teamContainer->addButton('currentTime', 'Teď')->setAttribute('onclick', 'submitCurrentTime(' . $i . ', this.form)');
            $teamContainer->addButton('inputtedTime', 'Zadáno')->setAttribute('onclick', 'this.form.submit()');
        }
        $form->addSubmit('send', 'ODESLAT KARTU STANOVIŠTĚ');
        $form->onSuccess[] = [$this, 'checkpointCardFormSucceeded'];
        return $form;
    }

    public function checkpointCardFormSucceeded(UI\Form $form, array $values)
    {
        $this->flashMessage('Zadávání znemožněno', 'error');
        $this->redirect('this');

        $checkpoint = $_GET['checkpoint'];

        $this->yearsModel->setYear($this->year);
        $this->resultsModel->setYear($this->year);

        $checkpointCount = $this->yearsModel->getCheckpointCount();

        foreach ($values as $team) {
            if (!isset($team['entryTime']) || $team['entryTime'] == '' || $team['entryTime'] == \App\Presenters\BasePresenter::EMPTY_TIME_VALUE) {
                $team['entryTime'] = NULL;
            }

            if ($team['entryTime']) {
                $this->resultsModel->insertResultsRow($team['teamId'], $checkpoint, $team['entryTime']);

                if ($checkpoint == $checkpointCount) {
                    $this->resultsModel->insertResultsRow($team['teamId'], $checkpoint, NULL, $team['entryTime']);
                }
            }
        }

        $this->flashMessage('Údaje z karty stanoviště byly úspěšně uloženy', 'success');
        $this->redirect('this');
    }
}
