<?php
namespace App\Presenters;

use App\Models\CiphersModel;
use App\Models\FilesModel;
use App\Models\ReportsModel;
use App\Models\ResultsModel;
use App\Models\TeamsModel;
use App\Models\UpdatesModel;
use App\Models\YearsModel;
use DiscussionControl;
use Nette;
use Nette\Application\UI;
use Nette\Http\FileUpload;


class AdministrationPresenter extends BasePresenter
{

    /** @var  YearsModel */
    private $yearsModel;
    /** @var  TeamsModel */
    private $teamsModel;
    /** @var  UpdatesModel */
    private $updatesModel;
    /** @var  ReportsModel */
    private $reportsModel;
    /** @var  ResultsModel */
    private $resultsModel;
    /** @var  CiphersModel */
    private $ciphersModel;
    /** @var  FilesModel */
    private $filesModel;
    /** @var  \IDiscussionControlFactory */
    private $discussionControlFactory;

    public function __construct(\IDiscussionControlFactory $discussionControlFactory,
                                YearsModel $yearsModel,
                                TeamsModel $teamsModel,
                                ReportsModel $reportsModel,
                                ResultsModel $resultsModel,
                                UpdatesModel $updatesModel,
                                CiphersModel $ciphersModel,
                                FilesModel $filesModel)
    {
        $this->discussionControlFactory = $discussionControlFactory;
        $this->yearsModel = $yearsModel;
        $this->teamsModel = $teamsModel;
        $this->updatesModel = $updatesModel;
        $this->resultsModel = $resultsModel;
        $this->reportsModel = $reportsModel;
        $this->ciphersModel = $ciphersModel;
        $this->filesModel = $filesModel;
    }

    public function renderDefault()
    {
        parent::render();
        $this->prepareHeading('Přidání aktuality');
    }

    public function renderTeamCard($teamId = null)
    {
        parent::render();
        $this->prepareHeading('Karta týmu');

        $this->yearsModel->setYear($this->selectedYear);

        $this->template->checkpointCount = $this->yearsModel->getCheckpointCount();
        $this->template->teamName = $this->teamsModel->getTeamName($teamId);
        $this->template->teamId = $teamId;
    }

    public function renderCheckpointCard($checkpoint = null)
    {
        parent::render();
        $this->prepareHeading('Karta stanoviště');

        $this->teamsModel->setYear($this->selectedYear);
        $this->yearsModel->setYear($this->selectedYear);

        $this->template->teamsCount = $this->teamsModel->getTeamsCount();
        $this->template->checkpointCount = $this->yearsModel->getCheckpointCount();
        $this->template->checkpoint = $checkpoint;
    }

    public function renderCiphers($checkpoint = null)
    {
        parent::render();
        $this->prepareHeading('Vkládání šifer');

        $this->yearsModel->setYear($this->selectedYear);

        $this->template->checkpointCount = $this->yearsModel->getCheckpointCount();
        $this->template->checkpoint = $checkpoint;
    }

    public function renderDiscussion()
    {
        parent::render();
        $this->prepareHeading('Kompletní diskuse');
    }

    public function renderChat()
    {
        parent::render();
        $this->prepareHeading('Organizátorský chat');
    }

    public function renderPayments()
    {
        parent::render();
        $this->prepareHeading('Platba startovného');
    }

    public function renderPaymentMail()
    {
        parent::render();
        $this->prepareHeading('E-maily týmů s nezaplaceným startovným');

        $this->teamsModel->setYear($this->selectedYear);

        $this->template->emails = $this->teamsModel->getUnpaidTeamsData();
    }

    public function renderNoDataMail()
    {
        parent::render();
        $this->prepareHeading('E-maily týmů bez zapsaných výsledků');

        $this->teamsModel->setYear($this->selectedYear);

        $this->template->emails = $this->teamsModel->getUnfilledTeamsData();
    }

