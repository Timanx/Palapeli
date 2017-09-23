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

    public function __construct(ResultsModel $resultsModel, YearsModel $yearsModel, TeamsModel $teamsModel)
    {
        parent::__construct();
        $this->resultsModel = $resultsModel;
        $this->yearsModel = $yearsModel;
        $this->teamsModel = $teamsModel;
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . '/teamCard.latte');

        $teamId = $_GET['teamCard-team'] ?? NULL;

        $this->yearsModel->setYear($this->year);
        $this->template->selectedYear = $this->year;
        $this->template->checkpointCount = $this->yearsModel->getCheckpointCount();
        $this->template->teamName = $this->teamsModel->getTeamName($teamId);
        $this->template->teamId = $teamId;

        $this->template->render();
    }

    public function createComponentSelectCheckpointForm()
    {
        $checkpoint = (isset($_GET['checkpoint']) ? $_GET['checkpoint'] : null);
        if (!isset($this->selectedYear)) {
            $this->getYearData();
        }

        $this->yearsModel->setYear($this->selectedYear);

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

        $this->redirect('this', ['checkpoint' => $checkpoint, 'previous' => $values['previous']]);
    }

    public function createComponentSelectOnlyCheckpointForm()
    {
        $checkpoint = (isset($_GET['checkpoint']) ? $_GET['checkpoint'] : null);
        if (!isset($this->selectedYear)) {
            $this->getYearData();
        }

        $this->yearsModel->setYear($this->selectedYear);

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
        if (!isset($this->selectedYear)) {
            $this->getYearData();
        }
        $this->resultsModel->setYear($this->selectedYear);

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
        $checkpoint = $_GET['checkpoint'];

        if (!isset($this->selectedYear)) {
            $this->getYearData();
        }
        $this->yearsModel->setYear($this->selectedYear);
        $this->resultsModel->setYear($this->selectedYear);

        $checkpointCount = $this->yearsModel->getCheckpointCount();

        foreach ($values as $team) {
            if (!isset($team['entryTime']) || $team['entryTime'] == '' || $team['entryTime'] == self::EMPTY_TIME_VALUE) {
                $team['entryTime'] = NULL;
            }

            $this->resultsModel->insertResultsRow($team['teamId'], $checkpoint, $team['entryTime']);

            if ($checkpoint == $checkpointCount) {
                $this->resultsModel->insertResultsRow($team['teamId'], $checkpoint, NULL, $team['entryTime']);
            }
        }

        $this->flashMessage('Údaje z karty stanoviště byly úspěšně uloženy', 'success');
        $this->redirect('this');
    }
}