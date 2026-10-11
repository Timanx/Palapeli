<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Components\ActionScreen\ActionScreen;
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
use Nette\Database\Row;
use Nette\Http\FileUpload;


/**
 * Administration for organizers (logged in as the organizer team). All pages work with the
 * selected game edition.
 */
final class AdministrationPresenter extends BasePresenter
{
	private const PaymentInputPrefix = 'team_';

	/** Prefilled text shown to teams while they wait for the cipher (ciphers.wait_message). */
	private const DefaultWaitMessage = 'Zatím si můžete opéct špekáček nebo se jinak občerstvit.';


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
		$year = $this->selectedYear;
		$checkpointCount = $this->yearsModel->getCheckpointCount($year);
		$hasFinishCipher = $this->yearsModel->hasFinishCipher($year);
		$playingIds = $this->teamsModel->getPlayingTeamsIds($year);

		// substitutes that did not get to the game are not listed
		$teams = array_filter(
			$this->resultsModel->whereIsWho($year),
			static fn(Row $team): bool => $team->checkpoint_number !== null || in_array($team->team_id, $playingIds, strict: true),
		);

		$finishedIds = [];
		$counts = ['playing' => 0, 'finished' => 0, 'quit' => 0, 'notStarted' => 0];
		foreach ($teams as $team) {
			// reaching the finish did not set end_time in older editions
			if ($team->checkpoint_number === null) {
				$counts['notStarted']++;
			} elseif (ActionScreen::hasReachedFinish($team->checkpoint_number + 1, $checkpointCount, $hasFinishCipher)) {
				$finishedIds[] = $team->team_id;
				$counts['finished']++;
			} elseif ($team->ended) {
				$counts['quit']++;
			} else {
				$counts['playing']++;
			}
		}

