<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Components\CheckpointCard\CheckpointCard;
use App\Components\CheckpointCard\CheckpointCardFactory;
use App\Components\DiscussionControl\DiscussionControl;
use App\Components\DiscussionControl\DiscussionControlFactory;
use App\Components\TeamCard\TeamCard;
use App\Components\TeamCard\TeamCardFactory;
use App\Components\TeamMessage\TeamMessage;
use App\Components\TeamMessage\TeamMessageFactory;
use App\Components\UpdatesForm\UpdatesForm;
use App\Components\UpdatesForm\UpdatesFormFactory;
use App\Components\YearForm\YearForm;
use App\Components\YearForm\YearFormFactory;
use App\Models\CipherFileStorage;
use App\Models\CipherFileType;
use App\Models\CiphersModel;
use App\Models\PaymentStatus;
use App\Models\ReportsModel;
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;


/**
 * Administration for organizers (logged in as the organizer team). All pages work with the
 * selected game edition.
 */
final class AdministrationPresenter extends BasePresenter
{
	private const PaymentInputPrefix = 'team_';


	public function __construct(
		private readonly DiscussionControlFactory $discussionControlFactory,
		private readonly UpdatesFormFactory $updatesFormFactory,
		private readonly YearFormFactory $yearFormFactory,
		private readonly TeamCardFactory $teamCardFactory,
		private readonly CheckpointCardFactory $checkpointCardFactory,
		private readonly TeamMessageFactory $teamMessageFactory,
		private readonly TeamsModel $teamsModel,
		private readonly ReportsModel $reportsModel,
		private readonly ResultsModel $resultsModel,
		private readonly CiphersModel $ciphersModel,
		private readonly CipherFileStorage $cipherFileStorage,
	) {
		parent::__construct();
	}


	protected function startup(): void
	{
		parent::startup();

		if (!$this->teamSession->isOrg()) {
			if ($this->getSignal() !== null) {
				$this->error('Na tuto stránku mají přístup pouze organizátoři.', 403);
			}

			// the layout shows a message with a link to the login page
			$this->setView('forbidden');
		}
	}


	public function renderForbidden(): void
	{
		$this->prepareHeading('Administrace');
	}


	public function renderDefault(): void
	{
		$this->prepareHeading('Přidání aktuality');
	}


	public function renderTeamCard(): void
	{
		$this->prepareHeading('Karta týmu');
	}


	public function renderCheckpointCard(?int $checkpoint = null, bool $previous = false): void
	{
		$this->prepareHeading('Karta stanoviště');
	}


	public function renderCiphers(?int $checkpoint = null): void
	{
		$this->prepareHeading('Vkládání šifer');
		$this->template->checkpointCount = $this->yearsModel->getCheckpointCount($this->selectedYear);
		$this->template->checkpoint = $checkpoint;
	}


	public function renderDiscussion(): void
	{
		$this->prepareHeading('Kompletní diskuse');
	}


	public function renderChat(): void
	{
		$this->prepareHeading('Organizátorský chat');
	}


	public function renderPayments(): void
	{
		$this->prepareHeading('Platba startovného');
	}


	public function renderPaymentMail(): void
	{
		$this->prepareHeading('E-maily týmů s nezaplaceným startovným');
		$this->template->emails = $this->teamsModel->getUnpaidTeamsData($this->selectedYear);
	}


	public function renderTeamMail(): void
	{
		$this->prepareHeading('E-maily na týmy');
		$this->template->emails = $this->teamsModel->getPlayingTeamsEmails($this->selectedYear);
	}


	public function renderTeamTable(bool $orderByRegistration = false): void
	{
		$this->prepareHeading('Tabulka údajů o týmech');
		$this->template->data = $orderByRegistration
			? $this->teamsModel->getPlayingTeamsByRegistration($this->selectedYear)
			: $this->teamsModel->getPlayingTeams($this->selectedYear);
	}


	public function renderTeamMessage(): void
	{
		$this->prepareHeading('Odeslat zprávu týmům');
	}


	public function renderYear(bool $createNew = false): void
	{
		$this->prepareHeading('Správa ročníků');
	}


	public function renderWhereIsWho(): void
	{
		$this->prepareHeading('Kde je kdo');
		$this->template->data = $this->resultsModel->whereIsWho($this->selectedYear);
	}


	public function renderResults(): void
	{
		$this->prepareHeading('Výsledky');
		$this->template->data = $this->resultsModel->getTeamStandings($this->selectedYear);
		$this->template->resultsPublic = $this->resultsModel->getResultsPublic($this->selectedYear);
	}


