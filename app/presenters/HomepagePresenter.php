<?php

namespace App\Presenters;

use Nette;


class HomepagePresenter extends BasePresenter
{
    public function renderDefault()
    {
        //reset previously selected years
        $this->session->getSection('selected')->year = self::CURRENT_YEAR;
        parent::render();
        $this->template->hideMenu = true;
    }
}
