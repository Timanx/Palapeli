<?php

declare(strict_types=1);

namespace App\Session;

use Nette\Http\Session;
use Nette\Http\SessionSection;


/**
 * Year (edition) of the game the visitor is browsing, stored in the session
 * (section "selected", keys "year" and "calendarYear").
 */
final class YearSelection
{
	private const Section = 'selected';


	public function __construct(
		private readonly Session $session,
	) {
	}


	public function getYear(): ?int
	{
		return $this->readInt('year');
	}


	public function getCalendarYear(): ?int
	{
		return $this->readInt('calendarYear');
	}


	public function select(int $year, int $calendarYear): void
	{
		$section = $this->section();
		$section->set('year', $year);
		$section->set('calendarYear', $calendarYear);
	}


	private function readInt(string $key): ?int
	{
		if (!$this->session->hasSection(self::Section)) {
			return null;
		}

		$value = $this->section()->get($key);
		return empty($value) ? null : (int) $value;
	}


	private function section(): SessionSection
	{
		return $this->session->getSection(self::Section);
	}
}
