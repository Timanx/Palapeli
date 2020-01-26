<?php

namespace App\Presenters;

use App\Models\CiphersModel;
use App\Models\ReportsModel;
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use DiscussionControl;
use Nette\Application\UI;
use App\Utils\Utils;
use Nette;


class GamePresenter extends BasePresenter
{
    /** @var  YearsModel */
    private $yearsModel;
    /** @var  ResultsModel */
    private $resultsModel;
    /** @var  TeamsModel */
    private $teamsModel;
    /** @var  ReportsModel */
    private $reportsModel;
    /** @var  CiphersModel */
    private $ciphersModel;
    /** @var  \IDiscussionControlFactory */
    private $discussionControlFactory;

    public function __construct(YearsModel $yearsModel, ResultsModel $resultsModel, TeamsModel $teamsModel, ReportsModel $reportsModel, CiphersModel $ciphersModel, \IDiscussionControlFactory $discussionControlFactory)
    {
        parent::__construct();
        $this->yearsModel = $yearsModel;
        $this->resultsModel = $resultsModel;
        $this->teamsModel = $teamsModel;
        $this->reportsModel = $reportsModel;
        $this->ciphersModel = $ciphersModel;
        $this->discussionControlFactory = $discussionControlFactory;
    }

    public function renderDefault()
    {
        parent::render();
        $this->prepareHeading('Seznam týmů');

        $this->teamsModel->setYear($this->selectedYear);
        $this->yearsModel->setYear($this->selectedYear);
        $this->template->hasGameEnded = $this->yearsModel->hasGameEnded();
        $data = $this->teamsModel->getPlayingTeams();
        $standby = $this->teamsModel->getStandbyTeams();
        $this->template->paid = $this->teamsModel->getPaidTeamsCount();
        $this->template->startPayment = $this->teamsModel->getStartPaymentTeamsCount();
        $this->template->data = $data;
        $this->template->standby = $standby;
        $this->template->teamsCount = count($data) + count($standby);
        $this->template->standbyCount = count($standby);

    }

    public function renderCiphers($checkpoint = 0, $year = null)
    {
        if (isset($year)) {
            $this->session->getSection('selected')->year = $year;
            $this->session->getSection('selected')->calendarYear = $year + 2011;
        }
        parent::render();
        $this->prepareHeading('Šifry');
        $this->yearsModel->setYear($this->selectedYear);
        $this->resultsModel->setYear($this->selectedYear);
        $this->teamsModel->setYear($this->selectedYear);
        $this->ciphersModel->setYear($this->selectedYear);

        $this->template->hasGameEnded = $this->yearsModel->hasGameEnded();

        $checkpointCount = $this->yearsModel->getCheckpointCount();

        if (!$this->yearsModel->hasFinishCipher()) {
            $checkpointCount--;
        }

        $this->template->checkpointCount = $checkpointCount;
        $this->template->cipherData = $this->ciphersModel->getCipher($checkpoint);
        $this->template->fastestSolution = $this->resultsModel->getFastestSolution($checkpoint);
        $this->template->teamsTotal = $teamsCount = $this->teamsModel->getTeamsCount();
        $teamsFilled = $this->resultsModel->getTeamsFilledIds($checkpoint);
        $this->template->teamsFilled = $teamsFilledCount = count($teamsFilled);
        $this->template->teamsArrived = $teamsArrivedCount = $this->resultsModel->geTeamsArrivedCount($checkpoint);

        $teamsContinued = array_keys($this->resultsModel->getTeamsContinuedIds($checkpoint));
        $this->template->usedHints = $usedHintsCount = $this->resultsModel->getUsedHintsCount($checkpoint, $teamsContinued);
        $this->template->teamsContinued = $teamsContinuedCount = count($teamsContinued);
        $this->template->teamsFilledContinued = $teamsFilledContinuedCount = count(array_intersect($teamsFilled, $teamsContinued));

        $this->template->teamsEnded = $teamsEndedCount = $teamsArrivedCount - $teamsContinuedCount;

        $this->template->usedHintsPercentage = Utils::percentages($usedHintsCount, $teamsFilledContinuedCount);
        $this->template->teamsEndedPercentage = Utils::percentages($teamsEndedCount, $teamsArrivedCount);
        $this->template->teamsArrivedPercentage = Utils::percentages($teamsArrivedCount, $teamsCount);

        $this->template->missingData = max($teamsArrivedCount, $teamsEndedCount + $teamsContinuedCount) - $teamsFilledCount;

        $this->template->checkpoint = $checkpoint;
    }

