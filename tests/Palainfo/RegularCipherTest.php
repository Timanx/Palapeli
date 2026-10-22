<?php

declare(strict_types=1);

namespace Tests\Palainfo;

use App\Models\LogType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Cipher;
use Tests\Support\Game;
use Tests\Support\PalainfoTestCase;
use Tests\Support\Team;


/**
 * Every combination of the cipher options on a regular checkpoint (checkpoint 1 of start, 1, 2, finish),
 * played through every way a team can get past it: arriving, getting the cipher after the wait,
 * solving it (password or departure), taking the total hint, and actions the current state refuses.
 */
final class RegularCipherTest extends PalainfoTestCase
{
	private const Checkpoint = 1;
	private const WaitMinutes = 15;


	/**
	 * kind × wait × entry message × specification and closing time × total hint text = 48 ciphers
	 * @return iterable<string, array{Cipher}>
	 */
	public static function provideCiphers(): iterable
	{
		$waits = [
			static fn(Cipher $cipher): Cipher => $cipher,
			static fn(Cipher $cipher): Cipher => $cipher->with(waitMinutes: self::WaitMinutes, waitMessage: 'Opečte si špekáček.', pickupCode: 'Sifra1'),
			static fn(Cipher $cipher): Cipher => $cipher->with(waitMinutes: self::WaitMinutes),
		];

		foreach ([false, true] as $password) {
			foreach ($waits as $wait) {
				foreach ([null, 'Vítejte u ohně, vyřiďte pozdrav od organizátorů.'] as $entryMessage) {
					foreach ([null, 'U velkého dubu'] as $specification) {
						foreach (['Další stanoviště je na rozhledně.', null] as $deadSolution) {
							$cipher = $wait(Cipher::default(self::Checkpoint)->with(
								password: $password,
								entryMessage: $entryMessage,
								specification: $specification,
								closeTime: $specification === null ? null : '18:30',
								deadSolution: $deadSolution,
							));
							yield $cipher->describe() => [$cipher];
						}
					}
				}
			}
		}
	}


