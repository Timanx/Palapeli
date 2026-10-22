<?php

declare(strict_types=1);

namespace Tests\Palainfo;

use App\Models\ResultsModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Cipher;
use Tests\Support\Game;
use Tests\Support\PalainfoTestCase;


/**
 * Whole games of several teams for every combination of the edition options (finish cipher, hint for the
 * start, klouzavý start), with a mix of cipher options, checked against the standings.
 */
final class WholeGameTest extends PalainfoTestCase
{
	private const CheckpointCount = 6;


	/**
	 * @return iterable<string, array{bool, bool, bool}>
	 */
	public static function provideEditions(): iterable
	{
		foreach ([true, false] as $finishCipher) {
			foreach ([true, false] as $hintForStart) {
				foreach ([false, true] as $individualStart) {
					$name = implode(', ', [
						$finishCipher ? 'finish cipher' : 'no finish cipher',
						$hintForStart ? 'hint for start' : 'no hint for start',
						$individualStart ? 'klouzavý start' : 'common start',
					]);
					yield $name => [$finishCipher, $hintForStart, $individualStart];
				}
			}
		}
	}


	#[DataProvider('provideEditions')]
	public function testGame(bool $finishCipher, bool $hintForStart, bool $individualStart): void
	{
		$game = Game::createDefault(
			$this->db,
			checkpointCount: self::CheckpointCount,
			ciphers: [
				0 => Cipher::default(0)->with(entryMessage: 'První šifra je u pumptracku.', specification: 'Nádraží'),
				1 => Cipher::default(1)->with(password: true),
				2 => Cipher::default(2)->with(waitMinutes: 10, waitMessage: 'Opečte si špekáček.', pickupCode: 'OHEN'),
				3 => Cipher::default(3)->with(password: true, waitMinutes: 5, deadSolution: null, closeTime: '23:59'),
				4 => Cipher::default(4)->with(specification: 'U lavičky'),
			],
			hasFinishCipher: $finishCipher,
			hintForStart: $hintForStart,
			individualStart: $individualStart,
		);
		$finish = self::CheckpointCount - 1;
		$fast = $game->addTeam('Rychlíci');
		$hinted = $game->addTeam('Nápovědníci');
		$quitter = $game->addTeam('Vzdávající');
		$late = $game->addTeam('Opozdilci');
		$idle = $game->addTeam('Nestartující');

		// "Opozdilci" start first and finish first; "Rychlíci" start an hour later and finish sooner after their start
		$late->play(0, 0);
		$game->travel(60);
		$fast->play(0, 0);
		$hinted->play(0, 0);
		$quitter->play(0, 0);
		self::assertScreensRender($fast);

		$game->travel(30);
		$late->play(1, $finish);
		self::assertTrue($late->hasEnded());
		$game->travel(10);
		$fast->play(1, $finish);
		self::assertTrue($fast->hasEnded());

		$hinted->arriveAt(1);
		$hinted->askForHint('KOD1X');
		$hinted->play(2, 2);
		$hinted->arriveAt(3);
		$hinted->askForHint('KOD3X');
		$hinted->arriveAt(4);
		// a trailing hint: the team does not reach the next checkpoint
		$hinted->askForHint('KOD4X');

		$quitter->play(1, 1);
		$quitter->arriveAt(2);
		$quitter->quit();
		self::assertTrue($quitter->hasEnded());

		foreach ([$fast, $hinted, $quitter, $late, $idle] as $team) {
			self::assertScreensRender($team);
		}

		$finishedCheckpoint = $finishCipher ? $finish + 1 : $finish;
		$resultsModel = $game->getService(ResultsModel::class);

		// before the results are published, the trailing hint is ignored
		$standings = $resultsModel->getTeamStandings(Game::Year, ignoreTrailingHints: true);
		$expectedOrder = $individualStart
			? [$fast->id, $late->id, $hinted->id, $quitter->id]
			: [$late->id, $fast->id, $hinted->id, $quitter->id];
		self::assertSame($expectedOrder, array_keys($standings), 'Teams that did not start are not in the standings.');
		self::assertSame($finishedCheckpoint, (int) $standings[$fast->id]['max_checkpoint']);
		self::assertSame($finishedCheckpoint, (int) $standings[$late->id]['max_checkpoint']);
		self::assertSame(4, (int) $standings[$hinted->id]['max_checkpoint']);
		self::assertSame(2, (int) $standings[$hinted->id]['total_hints']);
		self::assertSame(2, (int) $standings[$quitter->id]['max_checkpoint']);
		self::assertSame(0, (int) $standings[$fast->id]['total_hints']);
		self::assertSame(3, (int) $resultsModel->getTeamStandings(Game::Year)[$hinted->id]['total_hints'], 'Without ignoring the trailing hint.');

		if ($individualStart) {
			self::assertArrayHasKey('start_datetime', $standings[$fast->id]);
			self::assertGreaterThan($standings[$fast->id]['finish_time'], $standings[$late->id]['finish_time']);
		}

		// publishing the results removes the trailing hint from the data
		$game->setGameEnd('-1 minute');
		$resultsModel->removeTrailingHints(Game::Year);
		self::assertSame(0, $hinted->result(4)?->used_hint);
		self::assertSame(2, (int) $resultsModel->getTeamStandings(Game::Year)[$hinted->id]['total_hints']);

		// the scorecard has a row for every team that started
		$scorecard = $resultsModel->getCompleteResults(Game::Year);
		self::assertEqualsCanonicalizing([$fast->id, $hinted->id, $quitter->id, $late->id], array_keys($scorecard));
		self::assertCount($finishedCheckpoint + 1, $scorecard[$fast->id]);
		if ($individualStart) {
			self::assertSame('00:00', $scorecard[$fast->id][0]['entry_time'], 'Times are relative to the start of the team.');
		}

		self::assertScreensRender($fast);
	}
}
