<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI;
use Nette\Mail\Message;
use Nette\Mail\SendmailMailer;


class BasePresenter extends Nette\Application\UI\Presenter
{
    public $selectedYear;
    public $selectedCalendarYear;

    const ALMOST_DAY = 86399; //End dates should be inclusive

    const REGISTRATION_START = 1477580400; //27. 10. 2016 17:00
    const REGISTRATION_END = 1485039600 + self::ALMOST_DAY; //22. 1. 2017
    const TEAM_LIMIT = 70;
    const CURRENT_YEAR = 6;
    const CURRENT_CALENDAR_YEAR = 2017;

    const ENTRY_FEE = 240; //CZK
    const ENTRY_FEE_DEADLINE = 1485212400 + self::ALMOST_DAY; //24. 1. 2017
    const ENTRY_FEE_RETURN_DEADLINE = 1485385200 + self::ALMOST_DAY; ///26. 1. 2015
    const GAME_DATE = 1485558000; //28. 1. 2017
    const GAME_START = 1485590400 ; //28. 1. 2017 9:00
    const ENTRY_FEE_ACCOUNT = '237977821/0300';

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

    const ORG_TEAM_ID = -1;

    const PAY_OK = 1;
    const PAY_NOK = 0;
    const PAY_START = 2;
    const SHOULD_NOT_PAY = 3;

    public function render()
    {
        $this->getYearData();

        $this->template->teamName = null;
        if($this->session->hasSection('team') && !empty($this->session->getSection('team')->teamName)) {
            $this->template->teamName = $this->session->getSection('team')->teamName;
            $this->template->teamNameUpper = Nette\Utils\Strings::upper($this->session->getSection('team')->teamName);
        }

        if($this->session->hasSection('team') && !empty($this->session->getSection('team')->teamId)) {
            $this->template->teamId = $this->session->getSection('team')->teamId;
        }

        if($this->session->hasSection('team') && !empty($this->session->getSection('team')->teamId) && $this->session->getSection('team')->teamId == self::ORG_TEAM_ID) {
            $this->template->orgLogged = true;
        } else {
            $this->template->orgLogged = false;
        }

        $this->template->lastYear = self::CURRENT_YEAR - 1;
        $this->template->selectedYear = $this->selectedYear;
        $this->template->selectedCalendarYear = $this->selectedCalendarYear;
        $this->template->currentYear = self::CURRENT_YEAR;
        $this->template->currentCalendarYear = self::CURRENT_CALENDAR_YEAR;
        $this->template->isSelectedYearCurrent = $this->selectedYear == self::CURRENT_YEAR;
        $now = time();
        $this->template->hasRegistrationStarted = $this->hasRegistrationStarted($now);
        $this->template->hasRegistrationEnded = $this->hasRegistrationEnded($now);
        $this->template->isRegistrationOpen = $this->isRegistrationOpen($now);
        $this->template->hasGameStarted = $this->hasGameStarted($now);
    }

    public function getYearData() {
        if($this->session->hasSection('selected') && !empty($this->session->getSection('selected')->year)) {
            $this->selectedYear = $this->session->getSection('selected')->year;
        } else {
            $this->session->getSection('selected')->year = self::CURRENT_YEAR;
            $this->selectedYear = self::CURRENT_YEAR;
        }
        if($this->session->hasSection('selected') && !empty($this->session->getSection('selected')->calendarYear)) {
            $this->selectedCalendarYear = $this->session->getSection('selected')->calendarYear;
        } else {
            $this->session->getSection('selected')->calendarYear = self::CURRENT_CALENDAR_YEAR;
            $this->selectedCalendarYear = self::CURRENT_CALENDAR_YEAR;
        }
    }

    public function hasGameStarted($time = null) {
        if(!isset($time)) {
            $time = time();
        }
        return $time >= self::GAME_START;
    }

    public function hasRegistrationStarted($time = null) {
        if(!isset($time)) {
            $time = time();
        }
        return $time >= self::REGISTRATION_START;
    }

    public function hasRegistrationEnded($time = null) {
        if(!isset($time)) {
            $time = time();
        }
        return $time >= self::REGISTRATION_END;
    }

    public function isRegistrationOpen($time = null) {
        if(!isset($time)) {
            $time = time();
        }
        return $this->hasRegistrationStarted($time) && !$this->hasRegistrationEnded($time);
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
        $mail->setFrom('Palapeli Web <'. $values['sender'] .'>')
            ->addReplyTo($values['sender'])
            ->addTo('organizatori@palapeli.cz')
            ->setSubject('Zpráva z webu: ' . $values['subject'])
            ->setBody(nl2br($values['message'] . '\n\nZpráva odeslaná z webu.'));

        $mailer = new SendmailMailer;
        $mailer->send($mail);

        $this->flashMessage('E-mail byl úspěšně odeslán.', 'success');
        $this->redirect('this');
    }
}
