<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Models\UpdatesModel;


/**
 * Section "Info": news, rules and recommended equipment.
 */
final class InfoPresenter extends BasePresenter
{
	public function __construct(
		private readonly UpdatesModel $updatesModel,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$this->prepareHeading('Aktuality');
		$this->template->updates = $this->updatesModel->getUpdates($this->selectedYear);
	}


	public function renderRules(): void
	{
		$this->prepareHeading('Pravidla');
		$this->template->yearData = $this->yearsModel->getCurrentYearData();
	}


	public function renderEquipment(): void
	{
		$this->prepareHeading('Doporučená výbava');
	}
}
