<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Models\PasswordHasher;
use App\Models\PaymentQrCodeGenerator;
use App\Models\PaymentStatus;
use App\Models\TeamsModel;
use App\Utils\AppConstants;
use App\Utils\Utils;
use Nette\Application\UI\Form;
use Nette\Mail\Message;
use Nette\Utils\Html;


/**
 * Section "Tým": login, registration, editing team data, entry fee payment, cancelling the
 * participation and forgotten password.
 *
 * Teams have a permanent account (table teams) and register to individual editions
 * (table teamsyear). Organizers log in as the special team AppConstants::OrgTeamId.
 */
final class TeamPresenter extends BasePresenter
{
	private const StandbyRegisteredMessage = 'Tým %s byl úspěšně zaregistrován do aktuálního ročníku jako náhradní. Již je totiž naplněn limit počtu týmů, které se mohou hry zúčastnit. Jakmile se pro vás uvolní místo, ozveme se vám.';
	private const GdprMessage = 'Registruješ tým lidí a tím nám dáváš jejich jméno, příjmení, telefony a e-maily, tak jim to prosím alespoň řekni. My za to slíbíme, že je nezneužijeme jinak, než v souvislosti se hrou. Data od nás tahají šifrovačky.cz a statek.seslost.cz. Existuje GDPR, tam najdeš, co po nás můžeš chtít. Když se vám něco nebude líbit, tak se ozvěte orgům, Dark to s vámi vyřídí.';


	public function __construct(
		private readonly TeamsModel $teamsModel,
		private readonly PasswordHasher $passwordHasher,
		private readonly PaymentQrCodeGenerator $qrCodeGenerator,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$this->prepareHeading('Přihlášení');
		$this->template->isRegistrationOpen = $this->yearsModel->isRegistrationOpen($this->selectedYear);
	}


	public function renderRegistration(): void
	{
		$this->prepareHeading('Registrace');
		$year = $this->selectedYear;
		$teamLimit = $this->yearsModel->getTeamLimit($year);

		$this->template->hasRegistrationStarted = $this->yearsModel->hasRegistrationStarted($year);
		$this->template->isRegistrationOpen = $this->yearsModel->isRegistrationOpen($year);
		$this->template->registrationStart = $this->yearsModel->getRegistrationStart($year);
		$this->template->displayStandbyWarning = $teamLimit && $this->teamsModel->getTeamsCount($year) >= $teamLimit;
	}


	public function renderEdit(): void
	{
		$this->prepareHeading('Úprava údajů');
		$year = $this->selectedYear;

		$this->template->hasRegistrationStarted = $this->yearsModel->hasRegistrationStarted($year);
		$this->template->isRegistrationOpen = $this->yearsModel->isRegistrationOpen($year);
		$this->template->registered = $this->teamId !== null && $this->teamsModel->isTeamRegistered($this->teamId, $year);
	}


	public function renderCancel(): void
	{
		$this->prepareHeading('Zrušení účasti');
		$year = $this->selectedYear;

		$this->template->hasRegistrationStarted = $this->yearsModel->hasRegistrationStarted($year);
		$this->template->isRegistrationOpen = $this->yearsModel->isRegistrationOpen($year);
		$this->template->hasGameStarted = $this->yearsModel->hasGameStarted($year);
		$this->template->registered = $this->teamId !== null && $this->teamsModel->isTeamRegistered($this->teamId, $year);
	}


	public function renderPassword(): void
	{
		$this->prepareHeading('Zapomenuté heslo');
	}


	public function renderPayment(): void
	{
		$this->prepareHeading('Platba startovného');
		$year = $this->selectedYear;
		$template = $this->template;

		$template->registered = $this->teamId !== null && $this->teamsModel->isTeamRegistered($this->teamId, $year);
		$template->paid = null; // the team is not expected to pay
		$template->isSubstitute = false;
		$template->QRCode = null;
		$template->variableSymbol = null;
		$template->yearData = $yearData = $this->yearsModel->getYearData($year);

		if (!$template->registered || $this->teamId === null) {
			return;
		}

		$teamLimit = $this->yearsModel->getTeamLimit($year);
		$template->isSubstitute = $teamLimit && $this->teamsModel->getTeamRegistrationOrder($this->teamId, $year) >= $teamLimit;
		$template->paid = $this->teamsModel->getTeamPaymentStatus($this->teamId, $year);
		$template->variableSymbol = $variableSymbol = $this->selectedCalendarYear . $this->teamId;

		if ($yearData?->entry_fee_account) {
			$template->QRCode = $this->qrCodeGenerator->createDataUri($this->qrCodeGenerator->createPaymentString(
				account: $yearData->entry_fee_account,
				amount: (int) $yearData->entry_fee,
				variableSymbol: $variableSymbol,
				message: 'Palapeli ' . $this->selectedCalendarYear . ' ' . $this->teamsModel->getTeamName($this->teamId),
			));
		}
	}


