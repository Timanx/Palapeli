<?php
namespace App\Presenters;

use DiscussionControl;
use Nette\Application\UI;
use Nette;


class DiscussionPresenter extends BasePresenter
{

    /** @var Nette\Database\Context */
    private $database;
    /** @var  \IDiscussionControlFactory */
    private $discussionControlFactory;

    public function __construct(Nette\Database\Context $database, \IDiscussionControlFactory $discussionControlFactory)
    {
        parent::__construct();
        $this->database = $database;
        $this->discussionControlFactory = $discussionControlFactory;
    }

    public function renderDefault()
    {
        parent::render();
        $this->prepareHeading('Diskuse');
    }

    protected function createComponentDiscussion() {

        /** @var DiscussionControl $control */
        $control = $this->discussionControlFactory->create();

        $control->setTeamId($this->session->getSection('team')->teamId);
        $control->setTeamName($this->session->getSection('team')->teamName);
        $control->setThread(\DiscussionControl::MAIN_THREAD);

        return $control;
    }
}