<?php
use Nette\Application\UI\Control;
use Nette\Application\UI;

class DiscussionControl extends Control
{

    const MAIN_THREAD = 'main';
    const CIPHER_THREAD_PREFIX = 'sifry';
    const CHAT_THREAD = 'chat';

    const ANY_THREAD = 'any';

    /** @var Nette\Database\Context */
    private $database;
    private $thread;
    private $teamId;
    private $teamName;

    private $threads;


    public function __construct(Nette\Database\Context $database, $teamId = null, $teamName = null, $thread = self::MAIN_THREAD)
    {
        $this->database = $database;
        $this->thread = $thread;
        $this->teamName = $teamName;
        $this->teamId = $teamId;
    }

    public function render()
    {
        $template = $this->template;
        $template->setFile(__DIR__ . '/discussion.latte');
        if($this->thread == self::ANY_THREAD) {
            $data = $this->database->query('
            SELECT d.*, COALESCE(teams.name, d.unlogged_team_name) AS team_name
            FROM discussion d
            LEFT JOIN teams ON teams.id = d.team_id
            ORDER BY created DESC'
            )->fetchAll();
        } else {
            $data = $this->database->query('
            SELECT d.*, COALESCE(teams.name, d.unlogged_team_name) AS team_name
            FROM discussion d
            LEFT JOIN teams ON teams.id = d.team_id
            WHERE thread = ?
            ORDER BY created DESC',
                $this->thread
            )->fetchAll();
        }

        $template->data = $data;
        $template->isMasterDiscussion = $this->thread == self::ANY_THREAD;
        $template->render();
    }

    protected function createComponentDiscussionForm()
    {
        $this->threads = array_keys($this->database->query('SELECT MAX(created) AS created, thread
            FROM discussion
            GROUP BY thread
            ORDER BY created DESC')->fetchAssoc('thread')
        );

        $form = new UI\Form;
        $form->addText('name', 'Jméno:')->setRequired('Zadejte prosím své jméno.')->addRule(UI\Form::MAX_LENGTH, 'Jméno může mít maximálně 255 znaků', 255);
        if(!empty($this->teamName)) {
            $form->addText('team', 'Tým:')->setDisabled()->setDefaultValue($this->teamName);
        } else {
            $form->addText('team', 'Tým:')->setRequired(false)->addRule(UI\Form::MAX_LENGTH, 'Název týmu může mít maximálně 255 znaků', 255);
        }
        $form->addTextArea('message', 'Zpráva:')->setRequired('Nelze odeslat prázdnou zprávu.')->setAttribute('rows', 5)->addRule(UI\Form::MAX_LENGTH, 'Zpráva může mít maximálně 5000 znaků', 5000);
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
        $message = strip_tags($message, 'a');
        $message = nl2br($message);

        $name = $values['name'];
        $name = strip_tags($name);

        $team = (isset($values['team']) ? $values['team'] : '');
        $team = strip_tags($team);

        if(isset($values['masterThread'])) {
            $thread = $this->threads[$values['masterThread']];
        } else {
            $thread = $values['thread'];
        }

        $this->database->query('
            INSERT INTO discussion (name, team_id, unlogged_team_name, created, message, thread) VALUES (?, ?, ?, ?, ?, ?)
        ', $name, $this->teamId, (!isset($teamId) && strlen($team) > 0 ? $team : NULL),  date('Y-m-d H:i:s', time()), $message, $thread);


        $this->flashMessage('Příspěvek do diskuse byl úspěšně odeslán.', 'success');
        $this->redirect('this');
    }

}