	public function actionLogout(): void
	{
		$this->teamSession->logOut();
		$this->teamId = null;
	}


	public function renderLogout(): void
	{
		$this->prepareHeading('Odhlášení');
	}


	/**
	 * Registers the logged-in team to the selected edition with members from its last participation.
	 */
	public function actionRegisterLogged(): void
	{
		$year = $this->selectedYear;

		if (!$this->yearsModel->isRegistrationOpen($year)) {
			$this->flashMessage('Registrace do ' . $year . '. ročníku je uzavřena.');
			$this->redirect('Info:');
		}

		if ($this->teamId === null) {
			$this->flashMessage('Nejste přihlášení.');
			$this->redirect('Info:');
		}

		if ($this->teamsModel->isTeamRegistered($this->teamId, $year)) {
			$this->flashMessage('Do ' . $this->yearsModel->getCurrentYearNumber() . '. ročníku už jste zaregistrováni.', 'info');
			$this->redirect('Team:edit');
		}

		$this->registerWithPreviousMembers($this->teamId, $year, (string) $this->teamSession->getTeamName());
		$this->flashMessage(self::GdprMessage, 'info');
		$this->redirect('Team:edit');
	}


	protected function createComponentLoginForm(): Form
	{
		$form = new Form;
		$form->addText('name', 'Jméno týmu:')->setRequired('Zadejte prosím jméno týmu.');
		$form->addPassword('password', 'Heslo:')->setRequired('Zadejte prosím heslo.');
		$form->addSubmit('login', 'PŘIHLÁSIT');
		$form->addHidden('year', $this->selectedYear);
		$form->onSuccess[] = $this->loginFormSucceeded(...);
		return $form;
	}


	/**
	 * @param array{name: string, password: string, year: string} $values
	 */
	private function loginFormSucceeded(Form $form, array $values): void
	{
		$name = $values['name'];
		$year = (int) $values['year'];
		$teamId = $this->teamsModel->getTeamId($name);

		if ($teamId === null) {
			$form->addError(self::formError('Tým se zadaným názvem neexistuje.'));
			return;
		}

		if (!$this->passwordHasher->verify($values['password'], $this->teamsModel->getPasswordHash($teamId))) {
			$form->addError(self::formError('Nesprávně zadané heslo.'));
			return;
		}

		$this->teamSession->logIn($teamId, $name);

		if ($teamId === AppConstants::OrgTeamId) {
			$this->flashMessage('Organizátorský tým byl úspěšně přihlášen.', 'success');
			$this->redirect('Administration:');
		}

		if ($this->teamsModel->isTeamRegistered($teamId, $year)) {
			$this->flashMessage('Tým ' . $name . ' byl úspěšně přihlášen.', 'success');
			$this->redirect('Team:edit');
		}

		if ($year !== $this->yearsModel->getCurrentYearNumber()) {
			$this->flashMessage('Tým ' . $name . ' byl úspěšně přihlášen. ' . $year . '. ročníku se však neúčastnil, pro úpravu údajů z jiných ročníků prosíme vyberte jiný ročník.', 'success');
			$this->redirect('Info:');
		}

		if (!$this->yearsModel->isRegistrationOpen($year)) {
			$this->flashMessage('Tým ' . $name . ' byl úspěšně přihlášen. Registrace do aktuálního ročníku je však již uzavřena. Pro úpravu údajů z jiných ročníků prosíme vyberte jiný ročník.', 'success');
			$this->redirect('Info:');
		}

		// logging in during the registration period registers the team to the current edition
		$this->registerWithPreviousMembers($teamId, $year, $name);
		$this->redirect('Team:edit');
	}


