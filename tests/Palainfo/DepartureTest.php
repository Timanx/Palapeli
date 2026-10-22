<?php

declare(strict_types=1);

namespace Tests\Palainfo;

use Tests\Support\Game;
use Tests\Support\PalainfoTestCase;
use Tests\Support\Team;


/**
 * Departure from a checkpoint: the typed time, "TEĎ", corrections and the times that are refused.
 * Typed times are relative to the current time, so that the tests pass at any time of day (also around midnight).
 */
final class DepartureTest extends PalainfoTestCase
{
	private Game $game;
	private Team $team;


	protected function setUp(): void
	{
		parent::setUp();
		$this->game = Game::createDefault($this->db);
		$this->team = $this->game->addTeam();
		$this->team->play(0, 0);
		$this->team->enterCode('KOD1X');
	}


	public function testNow(): void
	{
		$page = $this->team->akce();
		self::assertSame('--:--', $page->getForm('exitTimeInput')?->querySelector('input[name="exitTime"]')?->getAttribute('value'));

		$page = $this->team->setDeparture();
		self::assertFlash($page, 'success', 'Odchod ze stanoviště byl nastaven na ');
		self::assertNow($this->team->result(1)?->exit_datetime);

		$page = $this->team->akce();
		self::assertFalse($page->hasForm('exitTimeNow'), 'TEĎ is offered only until the departure is set.');
		self::assertSame(
			$this->team->result(1)?->exit_datetime->format('H:i'),
			$page->getForm('exitTimeInput')?->querySelector('input[name="exitTime"]')?->getAttribute('value'),
		);
	}


	public function testEmptyInputMeansNow(): void
	{
		$page = $this->team->setDeparture('');
		self::assertNoErrors($page);
		self::assertNow($this->team->result(1)?->exit_datetime);
	}


	public function testTypedTime(): void
	{
		$this->game->travel(30);
		$expected = new \DateTimeImmutable('-10 minutes');

		$page = $this->team->setDeparture($expected->format('H:i'));
		self::assertFlash($page, 'success', 'Odchod ze stanoviště byl nastaven na ' . $expected->format('H:i'));
		self::assertSame($expected->format('Y-m-d H:i:00'), $this->team->result(1)?->exit_datetime->format('Y-m-d H:i:s'));

		// the departure can be corrected
		$corrected = new \DateTimeImmutable('-20 minutes');
		$this->team->setDeparture($corrected->format('H:i'));
		self::assertSame($corrected->format('Y-m-d H:i:00'), $this->team->result(1)?->exit_datetime->format('Y-m-d H:i:s'));
	}


	public function testBeforeArrival(): void
	{
		$this->game->travel(30);
		$page = $this->team->setDeparture((new \DateTimeImmutable('-40 minutes'))->format('H:i'));
		self::assertSame(['Odchod nemůže být dříve než příchod na stanoviště.'], self::getErrors($page));
		self::assertNull($this->team->result(1)?->exit_datetime);
	}


	public function testTimeInTheFutureMeansYesterday(): void
	{
		// the team arrived almost a day ago and left before midnight
		$this->game->travel(23 * 60 + 30);
		$this->game->setGameEnd('+1 hour');
		$typed = new \DateTimeImmutable('+1 hour');

		$page = $this->team->setDeparture($typed->format('H:i'));
		self::assertNoErrors($page);
		self::assertSame(
			$typed->modify('-1 day')->format('Y-m-d H:i:00'),
			$this->team->result(1)?->exit_datetime->format('Y-m-d H:i:s'),
		);
	}


	public function testTimeSlightlyInTheFutureIsToday(): void
	{
		if (date('H:i') >= '23:45') {
			self::markTestSkipped('The typed time would be after midnight.');
		}

		$typed = new \DateTimeImmutable('+5 minutes');
		$page = $this->team->setDeparture($typed->format('H:i'));
		self::assertNoErrors($page);
		self::assertSame($typed->format('Y-m-d H:i:00'), $this->team->result(1)?->exit_datetime->format('Y-m-d H:i:s'));
	}


	public function testInvalidTime(): void
	{
		$page = $this->team->setDeparture('pul treti');
		self::assertSame(['Nesprávně zadaný čas odchodu.'], self::getErrors($page));
		self::assertNull($this->team->result(1)?->exit_datetime);
	}


	public function testDepartureAfterTheHint(): void
	{
		$this->game->travel(30);
		$this->team->askForHint('KOD1X');
		$hintTime = $this->team->result(1)?->exit_datetime;
		self::assertNow($hintTime);

		// the team left later than it took the hint
		$this->game->travel(10);
		$this->team->setDeparture('');
		self::assertNow($this->team->result(1)?->exit_datetime);
		self::assertSame(1, $this->team->result(1)?->used_hint, 'Correcting the departure keeps the hint.');
	}
}