    public function renderPhotos()
    {
        parent::render();
        $this->prepareHeading('Fotky');
    }

    public function renderResults()
    {
        parent::render();
        $this->prepareHeading('Výsledky');

        $this->yearsModel->setYear($this->selectedYear);
        $this->resultsModel->setYear($this->selectedYear);

        $this->template->data = $data =$this->resultsModel->getTeamStandings();
        $this->template->resultsPublic = $this->resultsModel->getResultsPublic();
        $this->template->hasGameStarted = $this->yearsModel->hasGameStarted();
        $this->template->hasGameEnded = $this->yearsModel->hasGameEnded();
    }

    public function renderReports()
    {
        parent::render();
        $this->prepareHeading('Reportáže');

        $this->template->years = $this->yearsModel->getYearNames();
        $this->yearsModel->setYear($this->selectedYear);
        $this->template->hasGameEnded = $this->yearsModel->hasGameEnded();
        $this->template->reports = $reports = $this->reportsModel->getReports();
    }

    public function renderStats()
    {
        parent::render();
        $this->prepareHeading('Statistiky');


        $this->yearsModel->setYear($this->selectedYear);
        $this->resultsModel->setYear($this->selectedYear);
        $this->teamsModel->setYear($this->selectedYear);
        $data = $this->resultsModel->getStatsData();

        $this->template->hasGameEnded = $this->yearsModel->hasGameEnded();

        $cipherData = [];

        foreach ($data as $row) {
            if (!isset($cipherData[$row->checkpoint_number])) {
                $cipherData[$row->checkpoint_number] = ['dead' => 0, 'hint' => 0, 'solved' => 0, 'no-data' => 0];
            }
            if ($row->filled) {
                if (!$row->continued) {
                    $cipherData[$row->checkpoint_number]['dead']++;
                } elseif ($row->used_hint) {
                    $cipherData[$row->checkpoint_number]['hint']++;
                } else {
                    $cipherData[$row->checkpoint_number]['solved']++;
                }
            } else {
                $cipherData[$row->checkpoint_number]['no-data']++;
            }
        }

        for ($i = 0; $i < count($cipherData) - 1; $i++) {
            $nextSum = array_sum($cipherData[$i + 1]);
            $thisSum = array_sum($cipherData[$i]);
            if ($nextSum > $thisSum) {
                $cipherData[$i]['no-data'] += $nextSum - $thisSum;
            }
        }

        $this->template->cipherData = $cipherData;
        $this->template->teamsTotalCount = $this->teamsModel->getTeamsCount();
        $this->template->checkpointCount = (
            $this->yearsModel->hasFinishCipher() ?
                $this->yearsModel->getCheckpointCount() :
                $this->yearsModel->getCheckpointCount() - 1
        );
    }

    public function renderScorecard()
    {
        parent::render();
        $this->prepareHeading('Podrobné výsledky');

        $this->yearsModel->setYear($this->selectedYear);
        $this->resultsModel->setYear($this->selectedYear);
        $this->template->totalCheckpoints = $this->yearsModel->getCheckpointCount();
        $this->template->teams = $this->resultsModel->getTeamStandings();
        $this->template->results = $this->resultsModel->getCompleteResults();
        $this->template->resultsPublic = $this->resultsModel->getResultsPublic();
        $this->template->hasGameStarted = $this->yearsModel->hasGameStarted();
        $this->template->hasGameEnded = $this->yearsModel->hasGameEnded();
    }

    protected function createComponentCipherDiscussion()
    {

        /** @var DiscussionControl $control */
        $control = $this->discussionControlFactory->create();

        $control->setTeamId($this->session->getSection('team')->teamId);
        $control->setTeamName($this->session->getSection('team')->teamName);
        $control->setThread(\DiscussionControl::CIPHER_THREAD_PREFIX . '_' . $this->getParameter('year') . '_' . $this->getParameter('checkpoint'));

        return $control;
    }
}