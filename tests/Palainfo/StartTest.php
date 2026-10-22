<?php

declare(strict_types=1);

namespace Tests\Palainfo;

use App\Models\LogType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Cipher;
use Tests\Support\Game;
use Tests\Support\PalainfoTestCase;


/**
 * The start cipher (checkpoint 0): the start code, its options and the total hint for the start
 * (years.hint_for_start_exists).
 */
final class StartTest extends PalainfoTestCase
{
	public function testBeforeTheStart(): void
	{
		$game = Game::createDefault($this->db, ciphers: [0 => Cipher::default(0)->with(specification: 'Na nádraží u hodin')]);
		$team = $game->addTeam();

		$page = $team->akce();
		self::assertSame('KÓD STARTOVNÍ ŠIFRY', $page->getCodeHeading());
		self::assertFlash($page, 'info', 'Upřesnítko ke startu: Na nádraží u hodin');
		self::assertFalse($page->hasForm('exitTimeInput'), 'No departure before the start.');
		self::assertNull($page->getHintButton());
		self::assertFalse($page->hasQuitButton(), 'The game cannot be quit before the start.');
		self::assertSame('PŘÍCHODY NA STANOVIŠTĚ', $team->arrivals()->getTexts('.checkpoint-heading')[0]);
		self::assertStringContainsString('Zatím jste nedošli na žádné stanoviště.', $team->arrivals()->getText());
		self::assertSame([], $team->infoMessages());
		self::assertScreensRender($team);

		$page = $team->forge('exitTimeNow');
		self::assertSame([], $team->results(), 'No departure before the start.');
	}


	public function testStartCode(): void
	{
		$game = Game::createDefault($this->db, ciphers: [
			0 => Cipher::default(0)->with(entryMessage: 'První šifra se nachází u pumptracku.'),
		]);
		$first = $game->addTeam('První');
		$second = $game->addTeam('Druhý');

		$page = $first->enterCode('KOD1X');
		self::assertSame(['Nesprávně zadaný kód'], self::getErrors($page), 'The code of checkpoint 1 does not start the game.');

		$page = $first->enterCode('KOD 0X');
		self::assertSame(['Nesprávně zadaný kód'], self::getErrors($page), 'Spaces inside a code matter.');

		// the form trims the input
		$page = $first->enterCode(' KOD0X ');
		self::assertFlash($page, 'success', 'Vítejte na startu Palapeli. Kód startovní šifry jste zadali jako 1.');
		self::assertFlash($page, 'info', 'První šifra se nachází u pumptracku.');
		self::assertNow($first->result(0)?->entry_datetime);
		self::assertSame(
			[[LogType::EnterCheckpoint->value, null], [LogType::MessageFromOrg->value, 'První šifra se nachází u pumptracku.']],
			array_map(static fn($row): array => [$row->type_id, $row->message], $game->getLog($first->id)),
		);
		self::assertSame(['První šifra se nachází u pumptracku.', 'Zadali jste kód startovní šifry.'], $first->infoMessages());

		$page = $first->akce();
		self::assertSame('KÓD STANOVIŠTĚ 1', $page->getCodeHeading());
		self::assertSame('ODCHOD ZE STARTU', $page->getExitHeading());
		self::assertTrue($page->hasQuitButton());

		$game->travel(3);
		$page = $second->enterCode('kod0x');
		self::assertFlash($page, 'success', 'Kód startovní šifry jste zadali jako 2.');

		$arrivals = $second->arrivals();
		self::assertSame('PŘÍCHODY NA START', $arrivals->getTexts('.checkpoint-heading')[0]);
		self::assertSame(['První', 'Druhý'], $arrivals->getTexts('.team-name'));
		self::assertSame(['Druhý'], $arrivals->getTexts('.team-entry.current .team-name'));
		self::assertScreensRender($first);
	}