    public function renderTeamMail()
    {
        parent::render();
        $this->prepareHeading('E-maily na týmy');

        $this->teamsModel->setYear($this->selectedYear);

        $this->template->emails = $this->teamsModel->getPlayingTeamsEmails();
    }

    public function renderTeamTable()
    {
        parent::render();
        $this->prepareHeading('Tabulka údajů o týmech');

        $this->teamsModel->setYear($this->selectedYear);

        $this->teamsModel->getPlayingTeams();
    }

    protected function createComponentDiscussion() {

        /** @var DiscussionControl $control */
        $control = $this->discussionControlFactory->create();

        $control->setTeamId($this->session->getSection('team')->teamId);
        $control->setTeamName($this->session->getSection('team')->teamName);
        $control->setThread(\DiscussionControl::ANY_THREAD);

        return $control;
    }

    protected function createComponentChat() {

        /** @var DiscussionControl $control */
        $control = $this->discussionControlFactory->create();

        $control->setTeamId($this->session->getSection('team')->teamId);
        $control->setTeamName($this->session->getSection('team')->teamName);
        $control->setThread(\DiscussionControl::CHAT_THREAD);

        return $control;
    }

    public function createComponentNewUpdateForm()
    {
        $form = new UI\Form;
        $form->addTextArea('message', 'Text aktuality:', null, 5);
        $form->addText('date', 'Datum:')
            ->setType('date')
            ->setDefaultValue(date('Y-m-d', time()))
            ->setRequired();
        $form->addText('year', 'Ročník:')
            ->setType('number')
            ->setDefaultValue($this->selectedYear)
            ->addRule(UI\Form::MIN, 'Hodnota ročníku musí být alespoň 1.', 1)
            ->setRequired();
        $form->addSubmit('send', 'PŘIDAT AKTUALITU');
        $form->onSuccess[] = [$this, 'newUpdateFormSucceeded'];
        return $form;
    }

    public function newUpdateFormSucceeded(UI\Form $form, array $values)
    {
        if(!isset($this->selectedYear)) {
            $this->getYearData();
        }

        $this->updatesModel->setYear($this->selectedYear);

        $this->updatesModel->addUpdate($values['date'], $values['message']);

        $this->flashMessage('Aktualita byla úspěšně vložena.', 'success');
        $this->redirect('this');
    }

    public function createComponentSelectTeamForm()
    {
        if(!isset($this->selectedYear)) {
            $this->getYearData();
        }

        $teamId = (isset($_GET['team']) ? $_GET['team'] : null);

        $this->resultsModel->setYear($this->selectedYear);

        $teams = $this->resultsModel->getTeamsWithFilledStatus();

        $options = ['Nevyplněné týmy' => [], 'Vyplněné týmy' => []];
        foreach ($teams as $team) {
            $options[(!$team->team_filled ? 'Nevyplněné týmy' : 'Vyplněné týmy')][$team->id] = $team->name;
        }

        $form = new UI\Form;
        $form->addSelect('teams', null, $options, 1)->setPrompt('Vyberte tým')->setAttribute('onchange', 'this.form.submit()');
        $form->onSuccess[] = [$this, 'teamSelected'];
        return $form;
    }

    public function teamSelected(UI\Form $form, array $values)
    {
        $this->redirect('this', ['team' => $values['teams']]);
    }

