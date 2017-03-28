<?php
namespace App\Presenters;

use App\Models\UpdatesModel;
use Nette;


class InfoPresenter extends BasePresenter
{
    /** @var UpdatesModel */
    private $updatesModel;

    public function __construct(UpdatesModel $updatesModel)
    {
        $this->updatesModel = $updatesModel;
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
        $this->prepareHeading('Pravidla');
    }

    public function renderEquipment()
    {
        parent::render();
        $this->prepareHeading('Doporučená výbava');
    }
}