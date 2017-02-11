<?php
namespace App\Presenters;

use Nette;
use Nette\Application\UI;
use Nette\Mail\Message;
use Nette\Mail\SendmailMailer;
use App\Models\TeamsModel;


class TeamPresenter extends BasePresenter
{
    /** @var Nette\Database\Context */
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function renderRegistration()
    {
        parent::render();
        $this->prepareHeading('Registrace');

        $teamsRegistered = TeamsModel::getTeamsCount($this->database);

        $this->template->displayStandbyWarning = $teamsRegistered >= self::TEAM_LIMIT && self::TEAM_LIMIT > 0;
    }

    public function renderDefault()
    {
        parent::render();
        $this->prepareHeading('Přihlášení');
    }

    public function renderCancel()
    {
        parent::render();
        $this->prepareHeading('Zrušení účasti');

        if(isset($this->session->getSection('team')->teamId)) {

            $data = $this->database->query('
            SELECT 1
            FROM teamsyear ty
            WHERE team_id  = ? AND year = ?
        ', $this->session->getSection('team')->teamId, $this->selectedYear)->fetchAll();

            $this->template->registered = count($data);
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

        if(isset($this->session->getSection('team')->teamId)) {

            $data = $this->database->query('
            SELECT 1
            FROM teamsyear ty
            WHERE team_id  = ? AND year = ?
        ', $this->session->getSection('team')->teamId, $this->selectedYear)->fetchAll();

            $this->template->registered = count($data);
        } else {
            $this->template->registered = false;
        }
    }

    public function renderPayment()
    {
        parent::render();
        $this->prepareHeading('Platba startovného');

        if(isset($this->session->getSection('team')->teamId)) {

        $data = $this->database->query('
            SELECT paid
            FROM teamsyear ty
            WHERE team_id  = ? AND year = ?
        ', $this->session->getSection('team')->teamId, self::CURRENT_YEAR)->fetchAll();

        $registered = $this->database->query('
            SELECT registered
            FROM teamsyear ty
            WHERE team_id  = ? AND year = ?
        ', $this->session->getSection('team')->teamId, self::CURRENT_YEAR)->fetchField();

            $this->template->registered = isset($registered);
            if(isset($registered)) {

                $order = $this->database->query('
                    SELECT COUNT(team_id)
                    FROM teamsyear
                    WHERE registered < ? AND year = ?
                ', $registered, self::CURRENT_YEAR)->fetchField();

                $this->template->isSubstitute = $order >= self::TEAM_LIMIT;

                $this->template->paid = $data[0]->paid;
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
        if(!$this->isRegistrationOpen()) {
            $this->flashMessage('Registrace do ' . $this->selectedYear . '. ročníku je uzavřena.');
            $this->redirect('Info:');
        }
        if(isset($this->session->getSection('team')->teamId)) {
            $teamId = $this->session->getSection('team')->teamId;
            $isInSelectedYear = $this->database->query('
                    SELECT 1
                    FROM teamsyear
                    WHERE team_id = ? AND year = ?
                ', $teamId, self::CURRENT_YEAR)->fetchAll();

            if(!count($isInSelectedYear)) {
                if (count($isInSelectedYear) == 0) {
                    $teamData = $this->database->query('
                      SELECT *
                      FROM teamsyear
                      WHERE team_id = ' . $teamId . '
                      ORDER BY year DESC'
                    )->fetchAll();

                    if (count($teamData) != 0) {
                        $teamData = $teamData[0];
                        $this->database->query('
                            INSERT INTO teamsyear (team_id, year, paid, member1, member2, member3, member4, registered) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ', $teamId, self::CURRENT_YEAR, 0, $teamData->member1, $teamData->member2, $teamData->member3, $teamData->member4, date('Y-m-d H:i:s', time()));

                    } else {
                        $this->database->query('
                            INSERT INTO teamsyear (team_id, year, paid, member1, member2, member3, member4, registered) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ', $teamId, self::CURRENT_YEAR, 0, "", null, null, null, date('Y-m-d H:i:s', time()));
                    }
                    $teamsCount = TeamsModel::getTeamsCount($this->database);

                    if($teamsCount > self::TEAM_LIMIT) {
                        $this->flashMessage('Tým ' . $this->session->getSection('team')->teamName . ' byl úspěšně zaregistrován do aktuálního ročníku jako náhradní. Již je totiž naplněn limit počtu týmů, které se mohou hry zúčastnit. Jakmile se pro vás uvolní místo, ozveme se vám.', 'info');
                    } else {
                        $this->flashMessage('Tým ' . $this->session->getSection('team')->teamName . ' byl úspěšně zaregistrován do aktuálního ročníku.', 'success');
                    }

                    $this->redirect('Team:edit');
                }
            } else {
                $this->flashMessage('Do ' . self::CURRENT_YEAR . '. ročníku už jste zaregistrováni.', 'info');
                $this->redirect('Team:edit');
            }
        } else {
            $this->flashMessage('Nejste přihlášení.');
            $this->redirect('Info:');
        }
    }

    protected function createComponentLoginForm()
    {
        $form = new UI\Form;
        $form->addText('name', 'Jméno týmu:')->setRequired('Zadejte prosím jméno týmu.');
        $form->addPassword('password', 'Heslo:')->setRequired('Zadejte prosím heslo.');
        $form->addSubmit('login', 'PŘIHLÁSIT');
        $form->onSuccess[] = [$this, 'loginFormSucceeded'];
        return $form;
    }

    protected function createComponentRegistrationForm()
    {
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
        $form->addSubmit('login', 'REGISTROVAT');
        $form->onSuccess[] = [$this, 'registrationFormSucceeded'];
        return $form;
    }

    public function registrationFormSucceeded(UI\Form $form, array $values)
    {
        $takenNames = array_keys($this->database->query('
            SELECT name
            FROM teams'
        )->fetchAssoc('name'));

        if (in_array($values['name'], $takenNames)) {
            $form->addError(Nette\Utils\Html::el('div', ['class' => 'flash info'])->setHtml('Tým s tímto jménem již existuje. Pokud se jedná o Váš tým, můžete se do aktuálního ročníku přihlásit v sekci <a href="/team">Přihlášení</a>. Pokud si nepamatujete heslo ani e-mail, na který byste si nechali vygenerovat nové heslo, kontaktujte prosím organizátory na e-mailu organizatori@palapeli.cz. Pokud se nejedná o Váš tým, použijte prosím jiné jméno týmu.'));
        } else {
            $password = hash('ripemd160', $values['password']);

            foreach($values as &$value) {
                $value = strip_tags($value);
            }

            $this->database->query('
                INSERT INTO teams (name, password, phone1, phone2, email1, email2) VALUES
                (?,?,?,?,?,?)',$values['name'], $password, $values['phone1'], $values['phone2'], $values['email1'], $values['email2']);

            $teamId = $this->database->getInsertId();
            $year = self::CURRENT_YEAR;
            $registered = date('Y-m-d H:i:s', time());
            $this->database->query('
                INSERT INTO teamsyear (team_id, year, paid, member1, member2, member3, member4, registered) VALUES
                (?,?,?,?,?,?,?,?)', $teamId, $year, 0, $values['member1'], $values['member2'], $values['member3'], $values['member4'], $registered);

            $teamsCount = TeamsModel::getTeamsCount($this->database);

            if($teamsCount > self::TEAM_LIMIT) {
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
        if(!$this->selectedYear) {
            parent::getYearData();
        }
        $password = hash('ripemd160', $values['password']);

        $teamId = array_keys($this->database->query('
            SELECT id
            FROM teams
            WHERE name = ?', $values['name']
        )->fetchAssoc('id'));

        if (count($teamId) == 0) {
            $form->addError(Nette\Utils\Html::el('div', ['class' => 'flash info'])->setHtml('Tým se zadaným názvem neexistuje.'));
        } else {
            $teamId = $teamId[0];
            $teamData = $this->database->query('
                SELECT 1
                FROM teams
                WHERE password = ? AND id = ?',
                $password, $teamId
            )->fetchAll();

            if (count($teamData) == 0) {
                $form->addError(Nette\Utils\Html::el('div', ['class' => 'flash info'])->setHtml('Nesprávně zadané heslo.'));
            } else {
                if($teamId == self::ORG_TEAM_ID) {
                    $this->flashMessage('Organizátorský tým byl úspěšně přihlášen.', 'success');
                    $this->session->getSection('team')->teamId = self::ORG_TEAM_ID;
                    $this->session->getSection('team')->teamName = $values['name'];
                    $this->redirect('Administration:');
                } else {

                    $isInSelectedYear = $this->database->query('
                    SELECT 1
                    FROM teamsyear
                    WHERE team_id = ? AND year = ?
                ', $teamId, $this->selectedYear)->fetchAll();

                    if (!count($isInSelectedYear) && $this->selectedYear == self::CURRENT_YEAR) {
                        if (!$this->isRegistrationOpen()) {
                            $this->flashMessage('Tým ' . $values ['name'] . ' byl úspěšně přihlášen.', 'success');
                            $this->session->getSection('team')->teamId = $teamId;
                            $this->session->getSection('team')->teamName = $values['name'];
                            $this->redirect('Info:');
                        } else {
                            if (count($isInSelectedYear) == 0) {
                                $teamData = $this->database->query('
                                      SELECT *
                                      FROM teamsyear
                                      WHERE team_id = ' . $teamId . '
                                      ORDER BY year DESC'
                                )->fetchAll();

                                if (count($teamData) != 0) {
                                    $teamData = $teamData[0];
                                    $this->database->query('
                            INSERT INTO teamsyear (team_id, year, paid, member1, member2, member3, member4, registered) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ', $teamId, self::CURRENT_YEAR, 0, $teamData->member1, $teamData->member2, $teamData->member3, $teamData->member4, date('Y-m-d H:i:s', time()));

                                } else {
                                    $this->database->query('
                            INSERT INTO teamsyear (team_id, year, paid, member1, member2, member3, member4, registered) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ', $teamId, self::CURRENT_YEAR, 0, "", null, null, null, date('Y-m-d H:i:s', time()));
                                }
                                $this->flashMessage('Tým ' . $values ['name'] . ' byl úspěšně zaregistrován do aktuálního ročníku a přihlášen.', 'success');
                                $this->session->getSection('team')->teamId = $teamId;
                                $this->session->getSection('team')->teamName = $values['name'];
                                $this->redirect('Team:edit');
                            }
                        }
                    } elseif (!count($isInSelectedYear) && $this->selectedYear != self::CURRENT_YEAR) {
                        $this->flashMessage('Tým ' . $values ['name'] . ' byl úspěšně přihlášen. ' . $this->selectedYear . '. ročníku se však neúčastnil, pro úpravu údajů z jiných ročníků prosíme vyberte jiný ročník.', 'success');
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

        $teamId = $this->session->getSection('team')->teamId;

        $data = $this->database->query('
            SELECT *
            FROM teams t
            LEFT JOIN teamsyear ty ON t.id = ty.team_id AND ty.year = ?
            WHERE t.id = ?
        ', $this->selectedYear, $teamId)->fetchAll();

        $data = $data[0];

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
        $form->addSubmit('edit', 'ZMĚNIT ÚDAJE');
        $form->onSuccess[] = [$this, 'editFormSucceeded'];
        return $form;
    }

    public  function editFormSucceeded(UI\Form $form, array $values) {
        if(!$this->selectedYear) {
            parent::getYearData();
        }

        $teamId = $this->session->getSection('team')->teamId;

        $password = null;
        if(strlen($values['password']) > 0) {
            $password = hash('ripemd160', $values['password']);
            $this->database->query('UPDATE teams SET password = ? WHERE id = ?', $password, $teamId);
        }

        foreach($values as &$value) {
            $value = strip_tags($value);
        }

        $this->database->query('UPDATE teamsyear SET member1 = ?, member2 = ?, member3 = ?, member4 = ? WHERE team_id = ? AND year  =?', $values['member1'], $values['member2'], $values['member3'], $values['member4'], $teamId, $this->selectedYear);
        $this->database->query('UPDATE teams SET email1 = ?, email2 = ?, phone1 = ?, phone2 = ? WHERE id = ?', $values['email1'], $values['email2'], $values['phone1'], $values['phone2'], $teamId);


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
        $form->onSuccess[] = [$this, 'cancelFormSucceeded'];
        return $form;
    }

    public  function cancelFormSucceeded(UI\Form $form, array $values) {

        $teamId = $this->session->getSection('team')->teamId;
        $data = $this->database->query('
            SELECT paid
            FROM teamsyear
            WHERE year = ? AND team_id = ?
        ', self::CURRENT_YEAR, $teamId)->fetchAll();

        $mail = new Message;
        $mail->setFrom('Palapeli Web <organizatori@palapeli.cz>')
            ->addTo('organizatori@palapeli.cz')
            ->setSubject('Odhlášení týmu ' . $this->session->getSection('team')->teamName)
            ->setBody("Odhlásil se tým " . $this->session->getSection("team")->teamName . " s id " . $this->session->getSection("team")->teamId . "\nStartovné " . ($data[0]["paid"] ? "už bylo" : "ještě nebylo") . " zaplacené.\n\nAutomaticky generovaná zpráva z webu.");

        $teamsCount = TeamsModel::getTeamsCount($this->database);

        $playingTeams = TeamsModel::getPlayingTeamsIds($this->database);

        $this->database->query('
            DELETE
            FROM teamsyear
            WHERE year = ? AND team_id = ?
        ', self::CURRENT_YEAR, $teamId);

        $mailer = new SendmailMailer;
        $mailer->send($mail);

        if($teamsCount > self::TEAM_LIMIT && in_array($this->session->getSection("team")->teamId, $playingTeams)) {
            $newTeam = TeamsModel::getFirstStandby($this->database);

            if(isset($newTeam[0])) {

                $mail = new Message;

                if(strlen($newTeam[0]->email2) > 0) {
                    $mail->setFrom('Palapeli Web <organizatori@palapeli.cz>')
                        ->addTo($newTeam[0]->email1)
                        ->addTo($newTeam[0]->email2)
                        ->addBcc('organizatori@palapeli.cz')
                        ->addReplyTo('organizatori@palapeli.cz')
                        ->setSubject('Palapeli: Uvolnění místa na hře pro váš tým ' . $newTeam[0]->name)
                        ->setBody("Odhlásil se jeden ze zaregistrovaných týmů, čímž se uvolnilo místo pro vás. Ozvěte se nám prosím co nejrychleji, zda máte o účast na hře stále zájem. Pokud jste již s účastí nepočítali a zúčastnit se nechcete, zrušte prosím v autentizované části na webu svoji účast na hře.\n\nDěkujeme a doufáme, že vás uvidíme na hře!\nVaši organizátoři\n\nAutomaticky generovaná zpráva z webu.");
                } else {
                    $mail->setFrom('Palapeli Web <organizatori@palapeli.cz>')
                        ->addTo($newTeam[0]->email1)
                        ->addBcc('organizatori@palapeli.cz')
                        ->addReplyTo('organizatori@palapeli.cz')
                        ->setSubject('Palapeli: Uvolnění místa na hře pro váš tým ' . $newTeam[0]->name)
                        ->setBody("Odhlásil se jeden ze zaregistrovaných týmů, čímž se uvolnilo místo pro vás. Ozvěte se nám prosím co nejrychleji, zda máte o účast na hře stále zájem. Pokud jste již s účastí nepočítali a zúčastnit se nechcete, zrušte prosím v autentizované části na webu svoji účast na hře.\n\nDěkujeme a doufáme, že vás uvidíme na hře!\nVaši organizátoři\n\nAutomaticky generovaná zpráva z webu.");
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
        $data = $this->database->query('
            SELECT email1, email2
            FROM teams
            WHERE name = ?
        ', $values['name']
        )->fetchAll();

        if(count($data) == 0) {
            $form->addError(Nette\Utils\Html::el('div', ['class' => 'flash info'])->setHtml('Tým se zadaným názvem neexistuje.'));
        } else {
            $data = $data[0];
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

            $this->database->query('
            UPDATE teams SET password = ? WHERE name = ?
        ', $newPasswordHash, $values['name']);

            $mail = new Message;
            $mail->setFrom('Palapeli Web <organizatori@palapeli.cz>')
                ->addReplyTo('Palapeli Web <organizatori@palapeli.cz>')
                ->addTo($data['email1'])
                ->setSubject('Palapeli - změna hesla pro tým ' . $values['name'])
                ->sethTMLBody("Ahoj!<br>Někdo (pravděpodobně vy) požádal na stránkách šifrovací hry Palapeli o změnu hesla týmu " . $values['name'] . ". Bylo vám vygenerováno toto nové heslo: \"" . $newPassword . "\" (bez uvozovek). Pomocí hesla se můžete přihlásit do autentizované sekce <a href='http://palapeli.cz/team'>na stránkách Palapeli</a>.<br><br>Těšíme se na vás na hře,<br>vaši organizátoři.");
            if (isset($data['email2']) && strlen($data['email2']) > 0) {
                $mail->addTo($data['email2']);
            }

            $mailer = new SendmailMailer;
            $mailer->send($mail);

            $this->flashMessage('Heslo bylo úspěšně změněno a zasláno na e-maily uvedené u týmu ' . $values['name'], 'success');
            $this->redirect('Team:');
        }
    }

}