<?php
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use Nette\Application\UI;

class TeamCard extends BaseControl
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

        $this->teamsModel->setYear($this->year);

        $this->yearsModel->setYear($this->year);
        $this->template->selectedYear = $this->year;
        $this->template->checkpointCount = $this->yearsModel->getCheckpointCount();
        $this->template->teamName = $this->teamsModel->getTeamName($teamId);
        $this->template->isTeamFinalized = $this->teamsModel->isTeamFinalized($teamId);
        $this->template->teamId = $teamId;

        $this->template->render();
    }

    public function createComponentTeamCardForm()
    {
        $teamId = $_GET['teamCard-team'] ?? NULL;
        $this->teamId = $teamId;
        $this->resultsModel->setYear($this->year);
        $this->yearsModel->setYear($this->year);

        $results = $this->resultsModel->getTeamResults($teamId);
        $yearData = $this->yearsModel->getYearData();

        $form = new UI\Form;

        for ($i = 0; $i < $yearData->checkpoint_count; $i++) {
            $checkpoint = $form->addContainer('checkpoint' . $i);
            $checkpoint->addText('entryTime', ($i == 0 ? 'Začátek hry:' : ($i == $yearData->checkpoint_count - 1 ? 'Příchod do cíle:' : 'Příchod na ' . $i . '. stanoviště:')))->setType('time')->setDefaultValue(((isset($results[$i]) && isset($results[$i]['entry_time'])) ? $results[$i]['entry_time'] : ($i == 0 && isset($yearData->game_start) ? $yearData->game_start->format('H:i') : \App\Presenters\BasePresenter::EMPTY_TIME_VALUE)));
            $checkpoint->addText('exitTime', ($i == 0 ? 'Odchod ze startu:' : ($i == $yearData->checkpoint_count - 1 ? 'Vyřešení cílového hesla:' : 'Odchod z ' . $i . '. stanoviště:')))->setType('time')->setDefaultValue((isset($results[$i]) && isset($results[$i]['exit_time']) ? $results[$i]['exit_time'] : \App\Presenters\BasePresenter::EMPTY_TIME_VALUE));;
            if($i != $yearData->checkpoint_count - 1) {
                $checkpoint->addCheckbox('usedHint')->setDefaultValue((isset($results[$i]) && isset($results[$i]['used_hint']) ? $results[$i]['used_hint'] : 0))->setRequired(false);
            }
        }

        $form->addHidden('teamId', $teamId);
        $form->addSubmit('send', 'ODESLAT KARTU TÝMU');
        $form->onSuccess[] = [$this, 'teamCardFormSucceeded'];
        return $form;
    }

    public function teamCardFormSucceeded(UI\Form $form, array $values)
    {
        $this->flashMessage('Zadávání znemožněno', 'error');
        $this->redirect('this');

        $this->resultsModel->setYear($this->year);

        $teamId = $values['teamId'];
        foreach($values as $number => $checkpoint) {
            if($number != 'teamId') {
                $number = substr($number, 10);

                if (isset($checkpoint['usedHint']) &&
                    $checkpoint['usedHint']
                    ||
                    $checkpoint['exitTime'] != '' &&
                    $checkpoint['exitTime'] != \App\Presenters\BasePresenter::EMPTY_TIME_VALUE
                    ||
                    $checkpoint['entryTime'] != '' &&
                    $checkpoint['entryTime'] != \App\Presenters\BasePresenter::EMPTY_TIME_VALUE


                ) {
                    if ($checkpoint['exitTime'] == \App\Presenters\BasePresenter::EMPTY_TIME_VALUE || strlen($checkpoint['exitTime']) == 0) {
                        $checkpoint['exitTime'] = NULL;
                    }
                    if ($checkpoint['entryTime'] == \App\Presenters\BasePresenter::EMPTY_TIME_VALUE || strlen($checkpoint['entryTime']) == 0) {
                        $checkpoint['entryTime'] = NULL;
                    }

                    $this->resultsModel->insertResultsRow($teamId, $number, $checkpoint['entryTime'], $checkpoint['exitTime'], $checkpoint['usedHint'] ?? NULL);
                }

                //Handle finish
                if ($number == count($values) - 1 && $checkpoint['exitTime'] != '' && $checkpoint['exitTime'] != \App\Presenters\BasePresenter::EMPTY_TIME_VALUE) {

                    $this->resultsModel->insertResultsRow($teamId, ((int)$number + 1), $checkpoint['exitTime'], $checkpoint['exitTime']);
                }
            }
        }

        $this->flashMessage('Údaje z karty týmu byly úspěšně uloženy', 'success');
        $this->redirect('this', ['team' => $teamId]);
    }

    public function createComponentSelectTeamForm()
    {
        //$teamId = (isset($_GET['team']) ? $_GET['team'] : null);

        $this->resultsModel->setYear($this->year);

        $teams = $this->resultsModel->getTeamsWithFilledStatus();

        $options = ['Nevyplněné týmy' => [], 'Vyplněné týmy' => []];
        foreach ($teams as $team) {
            $options[(!$team->team_filled ? 'Nevyplněné týmy' : 'Vyplněné týmy')][$team->id] = $team->name;
        }

        $form = new UI\Form;
        $form->addSelect('teams', null, $options, 1)->setPrompt('Vyberte tým')->setAttribute('onchange', 'this.form.submit()');
        $form->onSuccess[] = [$this, 'teamSelected'];
        return $form;
    }

    public function teamSelected(UI\Form $form, array $values)
    {
        $this->redirect('this', ['team' => $values['teams']]);
    }

    public function createComponentFinalizeTeam()
    {
        $form = new UI\Form;

        $form->addSubmit('finalize', 'FINALIZOVAT TÝM');
        $form->addHidden('team_id', $_GET['teamCard-team'] ?? null);
        $form->onSuccess[] = [$this, 'teamFinalized'];

        return $form;
    }

    public function teamFinalized(UI\Form $form, array $values)
    {
        $this->teamsModel->setYear($this->year);
        $this->teamsModel->finalizeTeam($values['team_id']);
    }

}
