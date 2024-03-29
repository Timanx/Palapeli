<?php
namespace App\Presenters;

use App\Models\YearsModel;
use Endroid\QrCode\QrCode;
use Nette;
use Nette\Application\UI;
use Nette\Mail\Message;
use Nette\Mail\SendmailMailer;
use App\Models\TeamsModel;


class TeamPresenter extends BasePresenter
{
    /** @var  TeamsModel */
    private $teamsModel;
    /** @var YearsModel */
    private $yearsModel;

    public function __construct(TeamsModel $teamsModel, YearsModel $yearsModel)
    {
        parent::__construct();
        $this->teamsModel = $teamsModel;
        $this->yearsModel = $yearsModel;
    }

    public function renderRegistration()
    {
        parent::render();
        $this->prepareHeading('Registrace');
        $this->teamsModel->setYear($this->selectedYear);
        $this->yearsModel->setYear($this->selectedYear);
        $this->template->hasRegistrationStarted = $this->yearsModel->hasRegistrationStarted();
        $this->template->isRegistrationOpen = $this->yearsModel->isRegistrationOpen();
        $this->template->registrationStart = $a = $this->yearsModel->getRegistrationStart();

        $teamLimit = $this->yearsModel->getTeamLimit();
        $this->template->displayStandbyWarning = $teamLimit && $this->teamsModel->getTeamsCount() >= $teamLimit;
    }

    public function renderDefault()
    {
        parent::render();
        $this->prepareHeading('Přihlášení');
        $this->yearsModel->setYear($this->selectedYear);
        $this->template->isRegistrationOpen = $this->yearsModel->isRegistrationOpen();
    }

    public function renderCancel()
    {
        parent::render();
        $this->prepareHeading('Zrušení účasti');

        $this->yearsModel->setYear($this->selectedYear);
        $this->template->hasRegistrationStarted = $this->yearsModel->hasRegistrationStarted();
        $this->template->isRegistrationOpen = $this->yearsModel->isRegistrationOpen();
        $this->template->hasGameStarted = $this->yearsModel->hasGameStarted();

        if(isset($this->teamId)) {
            $this->teamsModel->setYear($this->selectedYear);
            $this->template->registered = $this->teamsModel->isTeamRegistered($this->teamId);
        } else {
            $this->template->registered = false;
        }
    }

    public function renderPassword()
    {
        parent::render();
        $this->prepareHeading('Zapomenuté heslo');
    }

    public function renderEdit()
    {
        parent::render();
        $this->prepareHeading('Úprava údajů');

        $this->yearsModel->setYear($this->selectedYear);
        $this->template->hasRegistrationStarted = $this->yearsModel->hasRegistrationStarted();
        $this->template->isRegistrationOpen = $this->yearsModel->isRegistrationOpen();

        if(isset($this->teamId)) {
            $this->teamsModel->setYear($this->selectedYear);
            $this->template->registered = $this->teamsModel->isTeamRegistered($this->teamId);
        } else {
            $this->template->registered = false;
        }
    }

    public function renderPayment()
    {
        parent::render();
        $this->prepareHeading('Platba startovného');
        if(isset($this->teamId)) {
            $this->teamsModel->setYear($this->selectedYear);
            $this->yearsModel->setYear($this->selectedYear);

            $this->template->yearData = $this->yearsModel->getYearData();
            $this->template->registered = $registered = $this->teamsModel->isTeamRegistered($this->teamId);

            if($registered) {
                $teamLimit = $this->yearsModel->getTeamLimit();
                $this->template->isSubstitute = $teamLimit && $this->teamsModel->getTeamRegistrationOrder($this->teamId) >= $teamLimit;
                $this->template->paid = $this->teamsModel->getTeamPaymentStatus($this->teamId);

                $yearData = $this->yearsModel->getYearData();
                $variableSymbol = $this->selectedCalendarYear . $this->teamId;

                list($accountNumber,$bankCode) = explode('/', $yearData->entry_fee_account);

                $QRString = 'SPD*1.0*ACC:CZ18' . $bankCode . '000000' . $accountNumber . '*AM:' .  $yearData->entry_fee . '*CC:CZK*X-VS:' . $variableSymbol . '*MSG:Palapeli ' . $this->selectedCalendarYear .  ' ' . $this->teamsModel->getTeamName($this->teamId) . '*';

                $QRCode = new QrCode($QRString);
                $QRCode->setSize(150);
                $this->template->QRCode = $a = $QRCode->writeDataUri();
                $this->template->variableSymbol = $variableSymbol;


            } else {
                $this->template->paid = self::SHOULD_NOT_PAY;
            }
        } else {
            $this->template->registered = false;
            $this->template->paid = self::SHOULD_NOT_PAY;
        }
    }