	#[DataProvider('provideCiphers')]
	public function testArrival(Cipher $cipher): void
	{
		[$game, $team] = $this->startGame($cipher);

		// on the way to the checkpoint
		$page = $team->akce();
		self::assertSame('KÓD STANOVIŠTĚ 1', $page->getCodeHeading());
		if ($cipher->specification !== null) {
			self::assertFlash($page, 'info', 'Upřesnítko ke stanovišti 1: ' . $cipher->specification);
		} else {
			self::assertNoFlash($page, 'Upřesnítko');
		}

		$card = $team->card()->getText();
		self::assertStringContainsString($cipher->closeTime === null ? '1. stanoviště (Otevřeno do konce hry)' : '1. stanoviště (Zavírá v 18:30)', $card);
		self::assertSame($cipher->specification !== null, str_contains($card, 'Upřesnítko: ' . $cipher->specification));

		// wrong codes
		$page = $team->enterCode('SPATNE');
		self::assertSame(['Nesprávně zadaný kód'], self::getErrors($page));
		$page = $team->enterCode((string) $game->cipher(0)->code);
		self::assertSame(['Tento kód už máte zadaný.'], self::getErrors($page));
		self::assertNull($team->result(self::Checkpoint));

		// the code is not case sensitive
		$page = $team->enterCode(mb_strtolower((string) $cipher->code));
		self::assertNoErrors($page);
		self::assertFlash($page, 'success', 'Dorazili jste na stanoviště 1 jako 1.');
		$result = $team->result(self::Checkpoint);
		self::assertNow($result?->entry_datetime);
		self::assertSame(0, $result->used_hint);
		self::assertNull($result->exit_datetime);
		self::assertNull($result->pickup_datetime);

		$expectedLog = [[LogType::EnterCheckpoint->value, self::Checkpoint, null]];
		if ($cipher->entryMessage !== null) {
			self::assertFlash($page, 'info', $cipher->entryMessage);
			$expectedLog[] = [LogType::MessageFromOrg->value, self::Checkpoint, $cipher->entryMessage];
		}

		self::assertSame($expectedLog, $this->getLogSince($team, self::Checkpoint));
		// the entry message is logged in the same second as the arrival, but after it
		self::assertInfo($team, $cipher->entryMessage, 'Přišli jste na stanoviště číslo 1.');

		// the checkpoint the team is on now
		$page = $team->akce();
		self::assertSame('TOTÁLKA K ŠIFŘE 1', $page->getHintButton());
		self::assertTrue($page->hasQuitButton());
		self::assertSame(['Šnečí tým'], $team->arrivals()->getTexts('.team-entry.current .team-name'));

		if ($cipher->hasWait()) {
			self::assertSame('waiting', $page->getCipherWaitState());
			self::assertEqualsWithDelta(self::WaitMinutes * 60, (int) $page->getCipherWait()?->getAttribute('data-remaining'), 10);
			if ($cipher->waitMessage !== null) {
				self::assertStringContainsString($cipher->waitMessage, $page->getText());
			}
			self::assertSame('KÓD NA ŠIFŘE 1', $page->getCodeHeading());
			self::assertFalse($page->hasForm('exitTimeInput'), 'The departure cannot be set before the pickup.');
			self::assertNoFlash($page, 'Řešením šifry je heslo');

			$game->travel(self::WaitMinutes);
			$page = $team->akce();
			self::assertSame('ready', $page->getCipherWaitState());
			self::assertStringContainsString('Tým Šnečí tým si již může vyzvednout šifru', $page->getText());
			self::assertSame('KÓD NA ŠIFŘE 1', $page->getCodeHeading());

		} elseif ($cipher->password) {
			self::assertNull($page->getCipherWaitState());
			self::assertSame('ŘEŠENÍ ŠIFRY', $page->getCodeHeading());
			self::assertFlash($page, 'info', 'Řešením šifry je heslo. Po jeho zadání vám Palainfo vypíše polohu dalšího stanoviště.');
			self::assertFalse($page->hasForm('exitTimeInput'), 'A password cipher is left only by the password.');

		} else {
			self::assertNull($page->getCipherWaitState());
			self::assertSame('KÓD STANOVIŠTĚ 2', $page->getCodeHeading());
			self::assertSame('ODCHOD ZE STANOVIŠTĚ 1', $page->getExitHeading());
			self::assertTrue($page->hasForm('exitTimeNow'));
		}

		self::assertScreensRender($team);
	}


