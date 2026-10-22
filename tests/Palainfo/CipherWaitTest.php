<?php

declare(strict_types=1);

namespace Tests\Palainfo;

use Tests\Support\Cipher;
use Tests\Support\Game;
use Tests\Support\PalainfoTestCase;
use Tests\Support\Team;


/**
 * Details of the mandatory wait for a cipher (ciphers.wait_minutes) not covered by the option matrix:
 * the countdown, an early pickup and several phones of one team.
 */
final class CipherWaitTest extends PalainfoTestCase
{
	private Game $game;
	private Team $team;


	protected function setUp(): void
	{
		parent::setUp();
		$this->game = Game::createDefault($this->db, ciphers: [
			1 => Cipher::default(1)->with(waitMinutes: 20, pickupCode: 'OHEN'),
			2 => Cipher::default(2)->with(waitMinutes: 1),
		]);
		$this->team = $this->game->addTeam();
		$this->team->play(0, 0);
	}


	public function testCountdown(): void
	{
		$this->team->enterCode('KOD1X');
		$waitEnd = new \DateTimeImmutable('+20 minutes');

		$page = $this->team->akce();
		self::assertStringContainsString('Tato obrazovka zezelená v ' . $waitEnd->format('H:i'), $page->getText());
		self::assertMatchesRegularExpression('~^(20:00|19:5\d)$~', $page->getTexts('.cipher-wait-countdown')[0]);
		self::assertStringNotContainsString('Opečte', $page->getText(), 'No wait message configured.');

		$this->game->travel(15);
		$page = $this->team->akce();
		self::assertSame('waiting', $page->getCipherWaitState());
		self::assertEqualsWithDelta(5 * 60, (int) $page->getCipherWait()?->getAttribute('data-remaining'), 10);

		$this->game->travel(5);
		$page = $this->team->akce();
		self::assertSame('ready', $page->getCipherWaitState());
		self::assertSame('0', $page->getCipherWait()?->getAttribute('data-remaining'));
	}


	public function testPickupBeforeTheCountdownEnds(): void
	{
		$this->team->enterCode('KOD1X');
		$this->game->travel(2);

		// organizers may shorten the wait
		$page = $this->team->enterCode('ohen');
		self::assertFlash($page, 'success', 'Převzetí šifry bylo zapsáno. Hodně štěstí při luštění!');
		self::assertNull($page->getCipherWaitState());
		self::assertNow($this->team->result(1)?->pickup_datetime);
		self::assertSame('ODCHOD ZE STANOVIŠTĚ 1', $page->getExitHeading());

		// the pickup code is not accepted twice, it is no longer expected
		$page = $this->team->enterCode('OHEN');
		self::assertSame(['Nesprávně zadaný kód'], self::getErrors($page));
	}


	public function testTwoPhones(): void
	{
		$this->team->enterCode('KOD1X');
		$phone2 = $this->team->secondPhone();
		self::assertSame('waiting', $phone2->akce()->getCipherWaitState());

		$outdated = $phone2->lastPage();
		$this->team->enterCode('OHEN');
		self::assertNull($phone2->akce()->getCipherWaitState(), 'The pickup on one phone removes the screen on the other one.');

		$page = $phone2->submitOutdated($outdated, 'codeInput', ['code' => 'OHEN']);
		self::assertSame(['Nesprávně zadaný kód'], self::getErrors($page));
		self::assertNow($this->team->result(1)?->pickup_datetime);
	}


	public function testWaitOnTheNextCheckpointToo(): void
	{
		$this->team->arriveAt(1);
		$this->team->setDeparture();
		$page = $this->team->enterCode('KOD2X');
		self::assertFlash($page, 'success', 'Dorazili jste na stanoviště 2 jako 1.');

		$page = $this->team->akce();
		self::assertSame('waiting', $page->getCipherWaitState());
		self::assertSame('KÓD NA ŠIFŘE 2', $page->getCodeHeading());
		self::assertNotNull($this->team->result(1)?->pickup_datetime);
		self::assertNull($this->team->result(2)?->pickup_datetime);
	}
}