    public function renderLogout()
    {
        unset($this->session->getSection('team')->teamName);
        unset($this->session->getSection('team')->teamId);
        parent::render();
        $this->prepareHeading('Odhlášení');

    }

    public function actionRegisterLogged()
    {
        if(!$this->selectedYear) {
            parent::getYearData();
        }

        $this->teamsModel->setYear($this->selectedYear);
        $this->yearsModel->setYear($this->selectedYear);


        if(!$this->yearsModel->isRegistrationOpen()) {
            $this->flashMessage('Registrace do ' . $this->selectedYear . '. ročníku je uzavřena.');
            $this->redirect('Info:');
        }
        if(isset($this->session->getSection('team')->teamId)) {
            $teamId = $this->session->getSection('team')->teamId;
            $registered = $this->teamsModel->isTeamRegistered($teamId);

            if(!$registered) {
                $teamData = $this->teamsModel->getMostRecentTeamYearData($teamId);

                if ($teamData) {
                    $this->teamsModel->registerTeam($teamId, $teamData->member1, $teamData->member2, $teamData->member3, $teamData->member4);
                } else {
                    $this->teamsModel->registerTeam($teamId);
                }
                $teamsCount = $this->teamsModel->getTeamsCount();
                $teamLimit = $this->yearsModel->getTeamLimit();
                if($teamLimit && $teamsCount > $teamLimit) {
                    $this->flashMessage('Tým ' . $this->session->getSection('team')->teamName . ' byl úspěšně zaregistrován do aktuálního ročníku jako náhradní. Již je totiž naplněn limit počtu týmů, které se mohou hry zúčastnit. Jakmile se pro vás uvolní místo, ozveme se vám.', 'info');
                } else {
                    $this->flashMessage('Tým ' . $this->session->getSection('team')->teamName . ' byl úspěšně zaregistrován do aktuálního ročníku.', 'success');
                }

                $this->flashMessage('Registruješ tým lidí a tím nám dáváš jejich jméno, příjmení, telefony a e-maily, tak jim to prosím alespoň řekni. My za to slíbíme, že je nezneužijeme jinak, než v souvislosti se hrou. Data od nás tahají šifrovačky.cz a statek.seslost.cz. Existuje GDPR, tam najdeš, co po nás můžeš chtít. Když se vám něco nebude líbit, tak se ozvěte orgům, Dark to s vámi vyřídí.', 'info');

                $this->redirect('Team:edit');
            } else {
                $this->flashMessage('Do ' . $this->yearsModel->getCurrentYearNumber() . '. ročníku už jste zaregistrováni.', 'info');
                $this->redirect('Team:edit');
            }
        } else {
            $this->flashMessage('Nejste přihlášení.');
            $this->redirect('Info:');
        }
    }

    protected function createComponentLoginForm()
    {
        if(!$this->selectedYear) {
            parent::getYearData();
        }

        $form = new UI\Form;
        $form->addText('name', 'Jméno týmu:')->setRequired('Zadejte prosím jméno týmu.');
        $form->addPassword('password', 'Heslo:')->setRequired('Zadejte prosím heslo.');
        $form->addSubmit('login', 'PŘIHLÁSIT');
        $form->addHidden('year', $this->selectedYear);
        $form->onSuccess[] = [$this, 'loginFormSucceeded'];
        return $form;
    }