	protected function createComponentRegistrationForm(): Form
	{
		$form = new Form;
		$form->addText('name', '*Jméno týmu:')
			->setRequired('Zadejte prosím jméno týmu.')
			->addRule($form::MaxLength, 'Název týmu může mít maximálně 255 znaků', 255)
			->setHtmlAttribute('style', 'width:calc(100% - 10px)');
		$form->addPassword('password', '*Heslo:')
			->setRequired('Zadejte prosím heslo.')
			->addRule($form::MaxLength, 'Heslo může mít maximálně 255 znaků', 255);
		$form->addPassword('passwordVerify', '*Heslo znovu:')
			->setRequired('Zadejte prosím heslo ještě jednou pro kontrolu.')
			->addRule($form::Equal, 'Hesla se neshodují', $form['password']);
		$this->addMemberInputs($form);
		$this->addContactInputs($form);
		$form->addText('captcha', 'Počet dílků puzzlíku na logu Palapeli:');
		$form->addSubmit('login', 'REGISTROVAT');
		$form->addHidden('year', $this->selectedYear);
		$form->onSuccess[] = $this->registrationFormSucceeded(...);
		return $form;
	}


	/**
	 * @param array<string, string> $values
	 */
	private function registrationFormSucceeded(Form $form, array $values): void
	{
		$year = (int) $values['year'];

		if (trim($values['captcha']) !== (string) AppConstants::CaptchaAnswer) {
			$form->addError(self::formError('Nesprávně vyplněná kontrolní otázka.'));
			return;
		}

		if ($this->teamsModel->isNameTaken($values['name'])) {
			$form->addError(self::formError('Tým s tímto jménem již existuje. Pokud se jedná o Váš tým, můžete se do aktuálního ročníku přihlásit v sekci <a href="' . $this->link('Team:') . '">Přihlášení</a>. Pokud si nepamatujete heslo ani e-mail, na který byste si nechali vygenerovat nové heslo, kontaktujte prosím organizátory na e-mailu ' . AppConstants::OrgEmail . '. Pokud se nejedná o Váš tým, použijte prosím jiné jméno týmu.'));
			return;
		}

		$passwordHash = $this->passwordHasher->hash($values['password']);
		$values = Utils::stripTags($values);

		$teamId = $this->teamsModel->addNewTeam(
			$values['name'],
			$passwordHash,
			$values['phone1'],
			$values['phone2'],
			$values['email1'],
			$values['email2'],
		);
		$this->teamsModel->registerTeam($teamId, $year, $values['member1'], $values['member2'], $values['member3'], $values['member4']);

		if ($this->isOverTeamLimit($year)) {
			$this->flashMessage(sprintf(self::StandbyRegisteredMessage, $values['name']), 'info');
		} else {
			$this->flashMessage('Tým ' . $values['name'] . ' byl úspěšně zaregistrován a přihlášen.', 'success');
		}

		$this->teamSession->logIn($teamId, $values['name']);
		$this->redirect('Team:edit');
	}


	protected function createComponentEditForm(): Form
	{
		$data = $this->teamsModel->getTeamData($this->requireLoggedTeam(), $this->selectedYear);

		$form = new Form;
		$form->addText('name', 'Jméno týmu:')
			->setDisabled()
			->setDefaultValue($data?->name);
		$form->addPassword('password', 'Nové heslo:')
			->setRequired(false)
			->addRule($form::MaxLength, 'Heslo může mít maximálně 255 znaků', 255);
		$form->addPassword('passwordVerify', 'Nové heslo znovu:')
			->setRequired(false)
			->addRule($form::Equal, 'Hesla se neshodují', $form['password']);
		$this->addMemberInputs($form);
		$this->addContactInputs($form);
		$form->addHidden('year', $this->selectedYear);
		$form->addSubmit('edit', 'ZMĚNIT ÚDAJE');

		if ($data) {
			$form->setDefaults([
				'member1' => $data->member1,
				'member2' => $data->member2,
				'member3' => $data->member3,
				'member4' => $data->member4,
				'phone1' => $data->phone1,
				'phone2' => $data->phone2,
				'email1' => $data->email1,
				'email2' => $data->email2,
			]);
		}

		$form->onSuccess[] = $this->editFormSucceeded(...);
		return $form;
	}


	/**
	 * @param array<string, string> $values
	 */
	private function editFormSucceeded(Form $form, array $values): void
	{
		$teamId = $this->requireLoggedTeam();

		if ($values['password'] !== '') {
			$this->teamsModel->updatePassword($teamId, $this->passwordHasher->hash($values['password']));
		}

		$values = Utils::stripTags($values);
		$this->teamsModel->updateTeamMembers($teamId, (int) $values['year'], $values['member1'], $values['member2'], $values['member3'], $values['member4']);
		$this->teamsModel->updateTeamContactInfo($teamId, $values['email1'], $values['email2'], $values['phone1'], $values['phone2']);

		$this->flashMessage('Údaje o vašem týmu byly úspěšně změněny.', 'success');
		$this->redirect('this');
	}


