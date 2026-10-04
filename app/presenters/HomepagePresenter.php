<?php

declare(strict_types=1);

namespace App\Presenters;


/**
 * Landing page with the puzzle shaped navigation.
 */
final class HomepagePresenter extends BasePresenter
{
	public function actionDefault(): void
	{
		// coming back to the homepage resets the browsed edition to the current one
		$current = $this->yearsModel->getCurrentYearData();
		$this->selectYear((int) $current->year, (int) $current->calendar_year);
	}


	public function renderDefault(): void
	{
		$this->template->hideMenu = true;
	}
}