    protected function createComponentRegistrationForm()
    {
        if(!$this->selectedYear) {
            parent::getYearData();
        }

        $form = new UI\Form;
        $form->addText('name', '*Jméno týmu:')->setRequired('Zadejte prosím jméno týmu.')->addRule(UI\Form::MAX_LENGTH, 'Název týmu může mít maximálně 255 znaků', 255)->setAttribute('style', 'width:calc(100% - 10px)');
        $form->addPassword('password', '*Heslo:')->setRequired('Zadejte prosím heslo.')->addRule(UI\Form::MAX_LENGTH, 'Heslo může mít maximálně 255 znaků', 255);
        $form->addPassword('passwordVerify', '*Heslo znovu:')->setRequired('Zadejte prosím heslo ještě jednou pro kontrolu.')->addRule(UI\Form::EQUAL, 'Hesla se neshodují', $form['password']);
        $form->addText('member1', '*První člen týmu:')->setRequired('Zadejte prosím jméno prvního člena týmu.')->addRule(UI\Form::MAX_LENGTH, 'Jméno prvního člena může mít maximálně 255 znaků', 255);
       $form->addText('member2', 'Druhý člen týmu:')->setRequired(false)->addRule(UI\Form::MAX_LENGTH, 'Jméno druhého člena může mít maximálně 255 znaků', 255);
        $form->addText('member3', 'Třetí člen týmu:')->setRequired(false)->addRule(UI\Form::MAX_LENGTH, 'Jméno třetího člena může mít maximálně 255 znaků', 255);
        $form->addText('member4', 'Čtvrtý člen týmu:')->setRequired(false)->addRule(UI\Form::MAX_LENGTH, 'Jméno čtvrtého člena může mít maximálně 255 znaků', 255);
        $form->addText('phone1', '*Telefon na 1. člena:')->setRequired('Zadejte prosím telefon.')->addRule(UI\Form::MAX_LENGTH, 'Telefon na prvního člena může mít maximálně 20 znaků', 20);
        $form->addText('phone2', 'Záložní telefon:')->setRequired(false)->addRule(UI\Form::MAX_LENGTH, 'Záložní telefon může mít maximálně 20 znaků', 20);
        $form->addText('email1', '*E-mail na 1. člena:')->setRequired('Zadejte prosím e-mail.')->addRule(UI\Form::EMAIL, 'E-mail není ve správném tvaru.')->addRule(UI\Form::MAX_LENGTH, 'E-mail prvního člena může mít maximálně 255 znaků', 255);
        $form->addText('email2', 'Záložní e-mail:')->setRequired(false)->addRule(UI\Form::EMAIL, 'Záložní-mail není ve správném tvaru.')->addRule(UI\Form::MAX_LENGTH, 'E-mail druhého člena může mít maximálně 255 znaků', 255);
        $form->addText('captcha', 'Počet dílků puzzlíku na logu Palapeli:');
        $form->addSubmit('login', 'REGISTROVAT');
        $form->addHidden('year', $this->selectedYear);
        $form->onSuccess[] = [$this, 'registrationFormSucceeded'];
        return $form;
    }