		$this->template->data = $teams;
		$this->template->finishedIds = $finishedIds;
		$this->template->counts = $counts;
		$this->template->checkpointCount = $checkpointCount;
		$this->template->hasFinishCipher = $hasFinishCipher;
	}


	public function renderResults(): void
	{
		$this->prepareHeading('Výsledky');
		$this->template->resultsPublic = $resultsPublic = $this->resultsModel->getResultsPublic($this->selectedYear);
		// before publication show the standings the way they will be published
		$this->template->data = $this->resultsModel->getTeamStandings($this->selectedYear, ignoreTrailingHints: !$resultsPublic);
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
		$form->addText('name', 'Název šifry', null, 255)
			->setOption('description', 'Zobrazí se v archivu šifer (u startu a cíle se název nezobrazuje).');
		$form->addTextArea('cipher_description', 'Popis zadání')
			->setOption('description', 'Text zadání v archivu šifer, lze použít HTML.');
		$form->addTextArea('solution_description', 'Popis řešení')
			->setOption('description', 'Postup řešení v archivu šifer, lze použít HTML. (Archiv aktuálního ročníku se veřejně zobrazí až po konci hry.)');
		$form->addText('solution', 'Řešení', null, 1023)
			->setOption('description', 'Zobrazí se v archivu šifer. U šifry „Řešením je heslo“ a u cíle ročníku s cílovou šifrou je to zároveň heslo, které týmy zadávají do Palainfa – velikost písmen, diakritika, mezery a interpunkce se nerozlišují, takže lze psát čitelně („Tajné heslo“).');
		$form->addText('dead_solution', 'Řešení v totálce', null, 1023)
			->setOption('description', 'Zobrazí se týmu, který si v Palainfu vezme totálku – obvykle poloha dalšího stanoviště. U šifry s heslem se zobrazí i po zadání správného hesla, proto je povinné. Prázdné = zobrazí se „Řešení“.');
		$form->addText('entry_message', 'Zpráva po zadání kódu', null, 1023)
			->setOption('description', 'Zobrazí se týmu hned po zadání kódu tohoto stanoviště a zůstane v záložce INFO – např. kde přesně šifru najde, nebo u cíle, že má zadat heslo z poslední šifry.');
		$form->addInteger('wait_minutes', 'Čekání na šifru (min)')
			->addRule($form::Range, 'Čekání na šifru musí být 1 až 600 minut.', [1, 600])
			->setOption('description', 'Povinné čekání po zadání kódu, než tým od organizátorů dostane šifru (např. u ohně). Palainfo týmu ukáže oranžovou obrazovku s odpočtem; po jeho uplynutí zezelená s nápisem „Tým <název týmu> si již může vyzvednout šifru“ – tu tým ukáže organizátorům. Zelená obrazovka zmizí, až tým zadá „Kód na šifře“. Odchod ze stanoviště lze zadat až po převzetí šifry, totálku kdykoli. Prázdné = bez čekání.');
		$form->addText('wait_message', 'Zpráva během čekání', null, 1023)
			->setDefaultValue(self::DefaultWaitMessage)
			->setOption('description', 'Zobrazí se na oranžové obrazovce pod odpočtem. Předvyplněno, lze změnit; prázdné = nic navíc.');
		$form->addText('pickup_code', 'Kód na šifře', null, 255)
			->setOption('description', 'Kód napsaný na zadání šifry, kterou tým po čekání dostane. Palainfo ho po týmu chce jako „KÓD NA ŠIFŘE X“; jeho zadáním tým zapíše převzetí šifry a zmizí zelená obrazovka. Velikost písmen se nerozlišuje. Povinný, pokud je nastaveno čekání; musí se lišit od kódu stanoviště.');
		$form->addText('code', 'Kód do Palainfa', null, 255)
			->setOption('description', 'Kód umístěný na stanovišti; jeho zadáním tým v Palainfu zapíše příchod. Velikost písmen se nerozlišuje. Bez kódu na stanoviště nelze v Palainfu dojít.');
		$form->addText('specification', 'Upřesnítko', null, 255)
			->setOption('description', 'Zobrazí se týmům v Palainfu v záložce KARTA u tohoto stanoviště a v záložce AKCE, dokud na toto stanoviště míří.');
		$form->addTime('checkpoint_close_time', 'Zavření stanoviště')->setFormat('H:i')
			->setOption('description', 'Zobrazí se v záložce KARTA („Zavírá v …“). Jen informativní – Palainfo kód přijme i po zavření. Prázdné = otevřeno do konce hry.');
		$form->addCheckbox('has_password_solution', 'Řešením je heslo')
			->setOption('description', 'Po příchodu tým zadá do Palainfa heslo („Řešení“); správné heslo zapíše odchod a zobrazí „Řešení v totálce“ (polohu dalšího stanoviště). Nezaškrtnuto: tým jen dojde na další stanoviště a zadá jeho kód. U cíle ročníku s cílovou šifrou se heslo vyžaduje vždy, i bez zaškrtnutí.');
		$form->addUpload('cipher_image', 'Obrázek šifry')
			->setOption('description', 'Obrázek zadání v archivu; nový soubor nahradí předchozí.');
		$form->addUpload('solution_image', 'Obrázek řešení')
			->setOption('description', 'Obrázek v řešení v archivu; nový soubor nahradí předchozí.');
		$form->addUpload('pdf_file', 'PDF soubor se šifrou')
			->setOption('description', 'Ke stažení v archivu; nový soubor nahradí předchozí.');
		$form->addSubmit('send', 'VLOŽIT ŠIFRU');

		if ($data) {
			$form->setDefaults([
				'name' => $data->name,
				'cipher_description' => $data->cipher_description,
				'solution_description' => $data->solution_description,
				'solution' => $data->solution,
				'dead_solution' => $data->dead_solution,
				'entry_message' => $data->entry_message,
				'wait_minutes' => $data->wait_minutes,
				'wait_message' => $data->wait_message ?? self::DefaultWaitMessage, // NULL = never saved
				'pickup_code' => $data->pickup_code,
				'code' => $data->code,
				'specification' => $data->specification,
				'checkpoint_close_time' => $data->checkpoint_close_time?->format('%H:%I'),
				'has_password_solution' => (bool) $data->has_password_solution,
			]);
		}

		$form->onValidate[] = $this->validateCipherForm(...);
		$form->onSuccess[] = $this->cipherFormSucceeded(...);
		return $form;
	}


	/**
	 * Password ciphers need the password and the location of the next checkpoint (otherwise Palainfo
	 * would show the password itself as the location). The finish cipher is always solved by a password.
	 * A wait for the cipher needs the pickup code that removes the green screen in Palainfo.
	 * @param array<string, mixed> $values
	 */
	private function validateCipherForm(Form $form, array $values): void
	{
		$checkpoint = (int) $this->getParameter('checkpoint');
		$isFinishCipher = $this->yearsModel->hasFinishCipher($this->selectedYear)
			&& $checkpoint === $this->yearsModel->getCheckpointCount($this->selectedYear) - 1;

		if (($values['has_password_solution'] || $isFinishCipher) && trim((string) $values['solution']) === '') {
			$form->addError('Vyplňte řešení – heslo, které týmy zadávají do Palainfa.');
		}

		if ($values['has_password_solution'] && !$isFinishCipher && trim((string) $values['dead_solution']) === '') {
			$form->addError('Vyplňte řešení v totálce – umístění dalšího stanoviště, které se zobrazí po zadání hesla.');
		}

		$pickupCode = trim((string) $values['pickup_code']);
		if ($values['wait_minutes'] !== null && $pickupCode === '') {
			$form->addError('Vyplňte kód na šifře – tým ho po čekání najde na zadání a zadá do Palainfa.');
		} elseif ($values['wait_minutes'] !== null && mb_strtoupper($pickupCode) === mb_strtoupper(trim((string) $values['code']))) {
			$form->addError('Kód na šifře se musí lišit od kódu stanoviště.');
		}
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
			'entry_message' => $values['entry_message'],
			'wait_minutes' => $values['wait_minutes'],
			'wait_message' => $values['wait_message'],
			'pickup_code' => $values['pickup_code'],
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
			// hints of teams that did not reach the next checkpoint are known only after the end of the game
			if (!$this->yearsModel->hasGameEnded($this->selectedYear)) {
				$this->flashMessage('Hra ještě neskončila, výsledky lze zveřejnit až po jejím konci.', 'error');
			} else {
				$this->resultsModel->removeTrailingHints($this->selectedYear);
				$this->resultsModel->publishResults($this->selectedYear);
				$this->flashMessage('Výsledky byly úspěšně zveřejněny (totálky týmů, které nedošly na další stanoviště, byly odstraněny).', 'success');
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