	public function renderReports(): void
	{
		$this->prepareHeading('Přidání reportáže');
	}


	protected function createComponentDiscussion(): DiscussionControl
	{
		return $this->createDiscussion(DiscussionControl::AnyThread);
	}


	protected function createComponentChat(): DiscussionControl
	{
		return $this->createDiscussion(DiscussionControl::ChatThread);
	}


	private function createDiscussion(string $thread): DiscussionControl
	{
		return $this->discussionControlFactory->create()
			->setTeamId($this->teamId)
			->setTeamName($this->teamSession->getTeamName())
			->setThread($thread);
	}


	protected function createComponentUpdateForm(): UpdatesForm
	{
		return $this->updatesFormFactory->create()->setYear($this->selectedYear);
	}


	protected function createComponentYearForm(): YearForm
	{
		return $this->yearFormFactory->create()
			->setYear($this->selectedYear)
			->setCreateNew((bool) $this->getParameter('createNew'));
	}


	protected function createComponentTeamCard(): TeamCard
	{
		return $this->teamCardFactory->create()->setYear($this->selectedYear);
	}


	protected function createComponentCheckpointCard(): CheckpointCard
	{
		$checkpoint = $this->getParameter('checkpoint');

		return $this->checkpointCardFactory->create()
			->setYear($this->selectedYear)
			->setCheckpoint(
				$checkpoint === null || $checkpoint === '' ? null : (int) $checkpoint,
				(bool) $this->getParameter('previous'),
			);
	}


	protected function createComponentTeamMessage(): TeamMessage
	{
		return $this->teamMessageFactory->create()->setYear($this->selectedYear);
	}


	/**
	 * Select box switching the checkpoint on the ciphers page.
	 */
	protected function createComponentSelectOnlyCheckpointForm(): Form
	{
		$checkpointCount = $this->yearsModel->getCheckpointCount($this->selectedYear);

		// option value = checkpoint number + 1, 0 is the prompt
		$options = ['Vyberte stanoviště'];
		for ($i = 0; $i < $checkpointCount; $i++) {
			$options[] = match ($i) {
				$checkpointCount - 1 => 'Cíl',
				0 => 'Start',
				default => $i . '. stanoviště',
			};
		}

		$form = new Form;
		$form->addSelect('checkpoint', '', $options, 1)
			->setHtmlAttribute('onchange', 'this.form.submit()');
		$form->onSuccess[] = function (Form $form, array $values): void {
			$selected = (int) $values['checkpoint'];
			$this->redirect('this', ['checkpoint' => $selected === 0 ? 0 : $selected - 1]);
		};
		return $form;
	}


	protected function createComponentCipherForm(): Form
	{
		$checkpoint = (int) $this->getParameter('checkpoint');
		$data = $this->ciphersModel->getCipher($this->selectedYear, $checkpoint);

		$form = new Form;
		$form->addText('name', 'Název šifry', null, 255);
		$form->addTextArea('cipher_description', 'Popis zadání');
		$form->addTextArea('solution_description', 'Popis řešení');
		$form->addText('solution', 'Řešení', null, 1023);
		$form->addText('dead_solution', 'Řešení v totálce (hlavně pro šifry, jejichž řešením je heslo - toto se zobrazí po zadání správného hesla)', null, 1023);
		$form->addText('code', 'Kód do Palainfa', null, 255);
		$form->addText('specification', 'Upřesnítko', null, 255);
		$form->addTime('checkpoint_close_time', 'Zavření stanoviště')->setFormat('H:i');
		$form->addCheckbox('has_password_solution', 'Řešením je heslo');
		$form->addUpload('cipher_image', 'Obrázek šifry');
		$form->addUpload('solution_image', 'Obrázek řešení');
		$form->addUpload('pdf_file', 'PDF soubor se šifrou');
		$form->addSubmit('send', 'VLOŽIT ŠIFRU');

		if ($data) {
			$form->setDefaults([
				'name' => $data->name,
				'cipher_description' => $data->cipher_description,
				'solution_description' => $data->solution_description,
				'solution' => $data->solution,
				'dead_solution' => $data->dead_solution,
				'code' => $data->code,
				'specification' => $data->specification,
				'checkpoint_close_time' => $data->checkpoint_close_time?->format('%H:%I'),
				'has_password_solution' => (bool) $data->has_password_solution,
			]);
		}

		$form->onSuccess[] = $this->cipherFormSucceeded(...);
		return $form;
	}