    public function createComponentTeamCardForm()
    {
        $teamId = $_GET['team'];
        if(!isset($this->selectedYear)) {
            $this->getYearData();
        }

        $this->resultsModel->setYear($this->selectedYear);
        $teamId->yearsModel->setYear($this->selectedYear);

        $results = $this->resultsModel->getTeamResults($teamId);
        $yearData = $teamId->yearsModel->getYearData();

        $form = new UI\Form;

        for ($i = 0; $i < $yearData->checkpointCount; $i++) {
            $checkpoint = $form->addContainer('checkpoint' . $i);
            $checkpoint->addText('entryTime', ($i == 0 ? 'Začátek hry:' : ($i == $yearData->checkpointCount-1 ? 'Příchod do cíle:' : 'Příchod na ' . $i . '. stanoviště:')))->setType('time')->setDefaultValue((isset($results[$i]) && isset($results[$i]['entry_time']) ? $results[$i]['entry_time'] : ($i == 0 && isset($yearData->game_start) ? $yearData->game_start : self::EMPTY_TIME_VALUE)));
            $checkpoint->addText('exitTime', ($i == 0 ? 'Odchod ze startu:' : ($i == $yearData->checkpointCount-1 ? 'Vyřešení cílového hesla:' : 'Odchod z ' . $i . '. stanoviště:')))->setType('time')->setDefaultValue((isset($results[$i]) && isset($results[$i]['exit_time']) ? $results[$i]['exit_time'] : self::EMPTY_TIME_VALUE));;
            if($i != $yearData->checkpointCount-1) {
                $checkpoint->addCheckbox('usedHint')->setDefaultValue((isset($results[$i]) && isset($results[$i]['used_hint']) ? $results[$i]['used_hint'] : 0))->setRequired(false);
            }
        }

        $form->addSubmit('send', 'ODESLAT KARTU TÝMU');
        $form->onSuccess[] = [$this, 'teamCardFormSucceeded'];
        return $form;
    }

    public function teamCardFormSucceeded(UI\Form $form, array $values)
    {
        if(!isset($this->selectedYear)) {
            $this->getYearData();
        }

        $this->resultsModel->setYear($this->selectedYear);

        $teamId = $_GET['team'];
        foreach($values as $number => $checkpoint) {
            $number = substr($number,10);

            if  (   isset($checkpoint['usedHint']) &&
                    $checkpoint['usedHint']
                 ||
                    $checkpoint['exitTime'] != '' &&
                    $checkpoint['exitTime'] != self::EMPTY_TIME_VALUE
                 ||
                    $checkpoint['entryTime'] != '' &&
                    $checkpoint['entryTime'] != self::EMPTY_TIME_VALUE


            ) {
                if($checkpoint['exitTime'] == self::EMPTY_TIME_VALUE || strlen($checkpoint['exitTime']) == 0) {
                    $checkpoint['exitTime'] = NULL;
                }                
                if($checkpoint['entryTime'] == self::EMPTY_TIME_VALUE || strlen($checkpoint['entryTime']) == 0) {
                    $checkpoint['entryTime'] = NULL;
                }

                $this->resultsModel->insertResultsRow($teamId, $number, $checkpoint['entryTime'], $checkpoint['exitTime'], $checkpoint['usedHint']);
            }

            //Handle finish
            if ($number == count($values) - 1 && $checkpoint['exitTime'] != '' && $checkpoint['exitTime'] != self::EMPTY_TIME_VALUE) {

                $this->resultsModel->insertResultsRow($teamId, ((int)$number + 1), $checkpoint['exitTime'],$checkpoint['exitTime']);
            }
        }

        $this->flashMessage('Údaje z karty týmu byly úspěšně uloženy', 'success');
        $this->redirect('this');
    }


    public function createComponentSelectCheckpointForm()
    {
        $checkpoint = (isset($_GET['checkpoint']) ? $_GET['checkpoint'] : null);
        if(!isset($this->selectedYear)) {
            $this->getYearData();
        }

        $this->yearsModel->setYear($this->selectedYear);

        $checkpointCount = $this->yearsModel->getCheckpointCount();

        $options = [];
        for ($i = 0; $i <= $checkpointCount; $i++){
            $options[$i] = ($i == $checkpointCount - 1 ? 'Příchod do cíle' : ($i == $checkpointCount ? 'Vyřešení cílového hesla' : ($i == 0 ? 'Start' : $i . '. stanoviště')));
        }

        array_unshift($options, 'Vyberte stanoviště');

        $form = new UI\Form;

        $form->addCheckbox('previous', 'Řadit týmy podle příchodu na předchozí stanoviště')->setAttribute('onchange', 'this.form.submit()');
        $form->addSelect('checkpoint', '', $options, 1)->setAttribute('onchange', 'this.form.submit()');
        $form->addHidden('currentCheckpoint', $checkpoint);
        $form->onSuccess[] = [$this, 'checkpointSelected'];
        return $form;
    }

