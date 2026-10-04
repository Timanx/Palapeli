<?php

declare(strict_types=1);

namespace App\Presenters;

/**
 * Switches the browsed game edition and returns to the page the visitor came from.
 */
final class ArchivePresenter extends BasePresenter
{
	/**
	 * @param string $link  destination to return to, e.g. ":Game:results"
	 */
	public function actionDefault(int $year, ?int $calendarYear = null, string $link = 'Homepage:'): void
	{
		$this->selectYear($year, $calendarYear);

		// only absolute destinations (":Presenter:action") are accepted;
		// an invalid destination produces "#error: ..." instead of a URL
		$url = str_starts_with($link, ':') ? $this->link('//' . $link) : '#';
		$this->redirectUrl(str_starts_with($url, '#') ? $this->link('//Homepage:') : $url);
	}
}