	public function testStartWithoutCode(): void
	{
		$game = Game::createDefault($this->db, ciphers: [0 => Cipher::default(0)->with(code: null)]);
		$team = $game->addTeam();

		foreach (['', 'KOD0X', 'null'] as $code) {
			$page = $team->enterCode($code);
			self::assertSame(['Nesprávně zadaný kód'], self::getErrors($page), 'A checkpoint without a code accepts nothing.');
		}

		self::assertSame([], $team->results());
	}


	/**
	 * @return iterable<string, array{bool}>
	 */
	public static function provideHintForStart(): iterable
	{
		yield 'hint for the start' => [true];
		yield 'no hint for the start' => [false];
	}


	#[DataProvider('provideHintForStart')]
	public function testTotalHintForTheStart(bool $hintForStart): void
	{
		$game = Game::createDefault($this->db, hintForStart: $hintForStart);
		$team = $game->addTeam();
		self::assertNull($team->akce()->getHintButton());

		$team->enterCode('KOD0X');
		self::assertSame($hintForStart ? 'TOTÁLKA KE STARTOVNÍ ŠIFŘE' : null, $team->akce()->getHintButton());
		self::assertSame(
			$hintForStart,
			$team->card()->getDocument()->querySelector('input[name="checkpoint0[usedHint]"]') !== null,
			'KARTA shows the hint of the start only when there is one.',
		);

		$page = $team->askForHint('KOD0X');
		if (!$hintForStart) {
			self::assertSame(['Totální nápovědu teď nelze otevřít.'], self::getErrors($page));
			self::assertNull($team->result(0)?->exit_datetime);
			self::assertSame(0, $team->result(0)?->used_hint);
			return;
		}

		self::assertFlash($page, 'info', 'Řešením startovní šifry je: Další stanoviště je u kapličky č. 1.');
		self::assertSame(1, $team->result(0)?->used_hint);
		self::assertNow($team->result(0)?->exit_datetime);
		self::assertInfo($team, 'Otevřeli jste totálku na startovní šifru. Znění: Další stanoviště je u kapličky č. 1.');
		self::assertNull($team->akce()->getHintButton());
	}


	public function testPasswordStartCipher(): void
	{
		$game = Game::createDefault($this->db, hintForStart: true, ciphers: [0 => Cipher::default(0)->with(password: true)]);
		$team = $game->addTeam();
		$team->enterCode('KOD0X');

		$page = $team->akce();
		self::assertSame('ŘEŠENÍ ŠIFRY', $page->getCodeHeading());
		self::assertFalse($page->hasForm('exitTimeInput'));
		self::assertSame('TOTÁLKA KE STARTOVNÍ ŠIFŘE', $page->getHintButton());

		$page = $team->enterCode('KOD1X');
		self::assertSame(['Řešení není správně.'], self::getErrors($page));

		$page = $team->enterCode('tajne heslo 0');
		self::assertFlash($page, 'success', 'Správně! Umístění dalšího stanoviště: Další stanoviště je u kapličky č. 1.');
		self::assertNow($team->result(0)?->exit_datetime);
		self::assertSame('ODCHOD ZE STARTU', $team->akce()->getExitHeading());
		self::assertNull($team->akce()->getHintButton());

		$page = $team->enterCode('KOD1X');
		self::assertFlash($page, 'success', 'Dorazili jste na stanoviště 1 jako 1.');
	}


	public function testWaitAtTheStart(): void
	{
		$game = Game::createDefault($this->db, ciphers: [0 => Cipher::default(0)->with(waitMinutes: 10, pickupCode: 'START')]);
		$team = $game->addTeam();
		$team->enterCode('KOD0X');

		$page = $team->akce();
		self::assertSame('waiting', $page->getCipherWaitState());
		self::assertSame('KÓD NA STARTOVNÍ ŠIFŘE', $page->getCodeHeading());
		self::assertFalse($page->hasForm('exitTimeInput'));

		$page = $team->enterCode('start');
		self::assertFlash($page, 'success', 'Převzetí šifry bylo zapsáno.');
		self::assertSame('ODCHOD ZE STARTU', $team->akce()->getExitHeading());
	}
}
