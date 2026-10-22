<?php

declare(strict_types=1);

namespace Tests\Palainfo;

use App\Models\LogModel;
use App\Models\LogType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Browser;
use Tests\Support\Game;
use Tests\Support\PalainfoTestCase;


/**
 * Who can use Palainfo and when: before the start, after the end of the game, after quitting, teams of
 * other editions and anonymous visitors; plus messages from organizers.
 */
final class GameStateTest extends PalainfoTestCase
{
	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideForms(): iterable
	{
		foreach (['codeInput', 'exitTimeInput', 'exitTimeNow', 'askForDead', 'askForEnd'] as $form) {
			yield $form => [$form];
		}
	}


	#[DataProvider('provideForms')]
	public function testBeforeTheGameStarts(string $form): void
	{
		$game = Game::createDefault($this->db, gameStart: '+1 hour');
		$team = $game->addTeam();

		$page = $team->akce();
		self::assertFlash($page, 'info', 'Palainfo bude zpřístupněné až po začátku hry.');
		self::assertNull($page->getCodeHeading());

		$page = $team->forge($form, ['code' => 'KOD0X', 'exitTime' => '']);
		self::assertSame(403, $page->httpCode);
		self::assertSame([], $team->results());
	}


	#[DataProvider('provideForms')]
	public function testTeamOfAnotherEdition(string $form): void
	{
		$game = Game::createDefault($this->db);
		$team = $game->addTeam(registered: false);

		self::assertFlash($team->akce(), 'info', 'Palainfo je přístupné pouze týmům zaregistrovaným v aktuálním ročníku.');
		self::assertSame(403, $team->forge($form, ['code' => 'KOD0X'])->httpCode);
		self::assertSame([], $team->results());
	}


	public function testAnonymousVisitor(): void
	{
		Game::createDefault($this->db);
		$browser = new Browser($this->db);

		$page = $browser->open('/pala-info/');
		self::assertTrue($page->hasFlash('info', 'Pro přístup do webinfa musíte být přihlášeni.'), $page->getText());
		self::assertSame(403, $browser->forge('codeInput', ['code' => 'KOD0X'])->httpCode);
		self::assertSame(0, $this->db->query('SELECT COUNT(*) FROM results')->fetchField());
	}


	public function testAfterTheGameEnds(): void
	{
		$game = Game::createDefault($this->db, hintForStart: true);
		$team = $game->addTeam();
		$team->enterCode('KOD0X');
		$outdated = $team->akce();
		$game->setGameEnd('-1 minute');

		$page = $team->akce();
		self::assertFlash($page, 'info', 'Hra již skončila, děkujeme za účast. Již nelze zadávat kódy stanovišť ani hesla. Přijďte se za námi podívat do cíle: ' . Game::FinishLocation);
		self::assertFlash($page, 'info', 'Rádi vás uvidíme i na afterparty: ' . Game::AfterpartyLocation);
		self::assertNull($page->getCodeHeading());
		self::assertScreensRender($team);

		self::assertSame(['Bohužel jste kód nestihli zadat před koncem hry.'], self::getErrors($team->submitOutdated($outdated, 'codeInput', ['code' => 'KOD1X'])));
		foreach (['exitTimeNow' => [], 'exitTimeInput' => ['exitTime' => ''], 'askForDead' => ['code' => 'KOD0X'], 'askForEnd' => ['code' => 'JEZIMADEMDOM']] as $form => $values) {
			$page = $team->submitOutdated($outdated, $form, $values);
			self::assertSame(['Hra již skončila, tuto akci už nelze provést.'], self::getErrors($page), $form);
		}

		$result = $team->result(0);
		self::assertNull($result?->exit_datetime);
		self::assertSame(0, $result->used_hint);
		self::assertNull($team->result(1));
		self::assertFalse($team->hasEnded());
	}


	public function testQuitting(): void
	{
		$game = Game::createDefault($this->db);
		$team = $game->addTeam();
		$team->play(0, 0);
		$team->enterCode('KOD1X');
		$outdated = $team->akce();

		$page = $team->quit('KONEC');
		self::assertSame(['Nesprávně zadaný kód'], self::getErrors($page));
		self::assertTrue($page->opensScreen('toggleEndScreen'), 'A wrong code re-opens the confirmation screen.');
		self::assertFalse($team->hasEnded());

		$page = $team->quit('jezimademdom');
		self::assertNoErrors($page);
		self::assertTrue($team->hasEnded());
		self::assertFlash($page, 'info', 'Již jste ukončili hru a ve hře tak nemůžete pokračovat.');
		self::assertFlash($page, 'info', 'Přijďte se podívat do cíle: ' . Game::FinishLocation . ' (otevřen od 20:00)');
		self::assertFlash($page, 'info', 'Rádi vás uvidíme i na afterparty: ' . Game::AfterpartyLocation . ' (od 22:00)');
		self::assertNull($page->getCodeHeading());
		self::assertContains([LogType::EndGame->value, null], array_map(static fn($row): array => [$row->type_id, $row->checkpoint_number], $game->getLog($team->id)));
		self::assertInfo($team, 'Ukončili jste hru.');
		self::assertFlash($team->card(), 'info', 'Pozice cíle: ' . Game::FinishLocation . ' (otevřen od 20:00)');
		self::assertScreensRender($team);

		// another phone with the page from before quitting
		foreach (['codeInput' => ['code' => 'KOD2X'], 'exitTimeNow' => [], 'askForDead' => ['code' => 'KOD1X']] as $form => $values) {
			$page = $team->submitOutdated($outdated, $form, $values);
			self::assertSame(['Již jste ukončili hru a ve hře tak nemůžete pokračovat.'], self::getErrors($page), $form);
		}

		self::assertNull($team->result(2));
		self::assertNull($team->result(1)?->exit_datetime);
	}


	public function testQuittingWithoutAfterparty(): void
	{
		$game = Game::createDefault($this->db);
		$this->db->query('UPDATE years SET afterparty_location = NULL, finish_open_time = NULL WHERE year = ?', Game::Year);
		$team = $game->addTeam();
		$team->enterCode('KOD0X');

		$page = $team->quit();
		self::assertFlash($page, 'info', 'Přijďte se podívat do cíle: ' . Game::FinishLocation . ' (otevřen od 09:00)');
		self::assertNoFlash($page, 'afterparty');
	}


	public function testMessagesFromOrganizers(): void
	{
		$game = Game::createDefault($this->db);
		$team = $game->addTeam();
		$other = $game->addTeam('Jiný tým');
		$log = $game->getService(LogModel::class);

		$log->log(LogType::MessageFromOrg, message: 'Stanoviště 2 je přesunuto k hájovně.');
		$log->log(LogType::MessageFromOrg, $other->id, null, Game::Year, 'Zpráva jen pro jiný tým');

		self::assertSame(['Stanoviště 2 je přesunuto k hájovně.'], $team->infoMessages());
		// both logged in the same second: the later one first
		self::assertSame(['Zpráva jen pro jiný tým', 'Stanoviště 2 je přesunuto k hájovně.'], $other->infoMessages());
	}
}
