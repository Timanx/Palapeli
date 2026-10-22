<?php

declare(strict_types=1);

namespace Tests\Support;

use Nette\Database\Connection;
use Nette\Database\Row;


/**
 * Edition of the game set up in the test database (the current one), with its ciphers and teams.
 */
final class Game
{
	public const Year = 100;
	public const FinishLocation = 'Hospoda U Palapeli';
	public const AfterpartyLocation = 'Klub Afterparty';


	/**
	 * @param array<int, Cipher> $ciphers  checkpoint number => cipher (0 = start, count - 1 = finish)
	 */
	private function __construct(
		private readonly Connection $db,
		public readonly array $ciphers,
		public readonly bool $hasFinishCipher,
	) {
	}


	/**
	 * @param array<int, Cipher> $ciphers  checkpoint number => cipher, 0 = start, the last one = finish
	 */
	public static function create(
		Connection $db,
		array $ciphers,
		bool $hasFinishCipher = true,
		bool $hintForStart = false,
		bool $individualStart = false,
		string $gameStart = '-1 hour',
		string $gameEnd = '+12 hours',
	): self
	{
		$db->query('UPDATE years SET is_current = 0');
		$db->query('INSERT INTO years ?', [
			'year' => self::Year,
			'calendar_year' => (int) date('Y'),
			'date' => new \DateTimeImmutable($gameStart),
			'word_numbering' => 'stý',
			'checkpoint_count' => count($ciphers),
			'game_start' => new \DateTimeImmutable($gameStart),
			'game_end' => new \DateTimeImmutable($gameEnd),
			'is_current' => 1,
			'has_finish_cipher' => (int) $hasFinishCipher,
			'hint_for_start_exists' => (int) $hintForStart,
			'individual_start' => (int) $individualStart,
			'finish_location' => self::FinishLocation,
			'finish_open_time' => '20:00',
			'afterparty_location' => self::AfterpartyLocation,
			'afterparty_time' => '22:00',
		]);

		foreach ($ciphers as $checkpoint => $cipher) {
			$db->query('INSERT INTO ciphers ?', ['year' => self::Year, 'checkpoint_number' => $checkpoint] + $cipher->toRow());
		}

		return new self($db, $ciphers, $hasFinishCipher);
	}


	/**
	 * Game with default ciphers; the given ones replace them (checkpoint number => cipher).
	 * @param array<int, Cipher> $ciphers
	 */
	public static function createDefault(Connection $db, int $checkpointCount = 4, array $ciphers = [], mixed ...$options): self
	{
		$all = [];
		for ($i = 0; $i < $checkpointCount; $i++) {
			$all[$i] = $ciphers[$i] ?? Cipher::default($i);
		}

		return self::create($db, $all, ...$options);
	}


	public function getCheckpointCount(): int
	{
		return count($this->ciphers);
	}


	public function getFinish(): int
	{
		return count($this->ciphers) - 1;
	}


	public function cipher(int $checkpoint): Cipher
	{
		return $this->ciphers[$checkpoint];
	}


	/**
	 * Registers a new team in the edition and logs it in on a new phone.
	 */
	public function addTeam(string $name = 'Šnečí tým', bool $registered = true): Team
	{
		$this->db->query('INSERT INTO teams ?', [
			'name' => $name,
			'password' => 'x',
			'phone1' => '',
			'phone2' => '',
			'email1' => '',
			'email2' => '',
		]);
		$teamId = (int) $this->db->getInsertId();
		if ($registered) {
			$this->db->query('INSERT INTO teamsyear ?', ['team_id' => $teamId, 'year' => self::Year, 'paid' => 1]);
		}

		return new Team($this, $this->db, $teamId, $name);
	}


	/**
	 * Moves the game the given number of minutes to the future: all stored times of the edition move back.
	 */
	public function travel(int $minutes): void
	{
		$seconds = $minutes * 60;
		$this->db->query('
			UPDATE results
			SET
				entry_datetime = entry_datetime - INTERVAL ? SECOND,
				pickup_datetime = pickup_datetime - INTERVAL ? SECOND,
				exit_datetime = exit_datetime - INTERVAL ? SECOND
			WHERE year = ?
		', $seconds, $seconds, $seconds, self::Year);
		$this->db->query('UPDATE log SET time = time - INTERVAL ? SECOND WHERE year = ?', $seconds, self::Year);
		$this->db->query('
			UPDATE years
			SET game_start = game_start - INTERVAL ? SECOND, game_end = game_end - INTERVAL ? SECOND
			WHERE year = ?
		', $seconds, $seconds, self::Year);
	}


	/**
	 * Sets the end of the game (relative to now, e.g. "-1 minute").
	 */
	public function setGameEnd(string $time): void
	{
		$this->db->query('UPDATE years SET game_end = ? WHERE year = ?', new \DateTimeImmutable($time), self::Year);
	}


	public function setGameStart(string $time): void
	{
		$this->db->query('UPDATE years SET game_start = ? WHERE year = ?', new \DateTimeImmutable($time), self::Year);
	}


	public function getResult(int $teamId, int $checkpoint): ?Row
	{
		return $this->db->query(
			'SELECT * FROM results WHERE team_id = ? AND year = ? AND checkpoint_number = ?',
			$teamId,
			self::Year,
			$checkpoint,
		)->fetch();
	}


	/**
	 * @return array<int, Row>  checkpoint number => results row
	 */
	public function getResults(int $teamId): array
	{
		return $this->db->query(
			'SELECT * FROM results WHERE team_id = ? AND year = ? ORDER BY checkpoint_number',
			$teamId,
			self::Year,
		)->fetchAssoc('checkpoint_number');
	}


	/**
	 * @return list<Row>  log entries of the team, oldest first
	 */
	public function getLog(int $teamId): array
	{
		return $this->db->query(
			'SELECT * FROM log WHERE team_id = ? AND year = ? ORDER BY id',
			$teamId,
			self::Year,
		)->fetchAll();
	}


	public function hasTeamEnded(int $teamId): bool
	{
		return $this->db->query(
			'SELECT end_time IS NOT NULL FROM teamsyear WHERE team_id = ? AND year = ?',
			$teamId,
			self::Year,
		)->fetchField() == 1;
	}


	/**
	 * Service of the application (a model) working with the test database.
	 * @template T of object
	 * @param class-string<T> $class
	 * @return T
	 */
	public function getService(string $class): object
	{
		$container = TestDatabase::createContainer();
		$container->addService('database.default.connection', $this->db);
		return $container->getByType($class);
	}
}
