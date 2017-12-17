<?php
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use App\Models\LogModel;
use Nette\Application\UI;

class TeamMessage extends BaseControl
{
    /** @var ResultsModel */
    private $resultsModel;
    /** @var YearsModel */
    private $yearsModel;
    /** @var TeamsModel */
    private $teamsModel;
    /** @var LogModel */
    private $logModel;

    public function __construct(
        ResultsModel $resultsModel,
        YearsModel $yearsModel,
        TeamsModel $teamsModel,
        LogModel $logModel
    ) {
        parent::__construct();
        $this->resultsModel = $resultsModel;
        $this->yearsModel = $yearsModel;
        $this->teamsModel = $teamsModel;
        $this->logModel = $logModel;
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . '/teamMessage.latte');
        $this->template->selectedYear = $this->year;
        $this->template->render();
    }

    public function createComponentTeamMessageForm()
    {
        $this->logModel->setYear($this->year);


        $logTypes = $this->logModel->getLogTypes();

        $form = new UI\Form;

        $form->addSelect('logtype', 'Typ zprávy', $logTypes);
        $form->addTextArea('message', 'Zpráva');
        $form->addSubmit('send', 'ODESLAT ZPRÁVU TÝMŮM');
        $form->onSuccess[] = [$this, 'teamCardFormSucceeded'];
        return $form;
    }

    public function teamCardFormSucceeded(UI\Form $form, array $values)
    {
        $this->logModel->log($values['logtype'], null, null, $this->year, $values['message']);

        $this->flashMessage('Zpráva týmům byla úspěšně odeslána', 'success');
        $this->redirect('this');
    }
}
