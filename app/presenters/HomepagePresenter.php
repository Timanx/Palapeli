<?php

namespace App\Presenters;

use App\Models\YearsModel;
use Nette;

class HomepagePresenter extends BasePresenter
{
    /** @var YearsModel $yearsModel */
    private $yearsModel;
    public function __construct(YearsModel $yearsModel)
    {
        parent::__construct();
        $this->yearsModel = $yearsModel;
    }

    public function renderDefault()
    {
        //reset previously selected years
        $this->session->getSection('selected')->year = $this->yearsModel->getCurrentYearNumber();
        parent::render();
        $this->template->hideMenu = true;
    }
}