	#[DataProvider('provideCiphers')]
	public function testSolving(Cipher $cipher): void
	{
		[$game, $team] = $this->startGame($cipher);
		$team->enterCode((string) $cipher->code);
		$logStart = count($this->getLogSince($team, self::Checkpoint));

		if ($cipher->hasWait()) {
			$game->travel(self::WaitMinutes);
			if ($cipher->pickupCode !== null) {
				$page = $team->enterCode('SPATNE');
				self::assertSame(['Nesprávně zadaný kód na šifře.'], self::getErrors($page));
				$page = $team->enterCode((string) $cipher->code);
				self::assertSame(['Tento kód už máte zadaný. Po převzetí šifry zadejte kód, který je na ní napsaný.'], self::getErrors($page));

				$page = $team->enterCode(mb_strtoupper($cipher->pickupCode));
				self::assertFlash($page, 'success', 'Převzetí šifry bylo zapsáno.');
				self::assertNull($page->getCipherWaitState());
				self::assertNow($team->result(self::Checkpoint)?->pickup_datetime);
			}
		}

		$page = $team->akce();
		if ($cipher->password) {
			if (!$cipher->hasWait() || $cipher->pickupCode !== null) {
				self::assertSame('ŘEŠENÍ ŠIFRY', $page->getCodeHeading());
			}

			// the code of the next checkpoint does not get the team past a password cipher
			$page = $team->enterCode((string) $game->cipher(2)->code);
			self::assertSame([$this->expectedWrongAnswer($cipher)], self::getErrors($page));
			$page = $team->enterCode('Tajné heslo 2');
			self::assertSame([$this->expectedWrongAnswer($cipher)], self::getErrors($page));
			self::assertNull($team->result(self::Checkpoint)?->exit_datetime);

			// case, diacritics, spaces and punctuation do not matter
			$page = $team->enterCode('  TAJNE   heslo-1!');
			self::assertNoErrors($page);
			self::assertFlash($page, 'success', 'Správně! Umístění dalšího stanoviště: ' . $cipher->getRevealedText());
			self::assertSame(
				[[LogType::MessageFromOrg->value, self::Checkpoint, 'Umístění stanoviště 2: ' . $cipher->getRevealedText()]],
				array_slice($this->getLogSince($team, self::Checkpoint), $logStart),
			);
			self::assertInfo($team, 'Umístění stanoviště 2: ' . $cipher->getRevealedText(), $cipher->entryMessage, 'Přišli jste na stanoviště číslo 1.');

			$page = $team->akce();
			self::assertNull($page->getHintButton(), 'No total hint for a solved password cipher.');
			self::assertSame('ODCHOD ZE STANOVIŠTĚ 1', $page->getExitHeading(), 'The departure can be corrected after the password.');

		} else {
			if ($cipher->hasWait() && $cipher->pickupCode === null) {
				// the team cannot record the pickup, so it never sets the departure; it goes on by the next code
				self::assertFalse($page->hasForm('exitTimeInput'));
				$page = $team->forge('exitTimeNow');
				self::assertSame(['Odchod ze stanoviště lze zadat až po převzetí šifry – zadejte kód, který je na ní napsaný.'], self::getErrors($page));

			} else {
				$page = $team->setDeparture();
				self::assertNoErrors($page);
				self::assertFlash($page, 'success', 'Odchod ze stanoviště byl nastaven na ');
				self::assertFalse($team->akce()->hasForm('exitTimeNow'), 'TEĎ is offered only until the departure is set.');
				self::assertSame('TOTÁLKA K ŠIFŘE 1', $team->akce()->getHintButton(), 'The hint stays available after the departure.');
			}
		}

		$result = $team->result(self::Checkpoint);
		self::assertSame(0, $result?->used_hint);
		if ($cipher->hasWait() && $cipher->pickupCode === null) {
			self::assertNull($result->pickup_datetime);
		}

		if ($cipher->hasWait() && $cipher->pickupCode === null && !$cipher->password) {
			self::assertNull($result->exit_datetime);
		} else {
			self::assertNow($result->exit_datetime);
		}

		// on to the next checkpoint
		$game->travel(20);
		$page = $team->enterCode((string) $game->cipher(2)->code);
		self::assertFlash($page, 'success', 'Dorazili jste na stanoviště 2 jako 1.');
		self::assertSame('ODCHOD ZE STANOVIŠTĚ 2', $page->getExitHeading());
		self::assertNow($team->result(2)?->entry_datetime);
		self::assertScreensRender($team);
	}


	#[DataProvider('provideCiphers')]
	public function testTotalHint(Cipher $cipher): void
	{
		[$game, $team] = $this->startGame($cipher);
		self::assertNull($team->akce()->getHintButton(), 'No hint for the start cipher in this edition.');

		$team->enterCode((string) $cipher->code);
		$game->travel(5);

		// a wrong confirmation code re-opens the confirmation screen
		$page = $team->askForHint('SPATNE');
		self::assertSame(['Nesprávně zadaný kód'], self::getErrors($page));
		self::assertTrue($page->opensScreen('toggleDeadScreen'));
		self::assertNull($team->result(self::Checkpoint)?->exit_datetime);

		// the hint is available also on the wait screen
		self::assertSame($cipher->hasWait() ? 'waiting' : null, $team->akce()->getCipherWaitState());
		$page = $team->askForHint(mb_strtolower((string) $cipher->code));
		self::assertNoErrors($page);
		self::assertFlash($page, 'info', 'Řešením šifry číslo 1 je: ' . $cipher->getRevealedText());
		self::assertFalse($page->opensScreen('toggleDeadScreen'));

		$result = $team->result(self::Checkpoint);
		self::assertSame(1, $result?->used_hint);
		self::assertNow($result->exit_datetime);
		self::assertNull($result->pickup_datetime);
		self::assertContains([LogType::OpenDead->value, self::Checkpoint, null], $this->getLogSince($team, self::Checkpoint));
		self::assertInfo($team, 'Otevřeli jste totálku na stanovišti 1. Znění: ' . $cipher->getRevealedText(), $cipher->entryMessage, 'Přišli jste na stanoviště číslo 1.');
		self::assertNotNull($team->card()->getDocument()->querySelector('input[name="checkpoint1[usedHint]"][checked]'));

		$page = $team->akce();
		self::assertNull($page->getCipherWaitState(), 'The hint removes the wait screen.');
		self::assertNull($page->getHintButton(), 'The hint can be taken only once.');
		self::assertSame('KÓD STANOVIŠTĚ 2', $page->getCodeHeading());
		self::assertSame('ODCHOD ZE STANOVIŠTĚ 1', $page->getExitHeading());

		$page = $team->forge('askForDead', ['code' => (string) $cipher->code]);
		self::assertSame(['Totální nápovědu teď nelze otevřít.'], self::getErrors($page));
		self::assertCount(1, array_filter($game->getLog($team->id), static fn($row): bool => $row->type_id === LogType::OpenDead->value));

		$page = $team->enterCode((string) $game->cipher(2)->code);
		self::assertFlash($page, 'success', 'Dorazili jste na stanoviště 2 jako 1.');
		self::assertSame('TOTÁLKA K ŠIFŘE 2', $page->getHintButton());
		self::assertScreensRender($team);

		// the hint counts: one checkpoint less in the standings
		$standings = $game->getService(\App\Models\ResultsModel::class)->getTeamStandings(Game::Year);
		self::assertSame(1, (int) $standings[$team->id]['total_hints']);
	}


