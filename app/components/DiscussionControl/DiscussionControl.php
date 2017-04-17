<?php
use Nette\Application\UI;
use App\Models\TeamsModel;
use App\Models\DiscussionModel;

class DiscussionControl extends BaseControl
{
    const MAIN_THREAD = 'main';
    const CIPHER_THREAD_PREFIX = 'sifry';
    const CHAT_THREAD = 'chat';

    const ANY_THREAD = 'any';

    /** @var TeamsModel */
    private $teamsModel;
    /** @var DiscussionModel */
    private $discussionModel;
    private $thread;
    private $teamId;
    private $teamName;

    private $threads;


    public function __construct(TeamsModel $teamsModel, DiscussionModel $discussionModel)
    {
        parent::__construct();
        $this->teamsModel = $teamsModel;
        $this->discussionModel = $discussionModel;
    }

    public function setThread($thread)
    {
        $this->thread = $thread;
    }

    public function setTeamId($teamId)
    {
        $this->teamId = $teamId;
    }

    public function setTeamName($teamName)
    {
        $this->teamName = $teamName;
    }

    public function render()
    {
        $template = $this->template;
        $template->setFile(__DIR__ . '/discussion.latte');
        if($this->thread == self::ANY_THREAD) {
            $data = $this->discussionModel->getAll();
        } else {
            $data = $this->discussionModel->getAllByThread($this->thread);
        }

        $template->data = $data;
        $template->requireCaptcha = !isset($this->teamId);
        $template->isMasterDiscussion = ($this->thread == self::ANY_THREAD);
        $template->render();
    }

    protected function createComponentDiscussionForm()
    {
        $this->threads = $this->discussionModel->getThreads();

        $form = new UI\Form;
        $form->addText('name', 'Jméno:')->setRequired('Zadejte prosím své jméno.')->addRule(UI\Form::MAX_LENGTH, 'Jméno může mít maximálně 255 znaků', 255);
        if(!empty($this->teamName)) {
            $form->addText('team', 'Tým:')->setDisabled()->setDefaultValue($this->teamName);
        } else {
            $form->addText('team', 'Tým:')->setRequired(false)->addRule(UI\Form::MAX_LENGTH, 'Název týmu může mít maximálně 255 znaků', 255);
        }
        $form->addTextArea('message', 'Zpráva:')->setRequired('Nelze odeslat prázdnou zprávu.')->setAttribute('rows', 5)->addRule(UI\Form::MAX_LENGTH, 'Zpráva může mít maximálně 5000 znaků', 5000);
        if (!isset($this->teamId)) {
            $form->addText('captcha', 'Počet dílků puzzle na logu Palapeli:')->setRequired('Vyplňte prosím kontrolní otázku proti spamu.');
        }
        if($this->thread == self::ANY_THREAD) {
            $form->addSelect('masterThread', 'Vlákno', $this->threads);
        }
        $form->addHidden('thread', $this->thread);
        $form->addSubmit('submit', 'ODESLAT');
        $form->onSuccess[] = [$this, 'discussionFormSucceeded'];
        return $form;
    }

    public  function discussionFormSucceeded(UI\Form $form, array $values) {
        $message = $values['message'];
        if($this->teamId != \App\Presenters\BasePresenter::ORG_TEAM_ID) {
            $message = strip_tags($message, 'a');
        }
        $message = nl2br($message);

        $name = $values['name'];
        $name = strip_tags($name);

        $team = (isset($values['team']) ? $values['team'] : '');
        $team = strip_tags($team);

        if(isset($values['captcha']) && $values['captcha'] != 4) {
            $form->addError('Špatně vyplněná kontrolní otázka proti spamu.');
        } else {

            if (isset($values['masterThread'])) {
                $thread = $this->threads[$values['masterThread']];
            } else {
                $thread = $values['thread'];
            }

            $this->discussionModel->insertPost($message, $name, $this->teamId, $team, $thread);


            $this->flashMessage('Příspěvek do diskuse byl úspěšně odeslán.', 'success');
            $this->redirect('this');
        }
    }

}