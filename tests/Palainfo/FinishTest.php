<?php

declare(strict_types=1);

namespace Tests\Palainfo;

use App\Models\LogType;
use App\Models\ResultsModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Cipher;
use Tests\Support\Game;
use Tests\Support\PalainfoTestCase;
use Tests\Support\Team;


/**
 * The finish (checkpoint count − 1) in all variants: without a finish cipher, with a finish cipher handed
 * out at the finish (also after a wait), and with the password coming from the last regular cipher.
 */
final class FinishTest extends PalainfoTestCase
{
	private const Finish = 3;


	public function testOnTheWayToTheFinish(): void
	{
		$game = Game::createDefault($this->db, ciphers: [self::Finish => Cipher::default(self::Finish)->with(specification: 'Za hospodou')]);
		$team = $game->addTeam();
		$team->play(0, 1);
		$team->arriveAt(2);

		$page = $team->akce();
		self::assertSame('KÓD CÍLOVÉHO STANOVIŠTĚ', $page->getCodeHeading());
		self::assertFlash($page, 'info', 'Upřesnítko k cíli: Za hospodou');
		self::assertSame('TOTÁLKA K ŠIFŘE 2', $page->getHintButton(), 'The last regular cipher has a total hint.');

		$page = $team->askForHint('KOD2X');
		self::assertFlash($page, 'info', 'Řešením šifry číslo 2 je: Další stanoviště je u kapličky č. 3.');
	}


	/**
	 * @return iterable<string, array{Cipher}>
	 */
	public static function provideFinishWithoutCipher(): iterable
	{
		$finish = Cipher::default(self::Finish)->with(solution: null, deadSolution: null);
		yield 'plain' => [$finish];
		yield 'entry message' => [$finish->with(entryMessage: 'Pivo na baru je zdarma.')];
		yield 'wait (ignored)' => [$finish->with(waitMinutes: 10, pickupCode: 'CIL')];
	}


	#[DataProvider('provideFinishWithoutCipher')]
	public function testFinishWithoutCipher(Cipher $finish): void
	{
		$game = Game::createDefault($this->db, hasFinishCipher: false, ciphers: [self::Finish => $finish]);
		$team = $game->addTeam();
		$team->play(0, 2);

		$page = $team->enterCode('kod3x');
		self::assertFlash($page, 'success', 'Gratulujeme k dokončení Palapeli! Hru jste dokončili jako 1., výsledky se započítanými totálkami budou vyhlášeny po skončení hry.');
		if ($finish->entryMessage !== null) {
			self::assertFlash($page, 'info', $finish->entryMessage);
		}

		self::assertTrue($team->hasEnded());
		self::assertNow($team->result(self::Finish)?->entry_datetime);
		self::assertNull($team->result(self::Finish + 1));
		self::assertNull($team->result(self::Finish)->exit_datetime);

		$this->assertFinished($team, maxCheckpoint: self::Finish);
		self::assertInfo($team, $finish->entryMessage, 'Přišli jste do cíle a dokončili hru! Gratulujeme!');
		self::assertSame('PŘÍCHODY DO CÍLE', $team->arrivals()->getTexts('.checkpoint-heading')[0]);

		$card = $team->card()->getDocument();
		self::assertNull($card->querySelector('input[name="checkpoint3[exitTime]"]'), 'No departure from the finish without a finish cipher.');
		self::assertNull($card->querySelector('input[name="checkpoint3[usedHint]"]'));
	}


	/**
	 * @return iterable<string, array{Cipher}>
	 */
	public static function provideFinishCipher(): iterable
	{
		$finish = Cipher::default(self::Finish)->with(solution: 'Cílové heslo', deadSolution: null);
		yield 'cipher at the finish' => [$finish];
		// has_password_solution does not matter, the finish cipher is always a password
		yield 'cipher at the finish marked as password' => [$finish->with(password: true)];
		yield 'own hint text (unused)' => [$finish->with(deadSolution: 'Nikdy se nezobrazí')];
		yield 'password from the last cipher' => [$finish->with(entryMessage: 'Zadejte heslo, které vám vyšlo z poslední šifry.')];
		yield 'specification' => [$finish->with(specification: 'Za hospodou')];
		yield 'wait + pickup code' => [$finish->with(waitMinutes: 5, waitMessage: 'Dejte si polévku.', pickupCode: 'CilovaSifra')];
		yield 'wait without pickup code' => [$finish->with(waitMinutes: 5)];
	}


