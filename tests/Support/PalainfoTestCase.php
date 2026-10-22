<?php

declare(strict_types=1);

namespace Tests\Support;

use Nette\Database\Connection;
use PHPUnit\Framework\TestCase;


/**
 * Every test runs in a database transaction that is rolled back afterwards.
 */
abstract class PalainfoTestCase extends TestCase
{
	protected Connection $db;


	protected function setUp(): void
	{
		$this->db = TestDatabase::getConnection();
		if ($this->db->getPdo()->inTransaction()) {
			// the previous test lost the connection
			$this->db->reconnect();
			TestDatabase::configureConnection($this->db);
		}

		$this->db->beginTransaction();
	}


	protected function tearDown(): void
	{
		$this->db->rollBack();
		MemorySessionHandler::clear();
	}


	protected static function assertFlash(Page $page, string $type, string $text): void
	{
		self::assertTrue(
			$page->hasFlash($type, $text),
			sprintf("Expected a '%s' message containing \"%s\", the page has:\n%s", $type, $text, self::describeFlashes($page)),
		);
	}


	protected static function assertNoFlash(Page $page, string $text): void
	{
		foreach ($page->getFlashes() as $flash) {
			self::assertStringNotContainsString($text, $flash['text'], "Unexpected message:\n" . self::describeFlashes($page));
		}
	}


	/**
	 * Messages of the page except the static information boxes (specifications, "the solution is a password").
	 * @return list<string>  "type: text"
	 */
	protected static function getErrors(Page $page): array
	{
		return array_values(array_map(
			static fn(array $flash): string => $flash['text'],
			array_filter($page->getFlashes(), static fn(array $flash): bool => $flash['type'] === 'error'),
		));
	}


	protected static function assertNoErrors(Page $page): void
	{
		self::assertSame([], self::getErrors($page), 'Unexpected error messages.');
	}


	/**
	 * The newest messages of the INFO screen (the team's log) are exactly these, newest first. Entries logged
	 * in the same second (e.g. an arrival and its entry message) must keep the order they were logged in.
	 */
	protected static function assertInfo(Team $team, ?string ...$newestFirst): void
	{
		$expected = array_values(array_filter($newestFirst, static fn(?string $text): bool => $text !== null));
		$messages = $team->infoMessages();
		self::assertSame(
			$expected,
			array_slice($messages, 0, count($expected)),
			"Newest INFO messages differ, INFO has:\n  " . implode("\n  ", $messages),
		);
	}


	/**
	 * The time is now (± the given seconds, the test spends some time between the action and the check).
	 */
	protected static function assertNow(mixed $time, int $tolerance = 60, string $message = ''): void
	{
		self::assertInstanceOf(\DateTimeInterface::class, $time, $message ?: 'Expected a time, got null.');
		self::assertEqualsWithDelta(time(), $time->getTimestamp(), $tolerance, $message);
	}


	/**
	 * All four Palainfo screens render without errors (templates get data for every state).
	 */
	protected static function assertScreensRender(Team $team): void
	{
		foreach (['akce' => $team->akce(), 'card' => $team->card(), 'arrivals' => $team->arrivals(), 'info' => $team->info()] as $screen => $page) {
			self::assertSame(200, $page->httpCode, "Screen $screen failed: " . $page->html);
			self::assertStringContainsString('screen-container', $page->html, "Screen $screen is not rendered.");
		}
	}


	private static function describeFlashes(Page $page): string
	{
		$lines = array_map(static fn(array $flash): string => "  {$flash['type']}: {$flash['text']}", $page->getFlashes());
		return $lines === [] ? '  (no messages)' : implode("\n", $lines);
	}
}