	/**
	 * @param array<string, mixed> $values
	 */
	private function cipherFormSucceeded(Form $form, array $values): void
	{
		$year = $this->selectedYear;
		$checkpoint = (int) $this->getParameter('checkpoint');

		$this->ciphersModel->upsertCipher($year, $checkpoint, [
			'name' => $values['name'],
			'cipher_description' => $values['cipher_description'],
			'solution_description' => $values['solution_description'],
			'solution' => $values['solution'],
			'dead_solution' => $values['dead_solution'],
			'code' => $values['code'],
			'specification' => $values['specification'],
			'checkpoint_close_time' => $values['checkpoint_close_time'],
			'has_password_solution' => $values['has_password_solution'],
		]);

		$uploads = [
			'cipher_image' => CipherFileType::CipherImage,
			'solution_image' => CipherFileType::SolutionImage,
			'pdf_file' => CipherFileType::Pdf,
		];
		foreach ($uploads as $input => $type) {
			$upload = $values[$input];
			assert($upload instanceof FileUpload);
			$fileId = $this->cipherFileStorage->store($upload, $year, $checkpoint, $type);
			if ($fileId !== null) {
				$this->ciphersModel->attachFile($year, $checkpoint, $type, $fileId);
			}
		}

		$this->flashMessage('Šifra byla úspěšně vložena.', 'success');
		$this->redirect('this');
	}


	protected function createComponentPayments(): Form
	{
		$form = new Form;
		foreach ($this->teamsModel->getTeamsPaymentStatus($this->selectedYear) as $team) {
			$form->addRadioList(self::PaymentInputPrefix . $team->id, $team->name, PaymentStatus::options())
				->setDefaultValue($team->paid);
		}

		$form->addSubmit('send', 'UPRAVIT PLATBY');
		$form->onSuccess[] = $this->editPayments(...);
		return $form;
	}


	/**
	 * @param array<string, int> $values  "team_<id>" => payment status
	 */
	private function editPayments(Form $form, array $values): void
	{
		foreach ($values as $input => $status) {
			$teamId = (int) substr($input, strlen(self::PaymentInputPrefix));
			$this->teamsModel->editTeamPayment($teamId, $this->selectedYear, PaymentStatus::from((int) $status));
		}

		$this->flashMessage('Platby byly úspěšně uloženy', 'success');
		$this->redirect('this');
	}


	protected function createComponentPublishResults(): Form
	{
		$form = new Form;
		$form->addSubmit('send', 'PUBLIKOVAT VÝSLEDKY');
		$form->onSuccess[] = function (): void {
			$this->resultsModel->publishResults($this->selectedYear);
			$this->flashMessage('Výsledky byly úspěšně zveřejněny', 'success');
			$this->redirect('this');
		};
		return $form;
	}


	protected function createComponentRemoveTrailingHints(): Form
	{
		$form = new Form;
		$form->addSubmit('send', 'ODSTRANIT TOTÁLKY TÝMŮ, KTERÉ NEDOŠLY NA DALŠÍ STANOVIŠTĚ');
		$form->onSuccess[] = function (): void {
			if (!$this->yearsModel->hasGameEnded($this->selectedYear)) {
				$this->flashMessage('Hra ještě neskončila, tato akce by znehodnotila výsledky.', 'error');
			} else {
				$this->resultsModel->removeTrailingHints($this->selectedYear);
				$this->flashMessage('Totálky byly úspěšně odstraněny', 'success');
			}

			$this->redirect('this');
		};
		return $form;
	}


	protected function createComponentAddReport(): Form
	{
		$form = new Form;
		$form->addInteger('year', 'Ročník*')
			->setDefaultValue($this->selectedYear)
			->setRequired();
		$form->addText('team', 'Autor (tým)*')->setRequired();
		$form->addText('name', 'Název repotráže');
		$form->addText('link', 'Odkaz na reportáž*');
		$form->addTextArea('description', 'Popis', 50, 10);
		$form->addSubmit('send', 'VLOŽIT REPORTÁŽ');
		$form->onSuccess[] = $this->addReportSucceeded(...);
		return $form;
	}


	/**
	 * @param array{year: int, team: string, name: string, link: string, description: string} $values
	 */
	private function addReportSucceeded(Form $form, array $values): void
	{
		$teamId = $this->teamsModel->getTeamId($values['team']);
		if ($teamId === null) {
			$form->addError('Tým se zadaným názvem neexistuje');
			return;
		}

		$this->reportsModel->insertReport($values['year'], $values['link'], $teamId, $values['name'], $values['description']);

		$this->flashMessage('Reportáž byla úspěšně přidána', 'success');
		$this->redirect('this');
	}
}
