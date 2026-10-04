<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Models\YearsModel;
use App\Session\TeamSession;
use App\Session\YearSelection;
use App\Utils\AppConstants;
use Nette;
use Nette\Application\UI\Form;
use Nette\Bridges\ApplicationLatte\DefaultTemplate;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nette\Utils\Strings;


/**
 * Ancestor of all presenters with the common layout (menu, archive year switch, footer with
 * the contact form).
 *
 * The visitor browses one game edition at a time ("selected year", stored in the session);
 * it defaults to the current edition.
 *
 * @property-read DefaultTemplate $template
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
	/** Selected game edition (year number, e.g. 12) and its calendar year (e.g. 2024). */
	protected int $selectedYear;
	protected int $selectedCalendarYear;

	/** Logged-in team, null for anonymous visitors. */
	protected ?int $teamId = null;

	protected YearsModel $yearsModel;
	protected TeamSession $teamSession;
	protected YearSelection $yearSelection;
	protected Mailer $mailer;


	public function injectBase(
		YearsModel $yearsModel,
		TeamSession $teamSession,
		YearSelection $yearSelection,
		Mailer $mailer,
	): void
	{
		$this->yearsModel = $yearsModel;
		$this->teamSession = $teamSession;
		$this->yearSelection = $yearSelection;
		$this->mailer = $mailer;
	}


	protected function startup(): void
	{
		parent::startup();
		$this->teamId = $this->teamSession->getTeamId();
		$this->loadSelectedYear();
	}


	/**
	 * Variables used by the layout and shared templates.
	 */
	protected function beforeRender(): void
	{
		parent::beforeRender();
		$current = $this->yearsModel->getCurrentYearData();
		$teamName = $this->teamSession->getTeamName();
		$template = $this->template;

		$template->teamName = $teamName;
		$template->teamNameUpper = $teamName === null ? null : Strings::upper($teamName);
		$template->teamId = $this->teamId;
		$template->orgLogged = $this->teamSession->isOrg();
		$template->isTeamInCurrentYear = $this->teamId !== null && $this->yearsModel->isTeamInCurrentYear($this->teamId);

		$template->selectedYear = $this->selectedYear;
		$template->selectedCalendarYear = $this->selectedCalendarYear;
		$template->currentYear = (int) $current->year;
		$template->currentCalendarYear = (int) $current->calendar_year;
		$template->isSelectedYearCurrent = $this->selectedYear === (int) $current->year;
		$template->showTesterNotification = (bool) $current->show_tester_notification;
		$template->hasFinishCipher = $this->yearsModel->hasFinishCipher($this->selectedYear);
		$template->archiveData = $this->yearsModel->getArchiveSwitchData();
		$template->heading ??= null;
	}


	/**
	 * Sets the page heading (shown upper-cased) and the <title>.
	 */
	protected function prepareHeading(string $heading): void
	{
		$this->template->heading = Strings::upper($heading);
		$this->template->title = $heading;
	}


	/**
	 * Switches the browsed game edition.
	 */
	protected function selectYear(int $year, ?int $calendarYear = null): void
	{
		$calendarYear ??= $this->yearsModel->getCalendarYear($year) ?? $this->selectedCalendarYear;
		$this->yearSelection->select($year, $calendarYear);
		$this->selectedYear = $year;
		$this->selectedCalendarYear = $calendarYear;
	}


	private function loadSelectedYear(): void
	{
		$year = $this->yearSelection->getYear();
		$calendarYear = $this->yearSelection->getCalendarYear();

		if ($year === null || $calendarYear === null) {
			$current = $this->yearsModel->getCurrentYearData();
			$year ??= (int) $current->year;
			$calendarYear ??= (int) $current->calendar_year;
			$this->yearSelection->select($year, $calendarYear);
		}

		$this->selectedYear = $year;
		$this->selectedCalendarYear = $calendarYear;
	}


	/**
	 * Contact form in the footer of every page.
	 */
	protected function createComponentMailForm(): Form
	{
		$form = new Form;
		$form->addText('sender')
			->setHtmlAttribute('placeholder', 'Váš e-mail')
			->setRequired(false)
			->addRule($form::Email, 'E-mail není ve správném tvaru.');
		$form->addText('subject')
			->setHtmlAttribute('placeholder', 'Předmět')
			->setRequired('Zadejte prosím předmět e-mailu.');
		$form->addTextArea('message')
			->setHtmlAttribute('placeholder', 'Zpráva')
			->setRequired('Zadejte prosím text zprávy.');
		$form->addSubmit('cancel', 'ODESLAT E-MAIL');
		$form->onSuccess[] = $this->mailFormSucceeded(...);
		return $form;
	}


	/**
	 * @param array{sender: string, subject: string, message: string} $values
	 */
	private function mailFormSucceeded(Form $form, array $values): void
	{
		$values = array_map(strip_tags(...), $values);

		$mail = new Message;
		if ($values['sender'] !== '') {
			$mail->setFrom($values['sender'])
				->addReplyTo($values['sender']);
		} else {
			$mail->setFrom(AppConstants::OrgMailFrom);
		}

		$mail->addTo(AppConstants::OrgEmail)
			->setSubject('Zpráva z webu: ' . $values['subject'])
			->setBody($values['message'] . "\n\nZpráva odeslaná z webu.");
		$this->mailer->send($mail);

		$this->flashMessage('E-mail byl úspěšně odeslán.', 'success');
		$this->redirect('this');
	}
}