	/**
	 * Forms the screen does not offer in the current state are refused by the server too (outdated page on another phone).
	 */
	#[DataProvider('provideCiphers')]
	public function testOutdatedPage(Cipher $cipher): void
	{
		[$game, $team] = $this->startGame($cipher);
		$phone2 = $team->secondPhone();
		$outdated = $phone2->akce();
		$startExit = $team->result(0)?->exit_datetime;

		$team->enterCode((string) $cipher->code);

		// the second phone still offers the code of checkpoint 1 and the departure from the start
		$page = $phone2->submitOutdated($outdated, 'codeInput', ['code' => (string) $cipher->code]);
		self::assertSame(
			[match (true) {
				$cipher->hasWait() => 'Tento kód už máte zadaný. Po převzetí šifry zadejte kód, který je na ní napsaný.',
				$cipher->password => 'Řešení není správně.',
				default => 'Tento kód už máte zadaný.',
			}],
			self::getErrors($page),
		);

		$page = $phone2->submitOutdated($outdated, 'exitTimeInput', ['exitTime' => '']);
		$error = match (true) {
			$cipher->password => 'Nejprve zadejte řešení šifry.',
			$cipher->hasWait() => 'Odchod ze stanoviště lze zadat až po převzetí šifry – zadejte kód, který je na ní napsaný.',
			default => null,
		};
		self::assertSame($error === null ? [] : [$error], self::getErrors($page));
		// the departure from the outdated page is the departure from the current checkpoint, never from the start again
		self::assertSame($error === null, $team->result(self::Checkpoint)?->exit_datetime !== null);
		self::assertEquals($startExit, $team->result(0)?->exit_datetime);
	}


	/**
	 * A started game: the team entered the start code and left the start; it heads to checkpoint 1.
	 * @return array{Game, Team}
	 */
	private function startGame(Cipher $cipher): array
	{
		$game = Game::createDefault($this->db, ciphers: [self::Checkpoint => $cipher]);
		$team = $game->addTeam();
		$team->enterCode((string) $game->cipher(0)->code);
		$team->setDeparture();
		$game->travel(30);
		return [$game, $team];
	}


	/**
	 * Error for a wrong password: while waiting for the pickup the input expects the pickup code.
	 */
	private function expectedWrongAnswer(Cipher $cipher): string
	{
		return $cipher->hasWait() && $cipher->pickupCode === null ? 'Nesprávně zadaný kód na šifře.' : 'Řešení není správně.';
	}


	/**
	 * @return list<array{int, ?int, ?string}>  type, checkpoint, message of the team's log entries from the checkpoint on
	 */
	private function getLogSince(Team $team, int $checkpoint): array
	{
		$entries = [];
		foreach ($team->game->getLog($team->id) as $row) {
			if ($row->checkpoint_number !== null && $row->checkpoint_number >= $checkpoint) {
				$entries[] = [$row->type_id, $row->checkpoint_number, $row->message];
			}
		}

		return $entries;
	}
}
