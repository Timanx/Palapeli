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


    public function __construct(
        LogModel $logModel,
        CiphersModel $ciphersModel
    )
    {
        parent::__construct();
        $this->logModel = $logModel;
        $this->ciphersModel = $ciphersModel;
    }

    public function render()
    {

        $this->template->setFile(__DIR__ . '/infoScreen.latte');

        $this->logModel->setYear($this->year);
        $this->ciphersModel->setYear($this->year);

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
                        $message .= sprintf('Přišli jste na stanoviště číslo&nbsp;%s.', $row->checkpoint_number);
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
