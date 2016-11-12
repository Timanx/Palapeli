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
    }

    protected function createComponentDiscussion() {
        return new \DiscussionControl($this->database, $this->session->getSection('team')->teamId, $this->session->getSection('team')->teamName, \DiscussionControl::MAIN_THREAD);
    }
}