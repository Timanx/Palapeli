<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Components\DiscussionControl\DiscussionControl;
use App\Components\DiscussionControl\DiscussionControlFactory;
use App\Models\CiphersModel;
use App\Models\PaymentStatus;
use App\Models\ReportsModel;
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Utils\Utils;


/**
 * Section "Hra": registered teams, results, ciphers with solutions and statistics, photos, reports.
 * All pages show data of the selected game edition.
 */
final class GamePresenter extends BasePresenter
{
	public function __construct(
		private readonly ResultsModel $resultsModel,
		private readonly TeamsModel $teamsModel,
		private readonly ReportsModel $reportsModel,
		private readonly CiphersModel $ciphersModel,
		private readonly DiscussionControlFactory $discussionControlFactory,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$this->prepareHeading('Seznam týmů');

		$year = $this->selectedYear;
		$playing = $this->teamsModel->getPlayingTeams($year);
		$standby = $this->teamsModel->getStandbyTeams($year);

		$this->template->hasGameEnded = $this->yearsModel->hasGameEnded($year);
		$this->template->paid = $this->teamsModel->countTeamsWithPaymentStatus($year, PaymentStatus::Paid);
		$this->template->startPayment = $this->teamsModel->countTeamsWithPaymentStatus($year, PaymentStatus::AtStart);
		$this->template->data = $playing;
		$this->template->standby = $standby;
		$this->template->teamsCount = count($playing) + count($standby);
		$this->template->standbyCount = count($standby);
	}


	/**
	 * @param ?int $year  links to ciphers carry the edition, so that they can be shared
	 */
	public function actionCiphers(int $checkpoint = 0, ?int $year = null): void
	{
		if ($year !== null && $year !== $this->selectedYear) {
			$this->selectYear($year);
		}
	}


	public function renderCiphers(int $checkpoint = 0): void
	{
		$this->prepareHeading('Šifry');
		$year = $this->selectedYear;

		$checkpointCount = $this->yearsModel->getCheckpointCount($year);
		if (!$this->yearsModel->hasFinishCipher($year)) {
			$checkpointCount--;
		}

		$teamsCount = $this->teamsModel->getTeamsCount($year);
		$teamsFilled = $this->resultsModel->getTeamsFilledIds($year, $checkpoint);
		$teamsArrived = $this->resultsModel->getTeamsArrivedCount($year, $checkpoint);
		$teamsContinued = $this->resultsModel->getTeamsContinuedIds($year, $checkpoint);
		$teamsFilledContinued = count(array_intersect($teamsFilled, $teamsContinued));
		$usedHints = $this->resultsModel->getUsedHintsCount($year, $checkpoint, $teamsContinued);
		$teamsEnded = $teamsArrived - count($teamsContinued);

		$template = $this->template;
		$template->hasGameEnded = $this->yearsModel->hasGameEnded($year);
		$template->checkpointCount = $checkpointCount;
		$template->checkpoint = $checkpoint;
		$template->cipherData = $this->ciphersModel->getCipher($year, $checkpoint);
		$template->fastestSolution = $this->resultsModel->getFastestSolution($year, $checkpoint);
		$template->teamsTotal = $teamsCount;
		$template->teamsFilled = count($teamsFilled);
		$template->teamsArrived = $teamsArrived;
		$template->teamsContinued = count($teamsContinued);
		$template->teamsFilledContinued = $teamsFilledContinued;
		$template->usedHints = $usedHints;
		$template->teamsEnded = $teamsEnded;
		$template->usedHintsPercentage = Utils::percentages($usedHints, $teamsFilledContinued);
		$template->teamsEndedPercentage = Utils::percentages($teamsEnded, $teamsArrived);
		$template->teamsArrivedPercentage = Utils::percentages($teamsArrived, $teamsCount);
		$template->missingData = max($teamsArrived, $teamsEnded + count($teamsContinued)) - count($teamsFilled);
	}


	public function renderPhotos(): void
	{
		$this->prepareHeading('Fotky');
	}


	public function renderResults(): void
	{
		$this->prepareHeading('Výsledky');

		$year = $this->selectedYear;
		$this->template->data = $this->resultsModel->getTeamStandings($year);
		$this->template->resultsPublic = $this->resultsModel->getResultsPublic($year);
		$this->template->hasGameStarted = $this->yearsModel->hasGameStarted($year);
		$this->template->hasGameEnded = $this->yearsModel->hasGameEnded($year);
	}


	public function renderReports(): void
	{
		$this->prepareHeading('Reportáže');

		$this->template->years = $this->yearsModel->getYearNames();
		$this->template->hasGameEnded = $this->yearsModel->hasGameEnded($this->selectedYear);
		$this->template->reports = $this->reportsModel->getReports();
	}


	public function renderStats(): void
	{
		$this->prepareHeading('Statistiky');
		$year = $this->selectedYear;

		$this->template->hasGameEnded = $this->yearsModel->hasGameEnded($year);
		$this->template->cipherData = $this->computeCipherStats($year);
		$this->template->teamsTotalCount = $this->teamsModel->getTeamsCount($year);
		$this->template->checkpointCount = $this->yearsModel->hasFinishCipher($year)
			? $this->yearsModel->getCheckpointCount($year)
			: $this->yearsModel->getCheckpointCount($year) - 1;
	}


	/**
	 * For every checkpoint counts teams that solved the cipher, used the total hint, ended there
	 * or did not fill in their data.
	 * @return array<int, array{dead: int, hint: int, solved: int, no-data: int}>
	 */
	private function computeCipherStats(int $year): array
	{
		$stats = [];
		foreach ($this->resultsModel->getStatsData($year) as $row) {
			$checkpoint = (int) $row->checkpoint_number;
			$stats[$checkpoint] ??= ['dead' => 0, 'hint' => 0, 'solved' => 0, 'no-data' => 0];

			$key = match (true) {
				!$row->filled => 'no-data',
				!$row->continued => 'dead',
				(bool) $row->used_hint => 'hint',
				default => 'solved',
			};
			$stats[$checkpoint][$key]++;
		}

		// a team that reached a later checkpoint must have passed this one, even without data
		for ($i = 0; $i < count($stats) - 1; $i++) {
			$nextSum = array_sum($stats[$i + 1] ?? []);
			$thisSum = array_sum($stats[$i] ?? []);
			if ($nextSum > $thisSum && isset($stats[$i])) {
				$stats[$i]['no-data'] += $nextSum - $thisSum;
			}
		}

		return $stats;
	}


	public function renderScorecard(): void
	{
		$this->prepareHeading('Podrobné výsledky');

		$year = $this->selectedYear;
		$this->template->totalCheckpoints = $this->yearsModel->getCheckpointCount($year);
		$this->template->teams = $this->resultsModel->getTeamStandings($year);
		$this->template->results = $this->resultsModel->getCompleteResults($year);
		$this->template->resultsPublic = $this->resultsModel->getResultsPublic($year);
		$this->template->hasGameStarted = $this->yearsModel->hasGameStarted($year);
		$this->template->hasGameEnded = $this->yearsModel->hasGameEnded($year);
	}


	protected function createComponentCipherDiscussion(): DiscussionControl
	{
		$year = $this->getParameter('year');
		$checkpoint = $this->getParameter('checkpoint');

		return $this->discussionControlFactory->create()
			->setTeamId($this->teamId)
			->setTeamName($this->teamSession->getTeamName())
			->setThread(DiscussionControl::cipherThread(
				$year === null ? null : (int) $year,
				$checkpoint === null ? null : (int) $checkpoint,
			));
	}
}
