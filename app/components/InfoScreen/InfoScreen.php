<?php

use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\YearsModel;
use App\Models\LogModel;
use App\Models\CiphersModel;
use Nette\Application\UI;

class InfoScreen extends BaseControl
{
    /** @var  LogModel */
    private $logModel;
    /** @var CiphersModel */
    private $ciphersModel;
    /** @var YearsModel */
    private $yearsModel;


    public function __construct(
        LogModel $logModel,
        CiphersModel $ciphersModel,
        YearsModel $yearsModel
    ) {
        parent::__construct();
        $this->logModel = $logModel;
        $this->ciphersModel = $ciphersModel;
        $this->yearsModel = $yearsModel;
    }

    public function render()
    {

        $this->template->setFile(__DIR__ . '/infoScreen.latte');

        $this->logModel->setYear($this->year);
        $this->ciphersModel->setYear($this->year);
        $this->yearsModel->setYear($this->year);

        $data = $this->logModel->getLogsForTeam($this->teamId);

        $flashes = [];

        foreach ($data as $row) {
            $message = $row->log_time . ' - ';
            $type = 'info';
            if ($row->message !== null) {
                $message .= $row->message;
            } else {
                switch ($row->type_id) {
                    case LogModel::LT_END_GAME:
                        $message .= 'Ukončili jste hru.';
                        break;
                    case LogModel::LT_OPEN_DEAD:
                        $solution = $this->ciphersModel->getDeadSolution($row->checkpoint_number);

                        $message .= sprintf('Otevřeli jste totálku na stanovišti&nbsp;%s. Znění: %s', $row->checkpoint_number, $solution);
                        break;
                    case LogModel::LT_ENTER_CHECKPOINT:
                        $checkpointCount = $this->yearsModel->getCheckpointCount();

                        if ($row->checkpoint_number == 0) {
                            $message .= sprintf('Zadali jste kód startovní šifry.');
                            if ($this->year == 10) {
                                $message .= sprintf('První šifra se nachází v Lelekovicích u pumptracku, v dutině křoví asi 10 metrů jižně od dřevěné sochy. Upřesnítka k dalším šifrám naleznete v záložce Karta.');
                            }
                        } elseif ($row->checkpoint_number == $checkpointCount - 1) {
                            $message .= sprintf('Přišli jste do cíle.');
                        } elseif ($row->checkpoint_number == $checkpointCount) {
                            $message .= sprintf('Dokončili jste hru! Gratulujeme!');
                        } else {
                            $message .= sprintf('Přišli jste na stanoviště číslo&nbsp;%s.', $row->checkpoint_number);
                        }

                        $type = 'success';
                        break;
                    case LogModel::LT_GAME_START:
                        $message .= sprintf('Hra začala.');
                        break;
                    default:
                        break;
                }
            }

            $flashes[] = $this->flashMessage($message, $type);

        }

        $this->template->customFlashes = $flashes;


        $this->template->render();

    }

}