    public function registrationFormSucceeded(UI\Form $form, array $values)
    {
        $this->teamsModel->setYear($values['year']);
        $this->yearsModel->setYear($values['year']);

        $takenNames = $this->teamsModel->getTakenNames();

        if ($values['captcha'] != 4) {
            $form->addError(Nette\Utils\Html::el('div', ['class' => 'flash info'])->setHtml('Nesprávně vyplněná kontrolní otázka.'));
            return;
        }

        if (in_array($values['name'], $takenNames)) {
            $form->addError(Nette\Utils\Html::el('div', ['class' => 'flash info'])->setHtml('Tým s tímto jménem již existuje. Pokud se jedná o Váš tým, můžete se do aktuálního ročníku přihlásit v sekci <a href="/team">Přihlášení</a>. Pokud si nepamatujete heslo ani e-mail, na který byste si nechali vygenerovat nové heslo, kontaktujte prosím organizátory na e-mailu organizatori@palapeli.cz. Pokud se nejedná o Váš tým, použijte prosím jiné jméno týmu.'));
        } else {
            $password = hash('ripemd160', $values['password']);

            foreach($values as &$value) {
                $value = strip_tags($value);
            }

            $teamId = $this->teamsModel->addNewTeam($values['name'], $password, $values['phone1'], $values['phone2'], $values['email1'], $values['email2']);

            $this->teamsModel->registerTeam($teamId, $values['member1'], $values['member2'], $values['member3'], $values['member4']);

            $teamsCount = $this->teamsModel->getTeamsCount();
            $teamLimit = $this->yearsModel->getTeamLimit();
            if($teamLimit && $teamsCount > $teamLimit) {
                $this->flashMessage('Tým ' . $this->session->getSection('team')->teamName . ' byl úspěšně zaregistrován do aktuálního ročníku jako náhradní. Již je totiž naplněn limit počtu týmů, které se mohou hry zúčastnit. Jakmile se pro vás uvolní místo, ozveme se vám.', 'info');
            } else {
                $this->flashMessage('Tým ' . $values ['name'] . ' byl úspěšně zaregistrován a přihlášen.', 'success');
            }
            $this->session->getSection('team')->teamId = $teamId;
            $this->session->getSection('team')->teamName = $values['name'];
            $this->redirect('Team:edit');
        }
    }

    public function loginFormSucceeded(UI\Form $form, array $values)
    {
        $teamId = $this->teamsModel->getTeamId($values['name']);

        $this->yearsModel->setYear($values['year']);
        $this->teamsModel->setYear($values['year']);

        if (!isset($teamId)) {
            $form->addError(Nette\Utils\Html::el('div', ['class' => 'flash info'])->setHtml('Tým se zadaným názvem neexistuje.'));
        } else {
            $password = hash('ripemd160', $values['password']);
            if (!$this->teamsModel->checkPassword($teamId, $password)) {
                $form->addError(Nette\Utils\Html::el('div', ['class' => 'flash info'])->setHtml('Nesprávně zadané heslo.'));
            } else {
                if($teamId == self::ORG_TEAM_ID) {
                    $this->flashMessage('Organizátorský tým byl úspěšně přihlášen.', 'success');
                    $this->session->getSection('team')->teamId = self::ORG_TEAM_ID;
                    $this->session->getSection('team')->teamName = $values['name'];
                    $this->redirect('Administration:');
                } else {
                    $registered = $this->teamsModel->isTeamRegistered($teamId);

                    $currentYearNumber = $this->yearsModel->getCurrentYearNumber();

                    if (!$registered && $values['year'] == $currentYearNumber) {
                        if (!$this->yearsModel->isRegistrationOpen()) {
                            $this->flashMessage('Tým ' . $values ['name'] . ' byl úspěšně přihlášen. Registrace do aktuálního ročníku je však již uzavřena. Pro úpravu údajů z jiných ročníků prosíme vyberte jiný ročník.', 'success');
                            $this->session->getSection('team')->teamId = $teamId;
                            $this->session->getSection('team')->teamName = $values['name'];
                            $this->redirect('Info:');
                        } else {
                            $teamData = $this->teamsModel->getMostRecentTeamYearData($teamId);

                            if (isset($teamData)) {
                                $this->teamsModel->registerTeam($teamId, $teamData->member1, $teamData->member2, $teamData->member3, $teamData->member4);
                            } else {
                                $this->teamsModel->registerTeam($teamId);
                            }

                            $this->session->getSection('team')->teamId = $teamId;
                            $this->session->getSection('team')->teamName = $values['name'];

                            $teamsCount = $this->teamsModel->getTeamsCount();
                            $teamLimit = $this->yearsModel->getTeamLimit();
                            if($teamLimit && $teamsCount > $teamLimit) {
                                $this->flashMessage('Tým ' . $this->session->getSection('team')->teamName . ' byl úspěšně zaregistrován do aktuálního ročníku jako náhradní. Již je totiž naplněn limit počtu týmů, které se mohou hry zúčastnit. Jakmile se pro vás uvolní místo, ozveme se vám.', 'info');
                            } else {
                                $this->flashMessage('Tým ' . $this->session->getSection('team')->teamName . ' byl úspěšně zaregistrován do aktuálního ročníku.', 'success');
                            }
                            $this->redirect('Team:edit');
                        }
                    } elseif (!$registered && $values['year'] != $currentYearNumber) {
                        $this->flashMessage('Tým ' . $values ['name'] . ' byl úspěšně přihlášen. ' . $values['year'] . '. ročníku se však neúčastnil, pro úpravu údajů z jiných ročníků prosíme vyberte jiný ročník.', 'success');
                        $this->session->getSection('team')->teamId = $teamId;
                        $this->session->getSection('team')->teamName = $values['name'];
                        $this->redirect('Info:');
                    } else {
                        $this->flashMessage('Tým ' . $values ['name'] . ' byl úspěšně přihlášen.', 'success');
                        $this->session->getSection('team')->teamId = $teamId;
                        $this->session->getSection('team')->teamName = $values['name'];
                        $this->redirect('Team:edit');
                    }
                }
            }
        }
    }