    public function checkpointSelected(UI\Form $form, array $values)
    {
        if($values['previous']) {
            $checkpoint = $values['currentCheckpoint'];
        } elseif($values['checkpoint'] == 0) {
            $checkpoint = 0;
        } else {
            $checkpoint = $values['checkpoint'] - 1;
        }

        $this->redirect('this', ['checkpoint' => $checkpoint, 'previous' => $values['previous']]);
    }

    public function createComponentSelectOnlyCheckpointForm()
    {
        $checkpoint = (isset($_GET['checkpoint']) ? $_GET['checkpoint'] : null);
        if(!isset($this->selectedYear)) {
            $this->getYearData();
        }

        $this->yearsModel->setYear($this->selectedYear);

        $checkpointCount = $this->yearsModel->getCheckpointCount();

        $options = [];
        for ($i = 0; $i < $checkpointCount; $i++){
            $options[$i] = ($i == $checkpointCount - 1 ? 'Cíl' : ($i == 0 ? 'Start' : $i . '. stanoviště'));
        }
        array_unshift($options, 'Vyberte stanoviště');


        $form = new UI\Form;
        $form->addSelect('checkpoint', '', $options, 1)->setAttribute('onchange', 'this.form.submit()');
        $form->onSuccess[] = [$this, 'onlyCheckpointSelected'];
        return $form;
    }

    public function onlyCheckpointSelected(UI\Form $form, array $values)
    {
        $this->redirect('this', ['checkpoint' => ($values['checkpoint'] == 0 ? 0 : $values['checkpoint'] - 1)]);
    }

    public function createComponentCheckpointCardForm()
    {
        $checkpoint = $_GET['checkpoint'];
        $previous = array_key_exists('previous', $_GET) && $_GET['previous'];
        if(!isset($this->selectedYear)) {
            $this->getYearData();
        }
        $this->resultsModel->setYear($this->selectedYear);

        $data = $this->resultsModel->getCheckpointEntryTimes($checkpoint, (bool)$previous);

        $form = new UI\Form;

        for ($i = 0; $i < count($data); $i++) {
            $teamContainer = $form->addContainer('team' . $i);
            $teamName = $teamContainer->addText('entryTime', $data[$i]['name'])->setType('time')->setDefaultValue((isset($data[$i]['entry_time']) ? $data[$i]['entry_time'] : self::EMPTY_TIME_VALUE));
            if($checkpoint > 1 && !$data[$i]['visited_previous']) {
                $teamName->getLabelPrototype()->addAttributes(['class' => 'dead', 'title' => 'Tým nemá vyplněný příchod na předchozím stanovišti']);
            }
            $teamContainer->addHidden('teamId', $data[$i]['id']);
            $teamContainer->addButton('currentTime', 'Teď')->setAttribute('onclick', 'submitCurrentTime(' . $i . ', this.form)');
            $teamContainer->addButton('inputtedTime', 'Zadáno')->setAttribute('onclick', 'this.form.submit()');
        }
        $form->addSubmit('send', 'ODESLAT KARTU STANOVIŠTĚ');
        $form->onSuccess[] = [$this, 'checkpointCardFormSucceeded'];
        return $form;
    }