	protected function createComponentCancelForm(): Form
	{
		$form = new Form;
		$form->getElementPrototype()->setAttribute('class', 'center');
		$form->addCheckbox('cancelConfirm', 'Skutečně chci zrušit účast týmu ' . $this->teamSession->getTeamName())
			->setRequired('Potvrďte prosím zrušení účasti zaškrtnutím checkboxu.')
			->setHtmlAttribute('class', 'nowrap');
		$form->addSubmit('cancel', 'ZRUŠIT ÚČAST')->setHtmlAttribute('class', 'autoWidth');
		$form->addHidden('year', $this->selectedYear);
		$form->onSuccess[] = $this->cancelFormSucceeded(...);
		return $form;
	}


	/**
	 * Cancels the registration, notifies the organizers and, when the team was playing (not
	 * a substitute), offers the free place to the first substitute team.
	 * @param array{cancelConfirm: bool, year: string} $values
	 */
	private function cancelFormSucceeded(Form $form, array $values): void
	{
		$teamId = $this->requireLoggedTeam();
		$teamName = (string) $this->teamSession->getTeamName();
		$year = (int) $values['year'];

		$paid = $this->teamsModel->getTeamPaymentStatus($teamId, $year);
		$wasPlaying = in_array($teamId, $this->teamsModel->getPlayingTeamsIds($year), strict: true);
		$teamsCount = $this->teamsModel->getTeamsCount($year);

		$this->mailer->send(new Message()
			->setFrom(AppConstants::OrgMailFrom)
			->addTo(AppConstants::OrgEmail)
			->setSubject('Odhlášení týmu ' . $teamName)
			->setBody(
				'Odhlásil se tým ' . $teamName . ' s id ' . $teamId
				. "\nStartovné " . ($paid === PaymentStatus::Paid ? 'už bylo' : 'ještě nebylo') . ' zaplacené.'
				. "\n\nAutomaticky generovaná zpráva z webu.",
			));

		$this->teamsModel->deleteTeamRegistration($teamId, $year);

		$teamLimit = $this->yearsModel->getTeamLimit($year);
		if ($teamLimit && $teamsCount > $teamLimit && $wasPlaying) {
			$this->notifyFirstStandby($year);
		}

		$this->flashMessage('Účast týmu na hře byla úspěšně zrušena.', 'success');
		$this->teamSession->logOut();
		$this->redirect('Info:');
	}


	private function notifyFirstStandby(int $year): void
	{
		$newTeam = $this->teamsModel->getFirstStandby($year);
		if ($newTeam === null) {
			return;
		}

		$mail = new Message()
			->setFrom(AppConstants::OrgMailFrom)
			->addTo($newTeam->email1)
			->addBcc(AppConstants::OrgEmail)
			->addReplyTo(AppConstants::OrgEmail)
			->setSubject('Palapeli: Uvolnění místa na hře pro váš tým ' . $newTeam->name)
			->setBody("Odhlásil se jeden ze zaregistrovaných týmů, čímž se uvolnilo místo pro vás. Ozvěte se nám prosím co nejrychleji, zda máte o účast na hře stále zájem. Pokud jste již s účastí nepočítali a zúčastnit se nechcete, zrušte prosím v autentizované části na webu svoji účast na hře.\n\nDěkujeme a doufáme, že vás uvidíme na hře!\nVaši organizátoři\n\nAutomaticky generovaná zpráva z webu.");

		if ($newTeam->email2 !== null && $newTeam->email2 !== '') {
			$mail->addTo($newTeam->email2);
		}

		$this->mailer->send($mail);
	}


	protected function createComponentForgottenPasswordForm(): Form
	{
		$form = new Form;
		$form->getElementPrototype()->setAttribute('class', 'center');
		$form->addText('name', 'Název týmu:')
			->setRequired('Zadejte prosím název týmu, pro který se má vygenerovat nové heslo.');
		$form->addSubmit('send', 'ODESLAT EMAIL')->setHtmlAttribute('class', 'autoWidth');
		$form->onSuccess[] = $this->forgottenPasswordFormSucceeded(...);
		return $form;
	}


