<?php

namespace App\Presenters;

use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use Nette;
use Nette\Application\UI;
use Nette\Mail\Message;
use Nette\Mail\SendmailMailer;


class BasePresenter extends Nette\Application\UI\Presenter
{
    const BLUE = '#005BD0';
    const RED = '#FF0000';
    const YELLOW = '#FFCA05';
    const GREEN = '#69B300';
    const TEAM_COLOR = '#005BD0';
    const INFO_COLOR = '#FF0000';
    const DISCUSSION_COLOR = '#FFCA05';
    const GAME_COLOR = '#69B300';
    const ADMINISTRATION_COLOR = 'black';

    const BLUE_TINT = '#A9CEFF';
    const RED_TINT = '#FFB1B1';
    const YELLOW_TINT = '#FFEBA2';
    const GREEN_TINT = '#D7FF9F';

    const ORG_MAIL_FORMAT = 'Palapeli Web <organizatori@palapeli.cz>';
    const EMPTY_TIME_VALUE = '--:--';

    const ORG_TEAM_ID = -1;

    const PAY_OK = 1;
    const PAY_NOK = 0;
    const PAY_START = 2;
    const SHOULD_NOT_PAY = 3;

    public $selectedYear;
    public $selectedCalendarYear;

    /** @var YearsModel */
    private $yearsModel;

    protected $teamId;

    public function __construct()
    {
        parent::__construct();
    }

    public function injectYearsModel(YearsModel $yearsModel)
    {
        $this->yearsModel = $yearsModel;
    }

    public function render()
    {
        $this->getYearData();
        $this->yearsModel->setYear($this->selectedYear);

        $this->template->teamName = null;
        if($this->session->hasSection('team') && !empty($this->session->getSection('team')->teamName)) {
            $this->template->teamName = $this->session->getSection('team')->teamName;
            $this->template->teamNameUpper = Nette\Utils\Strings::upper($this->session->getSection('team')->teamName);
        }

        if($this->session->hasSection('team') && !empty($this->session->getSection('team')->teamId)) {
            $this->template->teamId = $this->session->getSection('team')->teamId;
            $this->teamId = $this->session->getSection('team')->teamId;
        }

        if($this->session->hasSection('team') && !empty($this->session->getSection('team')->teamId) && $this->session->getSection('team')->teamId == self::ORG_TEAM_ID) {
            $this->template->orgLogged = true;
        } else {
            $this->template->orgLogged = false;
        }

        $currentYearData = $this->yearsModel->getCurrentYearData();

        $this->template->lastYear = $currentYearData->year - 1;
        $this->template->selectedYear = $this->selectedYear;
        $this->template->selectedCalendarYear = $this->selectedCalendarYear;
        $this->template->currentYear = $currentYearData->year;
        $this->template->currentCalendarYear = $currentYearData->calendar_year;
        $this->template->isSelectedYearCurrent = ($this->selectedYear == $currentYearData->year);
        $this->template->showTesterNotification = $currentYearData->show_tester_notification;
    }

    public function getYearData() {
        $currentYearData = $this->yearsModel->getCurrentYearData();

        if($this->session->hasSection('selected') && !empty($this->session->getSection('selected')->year)) {
            $this->selectedYear = $this->session->getSection('selected')->year;
        } else {
            $this->session->getSection('selected')->year = $currentYearData->year;
            $this->selectedYear = $currentYearData->year;
        }
        if($this->session->hasSection('selected') && !empty($this->session->getSection('selected')->calendarYear)) {
            $this->selectedCalendarYear = $this->session->getSection('selected')->calendarYear;
        } else {
            $this->session->getSection('selected')->calendarYear = $currentYearData->calendar_year;
            $this->selectedCalendarYear = $currentYearData->calendar_year;
        }
    }

    public function prepareHeading($heading) {
        $this->template->heading = Nette\Utils\Strings::upper($heading);
        $this->template->title = $heading;
    }

    protected function createComponentMailForm()
    {
        $form = new UI\Form;
        $form->addText('sender')->setAttribute('placeholder', 'Váš e-mail')->setRequired(false)->addRule(UI\Form::EMAIL, 'E-mail není ve správném tvaru.');
        $form->addText('subject')->setAttribute('placeholder', 'Předmět')->setRequired('Zadejte prosím předmět e-mailu.');
        $form->addTextArea('message')->setAttribute('placeholder', 'Zpráva')->setRequired('Zadejte prosím text zprávy.');
        $form->addSubmit('cancel', 'ODESLAT E-MAIL');
        $form->onSuccess[] = [$this, 'mailFormSucceeded'];
        return $form;
    }

    public function mailFormSucceeded(UI\Form $form, array $values) {
        foreach($values as &$value) {
            $value = strip_tags($value);
        }

        $mail = new Message;
        if(strlen($values['sender']) > 0) {
            $mail->setFrom($values['sender'])
                ->addReplyTo($values['sender'])
                ->addTo('organizatori@palapeli.cz')
                ->setSubject('Zpráva z webu: ' . $values['subject'])
                ->setBody($values['message'] . '

Zpráva odeslaná z webu.');
        } else {
            $mail->setFrom('Palapeli Web <organizatori@palapeli.cz>')
                ->addTo('organizatori@palapeli.cz')
                ->setSubject('Zpráva z webu: ' . $values['subject'])
                ->setBody($values['message'] . '

Zpráva odeslaná z webu.');
        }

        $mailer = new SendmailMailer;
        $mailer->send($mail);

        $this->flashMessage('E-mail byl úspěšně odeslán.', 'success');
        $this->redirect('this');
    }
}