	#[DataProvider('provideFinishCipher')]
	public function testFinishCipher(Cipher $finish): void
	{
		$game = Game::createDefault($this->db, hasFinishCipher: true, ciphers: [self::Finish => $finish]);
		$team = $game->addTeam();
		$team->play(0, 2);

		$page = $team->enterCode('KOD3X');
		self::assertFlash($page, 'success', 'Dorazili jste do cíle jako 1.');
		if ($finish->entryMessage !== null) {
			self::assertFlash($page, 'info', $finish->entryMessage);
		}

		self::assertFalse($team->hasEnded(), 'The finish password is still missing.');
		self::assertInfo($team, $finish->entryMessage, 'Přišli jste do cíle.');
		self::assertSame('PŘÍCHODY DO CÍLE', $team->arrivals()->getTexts('.checkpoint-heading')[0]);

		$page = $team->akce();
		self::assertNull($page->getHintButton(), 'No total hint for the finish cipher.');
		self::assertFalse($page->hasForm('exitTimeInput'));
		self::assertTrue($page->hasQuitButton());
		self::assertNoFlash($page, 'Upřesnítko');

		if ($finish->hasWait()) {
			self::assertSame('waiting', $page->getCipherWaitState());
			self::assertSame('KÓD NA CÍLOVÉ ŠIFŘE', $page->getCodeHeading());
			if ($finish->pickupCode !== null) {
				$page = $team->enterCode('cilovasifra');
				self::assertFlash($page, 'success', 'Převzetí šifry bylo zapsáno.');
				$page = $team->akce();
			}
		}

		if (!$finish->hasWait() || $finish->pickupCode !== null) {
			self::assertSame('ŘEŠENÍ ŠIFRY', $page->getCodeHeading());
			self::assertFlash($page, 'info', 'K dokončení hry vám zbývá zadat cílové heslo.');
		}

		$page = $team->askForHint('KOD3X');
		self::assertSame(['Totální nápovědu teď nelze otevřít.'], self::getErrors($page));
		$page = $team->forge('exitTimeNow');
		self::assertSame(['Nejprve zadejte řešení šifry.'], self::getErrors($page));

		$page = $team->enterCode('Tajné heslo 3');
		self::assertNotSame([], self::getErrors($page));
		self::assertFalse($team->hasEnded());

		$game->travel(25);
		$page = $team->enterCode('CILOVE HESLO');
		self::assertFlash($page, 'success', 'Gratulujeme k dokončení Palapeli! Hru jste dokončili jako 1.');
		self::assertTrue($team->hasEnded());

		// solving the finish password is stored as one more checkpoint
		$finishRow = $team->result(self::Finish);
		$passwordRow = $team->result(self::Finish + 1);
		self::assertNow($finishRow?->exit_datetime);
		self::assertNow($passwordRow?->entry_datetime);
		self::assertEquals($passwordRow->entry_datetime, $passwordRow->exit_datetime);
		self::assertSame(0, $passwordRow->used_hint);
		self::assertContains([LogType::EnterCheckpoint->value, self::Finish + 1], array_map(
			static fn($row): array => [$row->type_id, $row->checkpoint_number],
			$game->getLog($team->id),
		));

		$this->assertFinished($team, maxCheckpoint: self::Finish + 1);
		self::assertInfo($team, 'Vyřešili jste cílové heslo a dokončili hru! Gratulujeme!', $finish->entryMessage, 'Přišli jste do cíle.');
		self::assertSame('ČASY VYŘEŠENÍ CÍLOVÉHO HESLA', $team->arrivals()->getTexts('.checkpoint-heading')[0]);
		self::assertNotNull($team->card()->getDocument()->querySelector('input[name="checkpoint3[exitTime]"]'));
	}


	/**
	 * A configuration mistake: a finish cipher without a password can never be solved, the team can only quit.
	 */
	public function testFinishCipherWithoutPassword(): void
	{
		$game = Game::createDefault($this->db, hasFinishCipher: true, ciphers: [self::Finish => Cipher::default(self::Finish)->with(solution: null)]);
		$team = $game->addTeam();
		$team->play(0, 2);
		$team->enterCode('KOD3X');

		foreach (['', 'KOD3X', 'cokoliv'] as $answer) {
			$page = $team->enterCode($answer);
			self::assertSame(['Řešení není správně.'], self::getErrors($page));
		}

		self::assertFalse($team->hasEnded());
	}


	public function testOrderOfFinishers(): void
	{
		$game = Game::createDefault($this->db, hasFinishCipher: true);
		$first = $game->addTeam('První');
		$second = $game->addTeam('Druhý');
		$first->play(0, 2);
		$second->play(0, 2);

		// the second team reaches the finish later but solves the password first
		$first->enterCode('KOD3X');
		$game->travel(1);
		$page = $second->enterCode('KOD3X');
		self::assertFlash($page, 'success', 'Dorazili jste do cíle jako 2.');
		$page = $second->enterCode('tajne heslo 3');
		self::assertFlash($page, 'success', 'Hru jste dokončili jako 1.');
		$game->travel(1);
		$page = $first->enterCode('tajne heslo 3');
		self::assertFlash($page, 'success', 'Hru jste dokončili jako 2.');

		$standings = $game->getService(ResultsModel::class)->getTeamStandings(Game::Year);
		self::assertSame([$second->id, $first->id], array_keys($standings));
		self::assertSame(['Druhý', 'První'], $first->arrivals()->getTexts('.team-name'));
	}


	private function assertFinished(Team $team, int $maxCheckpoint): void
	{
		$page = $team->akce();
		self::assertFlash($page, 'success', 'Hru jste úspěšně dokončili! Gratulujeme.');
		self::assertFlash($page, 'info', 'Rádi vás uvidíme i na afterparty: ' . Game::AfterpartyLocation . ' (od 22:00)');
		self::assertNoFlash($page, 'Přijďte se podívat do cíle');
		self::assertNull($page->getCodeHeading(), 'Nothing can be entered after the finish.');
		self::assertFlash($team->card(), 'success', 'Hru jste úspěšně dokončili! Gratulujeme.');
		self::assertFlash($team->arrivals(), 'info', 'Místo konání afterparty: ' . Game::AfterpartyLocation);
		self::assertScreensRender($team);

		$page = $team->forge('codeInput', ['code' => 'KOD3X']);
		self::assertSame(['Již jste ukončili hru a ve hře tak nemůžete pokračovat.'], self::getErrors($page));
		$page = $team->forge('askForEnd', ['code' => 'JEZIMADEMDOM']);
		self::assertSame(['Již jste ukončili hru a ve hře tak nemůžete pokračovat.'], self::getErrors($page));

		$standings = $team->game->getService(ResultsModel::class)->getTeamStandings(Game::Year);
		self::assertSame($maxCheckpoint, (int) $standings[$team->id]['max_checkpoint']);
	}
}