	/**
	 * Generates a new random password and sends it to the team's e-mail addresses.
	 * @param array{name: string} $values
	 */
	private function forgottenPasswordFormSucceeded(Form $form, array $values): void
	{
		$name = $values['name'];
		$team = $this->teamsModel->getEmailsByName($name);

		if ($team === null) {
			$form->addError(self::formError('Tým se zadaným názvem neexistuje.'));
			return;
		}

		$newPassword = $this->passwordHasher->generatePassword();
		$this->teamsModel->updatePassword((int) $team->id, $this->passwordHasher->hash($newPassword));

		$escapedName = htmlspecialchars($name, ENT_QUOTES);
		$mail = new Message()
			->setFrom(AppConstants::OrgMailFrom)
			->addReplyTo(AppConstants::OrgMailFrom)
			->addTo($team->email1)
			->setSubject('Palapeli - změna hesla pro tým ' . $name)
			->setHtmlBody(
				'Ahoj!<br>Někdo (pravděpodobně vy) požádal na stránkách šifrovací hry Palapeli o změnu hesla týmu ' . $escapedName
				. '. Bylo vám vygenerováno toto nové heslo: "' . $newPassword . '" (bez uvozovek). Pomocí hesla se můžete přihlásit'
				. ' do autentizované sekce <a href="' . $this->link('//Team:') . '">na stránkách Palapeli</a>.'
				. '<br><br>Těšíme se na vás na hře,<br>vaši organizátoři.',
			);

		if ($team->email2 !== null && $team->email2 !== '') {
			$mail->addTo($team->email2);
		}

		$this->mailer->send($mail);

		$this->flashMessage('Heslo bylo úspěšně změněno a zasláno na e-maily uvedené u týmu ' . $name, 'success');
		$this->redirect('Team:');
	}


	private function addMemberInputs(Form $form): void
	{
		$form->addText('member1', '*První člen týmu:')
			->setRequired('Zadejte prosím jméno prvního člena týmu.')
			->addRule($form::MaxLength, 'Jméno prvního člena může mít maximálně 255 znaků', 255);
		$form->addText('member2', 'Druhý člen týmu:')
			->setRequired(false)
			->addRule($form::MaxLength, 'Jméno druhého člena může mít maximálně 255 znaků', 255);
		$form->addText('member3', 'Třetí člen týmu:')
			->setRequired(false)
			->addRule($form::MaxLength, 'Jméno třetího člena může mít maximálně 255 znaků', 255);
		$form->addText('member4', 'Čtvrtý člen týmu:')
			->setRequired(false)
			->addRule($form::MaxLength, 'Jméno čtvrtého člena může mít maximálně 255 znaků', 255);
	}


	private function addContactInputs(Form $form): void
	{
		$form->addText('phone1', '*Telefon na 1. člena:')
			->setRequired('Zadejte prosím telefon.')
			->addRule($form::MaxLength, 'Telefon na prvního člena může mít maximálně 20 znaků', 20);
		$form->addText('phone2', 'Záložní telefon:')
			->setRequired(false)
			->addRule($form::MaxLength, 'Záložní telefon může mít maximálně 20 znaků', 20);
		$form->addText('email1', '*E-mail na 1. člena:')
			->setRequired('Zadejte prosím e-mail.')
			->addRule($form::Email, 'E-mail není ve správném tvaru.')
			->addRule($form::MaxLength, 'E-mail prvního člena může mít maximálně 255 znaků', 255);
		$form->addText('email2', 'Záložní e-mail:')
			->setRequired(false)
			->addRule($form::Email, 'Záložní-mail není ve správném tvaru.')
			->addRule($form::MaxLength, 'E-mail druhého člena může mít maximálně 255 znaků', 255);
	}


	/**
	 * Registers the team to the edition, prefilling members from its most recent participation.
	 */
	private function registerWithPreviousMembers(int $teamId, int $year, string $teamName): void
	{
		$previous = $this->teamsModel->getMostRecentTeamYearData($teamId);
		$this->teamsModel->registerTeam(
			$teamId,
			$year,
			$previous->member1 ?? '',
			$previous->member2 ?? '',
			$previous->member3 ?? '',
			$previous->member4 ?? '',
		);

		if ($this->isOverTeamLimit($year)) {
			$this->flashMessage(sprintf(self::StandbyRegisteredMessage, $teamName), 'info');
		} else {
			$this->flashMessage('Tým ' . $teamName . ' byl úspěšně zaregistrován do aktuálního ročníku.', 'success');
		}
	}


	private function isOverTeamLimit(int $year): bool
	{
		$teamLimit = $this->yearsModel->getTeamLimit($year);
		return $teamLimit && $this->teamsModel->getTeamsCount($year) > $teamLimit;
	}


	private function requireLoggedTeam(): int
	{
		return $this->teamId ?? $this->error('Nejste přihlášeni.', 403);
	}


	private static function formError(string $html): Html
	{
		return Html::el('div', ['class' => 'flash info'])->setHtml($html);
	}
}
