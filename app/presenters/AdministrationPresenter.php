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
use InfoScreen;
use UpdatesForm;
use YearForm;


class AdministrationPresenter extends BasePresenter
{

    /** @var  YearsModel */
    private $yearsModel;
    /** @var  TeamsModel */
    private $teamsModel;
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
    /** @var  \IUpdatesFormFactory */
    private $updatesFormFactory;
    /** @var  \IYearFormFactory */
    private $yearFormFactory;
    /** @var  \ITeamCardFactory */
    private $teamCardFactory;
    /** @var  \ICheckpointCardFactory */
    private $checkpointCardFactory;
    /** @var  \ITeamMessageFactory */
    private $teamMessageFactory;

    public function __construct(
        \IDiscussionControlFactory $discussionControlFactory,
        \IUpdatesFormFactory $updatesFormFactory,
        \IYearFormFactory $yearFormFactory,
        \ITeamCardFactory $teamCardFactory,
        \ICheckpointCardFactory $checkpointCardFactory,
        \ITeamMessageFactory $teamMessageFactory,
        YearsModel $yearsModel,
        TeamsModel $teamsModel,
        ReportsModel $reportsModel,
        ResultsModel $resultsModel,
        CiphersModel $ciphersModel,
        FilesModel $filesModel
    )
    {
        $this->discussionControlFactory = $discussionControlFactory;
        $this->updatesFormFactory = $updatesFormFactory;
        $this->yearFormFactory = $yearFormFactory;
        $this->teamCardFactory = $teamCardFactory;
        $this->checkpointCardFactory = $checkpointCardFactory;
        $this->teamMessageFactory = $teamMessageFactory;
        $this->yearsModel = $yearsModel;
        $this->teamsModel = $teamsModel;
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

    public function renderTeamCard()
    {
        parent::render();
        $this->prepareHeading('Karta týmu');
    }

    public function renderCheckpointCard($checkpoint = null, $previous = false)
    {
        parent::render();
        $this->prepareHeading('Karta stanoviště');

        $this->teamsModel->setYear($this->selectedYear);
        $this->yearsModel->setYear($this->selectedYear);

        /** @var \CheckpointCard $component */
        $component = $this->getComponent('checkpointCard');
        $component->setCheckpointNumber($checkpoint);
        $component->setOrderByPrevious($previous);

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

    public function renderTeamMail()
    {
        parent::render();
        $this->prepareHeading('E-maily na týmy');

        $this->teamsModel->setYear($this->selectedYear);

        $this->template->emails = $this->teamsModel->getPlayingTeamsEmails();
    }

    public function renderTeamTable($orderByRegistration = false)
    {
        parent::render();
        $this->prepareHeading('Tabulka údajů o týmech');

        $this->teamsModel->setYear($this->selectedYear);

        $this->template->data = ($orderByRegistration ? $this->teamsModel->getPlayingTeamsByRegistration() : $this->teamsModel->getPlayingTeams());
    }

    public function renderTeamMessage()
    {
        parent::render();
        $this->prepareHeading('Odeslat zprávu týmům');
    }

    public function renderYear($createNew = false)
    {
        parent::render();
        $this->prepareHeading('Správa ročníků');

        $component = $this->getComponent('yearForm');
        assert($component instanceof YearForm);
        $component->setYear($this->selectedYear);
        if ($createNew) {
            $component->createNew();
        }
    }

    public function renderWhereIsWho()
    {
        parent::render();
        $this->prepareHeading('Kde je kdo');
        $this->resultsModel->setYear($this->selectedYear);
        $this->template->data = $this->resultsModel->whereIsWho();

    }

    protected function createComponentDiscussion()
    {
        /** @var DiscussionControl $control */
        $control = $this->discussionControlFactory->create();

        $control->setTeamId($this->session->getSection('team')->teamId);
        $control->setTeamName($this->session->getSection('team')->teamName);
        $control->setThread(\DiscussionControl::ANY_THREAD);

        return $control;
    }

    protected function createComponentChat()
    {
        /** @var DiscussionControl $control */
        $control = $this->discussionControlFactory->create();

        $control->setTeamId($this->session->getSection('team')->teamId);
        $control->setTeamName($this->session->getSection('team')->teamName);
        $control->setThread(\DiscussionControl::CHAT_THREAD);

        return $control;
    }

    protected function createComponentUpdateForm()
    {
        parent::getYearData();

        /** @var UpdatesForm $control */
        $control = $this->updatesFormFactory->create();
        $control->setYear($this->selectedYear);
        return $control;
    }
    protected function createComponentYearForm()
    {
        /** @var YearForm $control */
        $control = $this->yearFormFactory->create();
        return $control;
    }

    protected function createComponentTeamCard()
    {
        parent::getYearData();

        /** @var \TeamCard $control */
        $control = $this->teamCardFactory->create();
        $control->setYear($this->selectedYear);
        return $control;
    }

    protected function createComponentCheckpointCard()
    {
        parent::getYearData();

        /** @var \CheckpointCard $control */
        $control = $this->checkpointCardFactory->create();
        $control->setYear($this->selectedYear);
        return $control;
    }

    protected function createComponentTeamMessage()
    {
        parent::getYearData();

        /** @var \TeamMessage $control */
        $control = $this->teamMessageFactory->create();
        $control->setYear($this->selectedYear);
        return $control;
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
        $form->addText('code', 'Kód do Palainfa', null, 255)->setDefaultValue(isset($data->code) ? $data->code : null);
        $form->addText('specification', 'Upřesnítko', null, 255)->setDefaultValue(isset($data->specification) ? $data->specification : null);
        $form->addText(
            'checkpoint_close_time',
            'Zavření stanoviště',
            null,
            5
        )->setDefaultValue(isset($data->checkpoint_close_time) ? sprintf('%s:%s', $data->checkpoint_close_time->h,$data->checkpoint_close_time->i) : null);
        $form->addUpload('cipher_image', 'Obrázek šifry');
        $form->addUpload('solution_image', 'Obrázek řešení');
        $form->addUpload('pdf_file', 'PDF soubor se šifrou');
        $form->addSubmit('send', 'VLOŽIT ŠIFRU');
        $form->onSuccess[] = [$this, 'cipherFormSucceeded'];
        return $form;
    }

    private function uploadFile(FileUpload $file, $path, $target_name)
    {
        if ($file->getError() == UPLOAD_ERR_OK) {
            $target_file = $path . $target_name;

            $tmpFile = $file->getTemporaryFile();

            move_uploaded_file($tmpFile, $target_file);
            return $this->filesModel->insertFile($path, $target_name);
        }

        return null;
    }

    public function createComponentSelectOnlyCheckpointForm()
    {
        if (!$this->selectedYear) {
            parent::getYearData();
        }

        $this->yearsModel->setYear($this->selectedYear);

        $checkpointCount = $this->yearsModel->getCheckpointCount();

        $options = [];
        for ($i = 0; $i < $checkpointCount; $i++) {
            $options[$i] = (($i == $checkpointCount - 1 ) ? 'Cíl' : ($i == 0 ? 'Start' : $i . '. stanoviště'));
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

    public function cipherFormSucceeded(UI\Form $form, array $values)
    {
        $checkpoint = $_GET['checkpoint'];

        if (!file_exists('cipher_images')) {
            mkdir('cipher_images');
        }

        if (!file_exists('cipher_images/' . $this->selectedYear)) {
            mkdir('cipher_images/' . $this->selectedYear);
        }

        $target_dir = 'cipher_images/' . $this->selectedYear . '/' . $checkpoint . '/';
        if (!file_exists($target_dir)) {
            mkdir($target_dir);
        }

        $checkpointString = $checkpoint;
        if ($checkpoint < 10) {
            $checkpointString = '0' . $checkpoint;
        }

        $this->ciphersModel->setYear($this->selectedYear);
        $this->ciphersModel->setCheckpoint($checkpoint);

        $pattern = '~.*(\.[^\.]*)~';
        $replacement = 's' . $checkpointString . '$1';

        $cipher_image_id = $this->uploadFile($values['cipher_image'], $target_dir, preg_replace($pattern, 'img_' . $replacement, $values['cipher_image']->getName()));
        $solution_image_id = $this->uploadFile($values['solution_image'], $target_dir, preg_replace($pattern, 'sol_' . $replacement, $values['solution_image']->getName()));
        $pdf_file_id = $this->uploadFile($values['pdf_file'], $target_dir, preg_replace($pattern, 'pdf_' . $replacement, $values['pdf_file']->getName()));

        $this->ciphersModel->upsertCipher($checkpoint, $values['name'], $values['cipher_description'], $values['solution_description'], $values['solution'], $values['code'], $values['specification'], $values['checkpoint_close_time']);

        if ($values['solution_image']->getError() == UPLOAD_ERR_OK) {
            $this->ciphersModel->updateSolutionImage($solution_image_id);
        }

        if ($values['cipher_image']->getError() == UPLOAD_ERR_OK) {
            $this->ciphersModel->updateCipherImage($cipher_image_id);
        }

        if ($values['pdf_file']->getError() == UPLOAD_ERR_OK) {
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
        foreach ($values as $teamId => $paymentStatus) {
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

    public function createComponentRemoveTrailingHints()
    {

        $this->getYearData();

        $form = new UI\Form;
        $form->addSubmit('send', 'ODSTRANIT TOTÁLKY TÝMŮ, KTERÉ NEDOŠLY NA DALŠÍ STANOVIŠTĚ');


        $form->onSuccess[] = [$this, 'removeTrailingHints'];
        return $form;
    }

    public function removeTrailingHints()
    {
        $this->getYearData();

        $this->resultsModel->setYear($this->selectedYear);
        $this->yearsModel->setYear($this->selectedYear);

        if (!$this->yearsModel->hasGameEnded()) {
            $this->flashMessage('Hra ještě neskončila, tato akce by znehodnotila výsledky.', 'error');
        } else {
            $this->resultsModel->removeTrailingHints();
            $this->flashMessage('Totálky byly úspěšně odstraněny', 'success');
        }
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

        if (!$teamId) {
            $form->addError('Tým se zadaným názvem neexistuje');
        }

        $this->reportsModel->insertReport($values['year'], $values['link'], $teamId, $values['name'], $values['description']);

        $this->flashMessage('Reportáž byla úspěšně přidána', 'success');
        $this->redirect('this');
    }

}