    protected function createComponentEditForm()
    {
        if(!$this->selectedYear) {
            parent::getYearData();
        }

        $this->teamsModel->setYear($this->selectedYear);

        $teamId = $this->session->getSection('team')->teamId;

        $data = $this->teamsModel->getTeamData($teamId);

        $form = new UI\Form;
        $form->addText('name', 'Jméno týmu:')->setDisabled()->setDefaultValue($data->name);
        $form->addPassword('password', 'Nové heslo:')->setRequired(false)->addRule(UI\Form::MAX_LENGTH, 'Heslo může mít maximálně 255 znaků', 255);
        $form->addPassword('passwordVerify', 'Nové heslo znovu:')->setRequired(false)->addRule(UI\Form::EQUAL, 'Hesla se neshodují', $form['password']);
        $form->addText('member1', '*První člen týmu:')->setRequired('Zadejte prosím jméno prvního člena týmu.')->setDefaultValue($data->member1)->addRule(UI\Form::MAX_LENGTH, 'Jméno prvního člena může mít maximálně 255 znaků', 255);
        $form->addText('member2', 'Druhý člen týmu:')->setDefaultValue($data->member2)->setRequired(false)->addRule(UI\Form::MAX_LENGTH, 'Jméno druhého člena může mít maximálně 255 znaků', 255);
        $form->addText('member3', 'Třetí člen týmu:')->setDefaultValue($data->member3)->setRequired(false)->addRule(UI\Form::MAX_LENGTH, 'Jméno třetího člena může mít maximálně 255 znaků', 255);
        $form->addText('member4', 'Čtvrtý člen týmu:')->setDefaultValue($data->member4)->setRequired(false)->addRule(UI\Form::MAX_LENGTH, 'Jméno čtvrtého člena může mít maximálně 255 znaků', 255);
        $form->addText('phone1', '*Telefon na 1. člena:')->setRequired('Zadejte prosím telefon.')->setDefaultValue($data->phone1)->addRule(UI\Form::MAX_LENGTH, 'Telefon prvního člena může mít maximálně 20 znaků', 20);
        $form->addText('phone2', 'Záložní telefon:')->setDefaultValue($data->phone2)->setRequired(false)->addRule(UI\Form::MAX_LENGTH, 'Telefon druhého člena může mít maximálně 20 znaků', 20);
        $form->addText('email1', '*E-mail na 1. člena:')->setRequired('Zadejte prosím e-mail.')->addRule(UI\Form::EMAIL, 'E-mail není ve správném tvaru.')->setDefaultValue($data->email1)->addRule(UI\Form::MAX_LENGTH, 'E-mail prvního člena může mít maximálně 255 znaků', 255);
        $form->addText('email2', 'Záložní e-mail:')->setRequired(false)->addRule(UI\Form::EMAIL, 'Záložní-mail není ve správném tvaru.')->setRequired(false)->setDefaultValue($data->email2)->addRule(UI\Form::MAX_LENGTH, 'E-mail druhého člena může mít maximálně 255 znaků', 255);
        $form->addHidden('year', $this->selectedYear);
        $form->addSubmit('edit', 'ZMĚNIT ÚDAJE');
        $form->onSuccess[] = [$this, 'editFormSucceeded'];
        return $form;
    }

