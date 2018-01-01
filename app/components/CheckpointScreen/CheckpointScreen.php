<?php

use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use App\Models\LogModel;
use App\Models\CiphersModel;
use Nette\Application\UI;

class CheckpointScreen extends BaseControl
{
    /** @var ResultsModel */
    private $resultsModel;
    /** @var YearsModel */
    private $yearsModel;
    /** @var TeamsModel */
    private $teamsModel;
    /** @var  LogModel */
    private $logModel;
    /** @var CiphersModel */
    private $ciphersModel;
    /** @var Nette\Http\Session */
    private $session;

    private $lastCheckpointData;
    private $defaultScreen;

    const END_CODE = 'JEZIMADEMDOM';

    const DEAD_SCREEN = 0;
    const END_SCREEN = 1;


    public function __construct(
        ResultsModel $resultsModel,
        YearsModel $yearsModel,
        TeamsModel $teamsModel,
        CiphersModel $ciphersModel,
        LogModel $logModel,
        \Nette\Http\Session $session
    )
    {
        parent::__construct();
        $this->resultsModel = $resultsModel;
        $this->yearsModel = $yearsModel;
        $this->teamsModel = $teamsModel;
        $this->ciphersModel = $ciphersModel;
        $this->logModel = $logModel;
        $this->session = $session;
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . '/checkpointScreen.latte');

        $this->teamsModel->setYear($this->year);
        $this->resultsModel->setYear($this->year);
        $this->yearsModel->setYear($this->year);

        $checkpointNumber = $this->resultsModel->getLastCheckpointNumber($this->teamId);

        if ($this->teamsModel->hasTeamEnded($this->teamId)) {
            $this->template->teamEnded = true;
            $data = $this->yearsModel->getEndgameData();
            if ($checkpointNumber > $data->checkpoint_count) {
                $this->flashMessage('Hru jste úspěšně dokončili! Gratulujeme.', 'success');
            } else {
                $this->flashMessage('Již jste ukončili hru a ve hře tak nemůžete pokračovat.');
                $this->flashMessage(sprintf('Pozice cíle: %s (otevřen od %s)', $data->finish_location, $data->finish_open_time), 'info');
            }
            if ($data->afterparty_location !== null) {
                $this->flashMessage(sprintf('Místo konání afterparty: %s (od %s)', $data->afterparty_location, $data->afterparty_time), 'info');
            }
        }

        $this->template->checkpointData = ($checkpointNumber !== false ? $this->resultsModel->getCheckpointEntryTimes($checkpointNumber, false, true) : null);
        $this->template->checkpointCount = $this->yearsModel->getCheckpointCount();

        $this->template->checkpointNumber = $checkpointNumber;
        $this->template->current = $this->teamId;

        $this->template->render();
    }
}
