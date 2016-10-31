<?php
namespace App\Presenters;

use Nette\Application\UI;
use Nette;


class DiscussionPresenter extends BasePresenter
{

    /** @var Nette\Database\Context */
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function renderDefault()
    {
        parent::render();
        $this->prepareHeading('Diskuse');
        $data = $this->database->query('
            SELECT d.*, COALESCE(teams.name, d.unlogged_team_name) AS team_name
            FROM discussion d
            LEFT JOIN teams ON teams.id = d.team_id
            WHERE thread = ?
            ORDER BY created DESC',
            'main'
        )->fetchAll();

        $this->template->data = $data;
    }

    protected function createComponentDiscussionForm($thread)
    {
        $teamName = $this->session->getSection('team')->teamName;
        $form = new UI\Form;
        $form->addText('name', 'Jméno:')->setRequired('Zadejte prosím své jméno.')->addRule(UI\Form::MAX_LENGTH, 'Jméno může mít maximálně 255 znaků', 255);
        if(!empty($teamName)) {
            $form->addText('team', 'Tým:')->setDisabled()->setDefaultValue($teamName);
        } else {
            $form->addText('team', 'Tým:')->setRequired(false)->addRule(UI\Form::MAX_LENGTH, 'Název týmu může mít maximálně 255 znaků', 255);
        }
        $form->addTextArea('message', 'Zpráva:')->setRequired('Nelze odeslat prázdnou zprávu.')->setAttribute('rows', 5)->addRule(UI\Form::MAX_LENGTH, 'Zpráva může mít maximálně 5000 znaků', 5000);
        $form->addHidden('thread', $thread);
        $form->addSubmit('submit', 'ODESLAT');
        $form->onSuccess[] = [$this, 'discussionFormSucceeded'];
        return $form;
    }

    public  function discussionFormSucceeded(UI\Form $form, array $values) {
        $teamId = $this->session->getSection('team')->teamId;

        $message = $values['message'];
        $message = strip_tags($message, 'a');
        $message = nl2br($message);

        $name = $values['name'];
        $name = strip_tags($name);

        $team = (isset($values['team']) ? $values['team'] : '');
        $team = strip_tags($team);


        $this->database->query('
            INSERT INTO discussion (name, team_id, unlogged_team_name, created, message, thread) VALUES (?, ?, ?, ?, ?, ?)
        ', $name, $teamId, (!isset($teamId) && strlen($team) > 0 ? $team : NULL),  date('Y-m-d H:i:s', time()), $message, 'main');


        $this->flashMessage('Příspěvek do diskuse byl úspěšně odeslán.', 'success');
        $this->redirect('this');
    }
}