    public  function editFormSucceeded(UI\Form $form, array $values) {
        $this->teamsModel->setYear($values['year']);

        $teamId = $this->session->getSection('team')->teamId;

        $password = null;
        if(strlen($values['password']) > 0) {
            $password = hash('ripemd160', $values['password']);
            $this->teamsModel->updatePassword($teamId, $password);
        }

        foreach($values as &$value) {
            $value = strip_tags($value);
        }

        $this->teamsModel->updateTeamMembers($teamId, $values['member1'], $values['member2'], $values['member3'], $values['member4']);
        $this->teamsModel->updateTeamContactInfo($teamId, $values['email1'], $values['email2'], $values['phone1'], $values['phone2']);


        $this->flashMessage('Údaje o vašem týmu byly úspěšně změněny.', 'success');
        $this->redirect('this');
    }

    protected function createComponentCancelForm()
    {
        $teamName = $this->session->getSection('team')->teamName;
        $form = new UI\Form;
        $form->getElementPrototype()->setAttribute('class', 'center');
        $form->addCheckbox('cancelConfirm', 'Skutečně chci zrušit účast týmu ' . $teamName)->setRequired('Potvrďte prosím zrušení účasti zaškrtnutím checkboxu.')->setAttribute('class', 'nowrap');
        $form->addSubmit('cancel', 'ZRUŠIT ÚČAST')->setAttribute('class', 'autoWidth');
        $form->addHidden('year', $this->selectedYear);
        $form->onSuccess[] = [$this, 'cancelFormSucceeded'];
        return $form;
    }

    public  function cancelFormSucceeded(UI\Form $form, array $values) {
        $teamId = $this->session->getSection('team')->teamId;

        $this->teamsModel->setYear($values['year']);
        $this->yearsModel->setYear($values['year']);

        $paid = $this->teamsModel->getTeamPaymentStatus($teamId);

        $mail = new Message;
        $mail->setFrom('Palapeli Web <organizatori@palapeli.cz>')
            ->addTo('organizatori@palapeli.cz')
            ->setSubject('Odhlášení týmu ' . $this->session->getSection('team')->teamName)
            ->setBody("Odhlásil se tým " . $this->session->getSection("team")->teamName . " s id " . $this->session->getSection("team")->teamId . "\nStartovné " . ($paid == 1 ? "už bylo" : "ještě nebylo") . " zaplacené.\n\nAutomaticky generovaná zpráva z webu.");
        $teamsCount = $this->teamsModel->getTeamsCount();
        $mailer = new SendmailMailer;
        $mailer->send($mail);

        $this->teamsModel->deleteTeamRegistration($teamId);
        
        $playingTeams = $this->teamsModel->getPlayingTeamsIds();
        $teamLimit = $this->yearsModel->getTeamLimit();
        if($teamLimit && $teamsCount > $teamLimit && in_array($this->session->getSection("team")->teamId, $playingTeams)) {
            $newTeam = $this->teamsModel->getFirstStandby();

            if(isset($newTeam)) {

                $mail = new Message;

                $mail->setFrom(self::ORG_MAIL_FORMAT)
                    ->addTo($newTeam->email1)
                    ->addBcc('organizatori@palapeli.cz')
                    ->addReplyTo('organizatori@palapeli.cz')
                    ->setSubject('Palapeli: Uvolnění místa na hře pro váš tým ' . $newTeam->name)
                    ->setBody("Odhlásil se jeden ze zaregistrovaných týmů, čímž se uvolnilo místo pro vás. Ozvěte se nám prosím co nejrychleji, zda máte o účast na hře stále zájem. Pokud jste již s účastí nepočítali a zúčastnit se nechcete, zrušte prosím v autentizované části na webu svoji účast na hře.\n\nDěkujeme a doufáme, že vás uvidíme na hře!\nVaši organizátoři\n\nAutomaticky generovaná zpráva z webu.");

                if(strlen($newTeam->email2) > 0) {
                    $mail->addTo($newTeam->email2);
                }
                $mailer->send($mail);
            }
        }

        $this->flashMessage('Účast týmu na hře byla úspěšně zrušena.', 'success');
        unset($this->session->getSection('team')->teamName);
        unset($this->session->getSection('team')->teamId);
        $this->redirect('Info:');
    }