    public function checkpointCardFormSucceeded(UI\Form $form, array $values)
    {
        $checkpoint = $_GET['checkpoint'];

        if(!isset($this->selectedYear)) {
            $this->getYearData();
        }
        $this->yearsModel->setYear($this->selectedYear);
        $this->resultsModel->setYear($this->selectedYear);

        $checkpointCount = $this->yearsModel->getCheckpointCount();

        foreach($values as $team) {
            if(!isset($team['entryTime']) || $team['entryTime'] == '' || $team['entryTime'] == self::EMPTY_TIME_VALUE) {
                $team['entryTime'] = NULL;
            }

            $this->resultsModel->insertResultsRow($team['teamId'], $checkpoint, $team['entryTime']);

            if($checkpoint == $checkpointCount) {
                $this->resultsModel->insertResultsRow($team['teamId'], $checkpoint, NULL, $team['entryTime']);
            }
        }

        $this->flashMessage('Údaje z karty stanoviště byly úspěšně uloženy', 'success');
        $this->redirect('this');
    }


    public function createComponentCipherForm()
    {
        $checkpoint = $_GET['checkpoint'];
        $this->getYearData();

        $this->ciphersModel->setYear($this->selectedYear);
        $data = $this->ciphersModel->getCipher($checkpoint);

        $form = new UI\Form;
        $form->elementPrototype->addAttributes(['enctype' => 'multipart/form-data']);

        $form->addText('name', 'Název šifry', null, 255)->setDefaultValue(isset($data->name) ? $data->name : null);
        $form->addTextArea('cipher_description', 'Popis zadání')->setDefaultValue(isset($data->cipher_description) ? $data->cipher_description : null);
        $form->addTextArea('solution_description', 'Popis řešení')->setDefaultValue(isset($data->solution_description) ? $data->solution_description : null);
        $form->addText('solution', 'Řešení', null, 1023)->setDefaultValue(isset($data->solution) ? $data->solution : null);
        $form->addUpload('cipher_image', 'Obrázek šifry');
        $form->addUpload('solution_image', 'Obrázek řešení');
        $form->addUpload('pdf_file', 'PDF soubor se šifrou');
        $form->addSubmit('send', 'VLOŽIT ŠIFRU');
        $form->onSuccess[] = [$this, 'cipherFormSucceeded'];
        return $form;
    }

    private function uploadFile(FileUpload $file, $path,  $target_name) {
        if($file->getError() == UPLOAD_ERR_OK) {
            $target_file = $path . $target_name;

            $tmpFile = $file->getTemporaryFile();

            move_uploaded_file($tmpFile, $target_file);
            return $this->filesModel->insertFile($path, $target_name);
        }

        return null;
    }

    public function cipherFormSucceeded(UI\Form $form, array $values)
    {
        $checkpoint = $_GET['checkpoint'];

        if(!file_exists('cipher_images')) {
            mkdir('cipher_images');
        }

        if(!file_exists('cipher_images/' . $this->selectedYear)) {
            mkdir('cipher_images/' . $this->selectedYear);
        }

        $target_dir = 'cipher_images/' . $this->selectedYear . '/' . $checkpoint . '/';
        if(!file_exists($target_dir)) {
            mkdir($target_dir);
        }

        $checkpointString = $checkpoint;
        if($checkpoint < 10) {
            $checkpointString = '0' . $checkpoint;
        }

        $this->ciphersModel->setYear($this->selectedYear);
        $this->ciphersModel->setCheckpoint($checkpoint);

        $pattern = '~.*(\.[^\.]*)~';
        $replacement = 's' . $checkpointString . '$1';

        $cipher_image_id = $this->uploadFile($values['cipher_image'], $target_dir, preg_replace($pattern, 'img_' . $replacement, $values['cipher_image']->getName()));
        $solution_image_id = $this->uploadFile($values['solution_image'], $target_dir, preg_replace($pattern, 'sol_' . $replacement, $values['solution_image']->getName()));
        $pdf_file_id = $this->uploadFile($values['pdf_file'], $target_dir, preg_replace($pattern, 'pdf_' . $replacement, $values['pdf_file']->getName()));

        $this->ciphersModel->upsertCipher($checkpoint, $values['name'], $values['cipher_description'], $values['solution_description'], $values['solution']);

        if($values['solution_image']->getError() == UPLOAD_ERR_OK) {
            $this->ciphersModel->updateSolutionImage($solution_image_id);
        }

        if($values['cipher_image']->getError() == UPLOAD_ERR_OK) {
            $this->ciphersModel->updateCipherImage($cipher_image_id);
        }

        if($values['pdf_file']->getError() == UPLOAD_ERR_OK) {
            $this->ciphersModel->updatePDF($pdf_file_id);
        }

        $this->flashMessage('Šifra byla úspěšně vložena.', 'success');
        $this->redirect('this');
    }

