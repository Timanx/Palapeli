<?php
namespace App\Presenters;

use App\Models\UpdatesModel;
use App\Models\YearsModel;
use Nette;


class InfoPresenter extends BasePresenter
{
    /** @var UpdatesModel */
    private $updatesModel;
    /** @var  YearsModel */
    private $yearsModel;

    public function __construct(UpdatesModel $updatesModel, YearsModel $yearsModel)
    {
        $this->updatesModel = $updatesModel;
        $this->yearsModel = $yearsModel;
    }

    public function renderDefault()
    {
        parent::render();
        $this->prepareHeading('Aktuality');
        $this->updatesModel->setYear($this->selectedYear);
        $data = $this->updatesModel->getUpdates();
        $this->template->updates = $data;

    }

    public function renderRules()
    {
        parent::render();
        $this->template->yearData = $this->yearsModel->getCurrentYearData();

        $this->prepareHeading('Pravidla');
    }

    public function renderEquipment()
    {
        parent::render();
        $this->prepareHeading('Doporučená výbava');
    }
}