    protected function createComponentForgottenPasswordForm()
    {
        $form = new UI\Form;
        $form->getElementPrototype()->setAttribute('class', 'center');
        $form->addText('name', 'Název týmu:')->setRequired('Zadejte prosím název týmu, pro který se má vygenerovat nové heslo.');
        $form->addSubmit('send', 'ODESLAT EMAIL')->setAttribute('class', 'autoWidth');
        $form->onSuccess[] = [$this, 'forgottenPasswordFormSucceeded'];
        return $form;
    }

    public  function forgottenPasswordFormSucceeded(UI\Form $form, array $values) {
        $data = $this->teamsModel->getEmailsByName($values['name']);

        if(!isset($data)) {
            $form->addError(Nette\Utils\Html::el('div', ['class' => 'flash info'])->setHtml('Tým se zadaným názvem neexistuje.'));
        } else {
            srand(time());
            $newPassword = '';
            $newPassword .= chr(rand(97, 122));
            $newPassword .= chr(rand(97, 122));
            $newPassword .= chr(rand(97, 122));
            $newPassword .= chr(rand(97, 122));
            $newPassword .= chr(rand(97, 122));
            $newPassword .= chr(rand(97, 122));
            $newPassword .= chr(rand(97, 122));

            $newPasswordHash = hash('ripemd160', $newPassword);

            $this->teamsModel->updatePassword($data->id, $newPasswordHash);

            $mail = new Message;
            $mail->setFrom(self::ORG_MAIL_FORMAT)
                ->addReplyTo(self::ORG_MAIL_FORMAT)
                ->addTo($data->email1)
                ->setSubject('Palapeli - změna hesla pro tým ' . $values['name'])
                ->sethTMLBody("Ahoj!<br>Někdo (pravděpodobně vy) požádal na stránkách šifrovací hry Palapeli o změnu hesla týmu " . $values['name'] . ". Bylo vám vygenerováno toto nové heslo: \"" . $newPassword . "\" (bez uvozovek). Pomocí hesla se můžete přihlásit do autentizované sekce <a href='http://palapeli.cz/team'>na stránkách Palapeli</a>.<br><br>Těšíme se na vás na hře,<br>vaši organizátoři.");
            if (strlen($data->email2) > 0) {
                $mail->addTo($data->email2);
            }

            $mailer = new SendmailMailer;
            $mailer->send($mail);

            $this->flashMessage('Heslo bylo úspěšně změněno a zasláno na e-maily uvedené u týmu ' . $values['name'], 'success');
            $this->redirect('Team:');
        }
    }
}