    public function createComponentPayments()
    {
        $this->getYearData();
        $this->teamsModel->setYear($this->selectedYear);
        $teams = $this->teamsModel->getTeamsPaymentStatus();

        $form = new UI\Form;
        foreach ($teams as $team) {
            $form->addRadioList($team->id, $team->name, [0 => 'Nezaplaceno', 1 => 'Zaplaceno', 2 => 'Platba na startu'])->setDefaultValue($team->paid);
        }
        $form->addSubmit('send', 'UPRAVIT PLATBY');


        $form->onSuccess[] = [$this, 'editPayments'];
        return $form;
    }

    public function editPayments(UI\Form $form, array $values)
    {
        $this->teamsModel->setYear($this->selectedYear);
        foreach($values as $teamId => $paymentStatus) {
            $this->teamsModel->editTeamPayment($teamId, $paymentStatus);
        }

        $this->flashMessage('Platby byly úspěšně uloženy', 'success');
        $this->redirect('this');
    }

    public function renderResults()
    {
        parent::render();
        $this->prepareHeading('Výsledky');

        $this->resultsModel->setYear($this->selectedYear);

        $data = $this->resultsModel->getTeamStandings();

        $this->template->resultsPublic = $this->resultsModel->getResultsPublic();

        $this->template->data = $data;

    }

    public function createComponentPublishResults()
    {

        $this->getYearData();

        $form = new UI\Form;
        $form->addSubmit('send', 'PUBLIKOVAT VÝSLEDKY');


        $form->onSuccess[] = [$this, 'publishResults'];
        return $form;
    }

    public function publishResults()
    {
        $this->getYearData();

        $this->resultsModel->setYear($this->selectedYear);
        $this->resultsModel->publishResults();

        $this->flashMessage('Výsledky byly úspěšně zveřejněny', 'success');
        $this->redirect('this');
    }

    public function renderReports()
    {
        parent::render();
        $this->prepareHeading('Přidání reportáže');
    }

    public function createComponentAddReport()
    {
        $this->getYearData();

        $form = new UI\Form;

        $form->addText('year', 'Ročník*')->setType('number')->setDefaultValue($this->selectedYear)->setRequired();
        $form->addText('team', 'Autor (tým)*')->setRequired();
        $form->addText('name', 'Název repotráže');
        $form->addText('link', 'Odkaz na reportáž*');
        $form->addTextArea('description', 'Popis', 50, 10);
        $form->addSubmit('send', 'VLOŽIT REPORTÁŽ');

        $form->onSuccess[] = [$this, 'addReportSucceeded'];
        return $form;
    }

    public function addReportSucceeded(UI\Form $form, array $values)
    {
        $this->getYearData();

        $this->teamsModel->setYear($this->selectedYear);
        $teamId = $this->teamsModel->getTeamId($values['team']);

        if(!$teamId) {
            $form->addError('Tým se zadaným názvem neexistuje');
        }

        $this->reportsModel->insertReport($values['year'], $values['link'], $teamId, $values['name'], $values['description']);

        $this->flashMessage('Reportáž byla úspěšně přidána', 'success');
        $this->redirect('this');
    }

}