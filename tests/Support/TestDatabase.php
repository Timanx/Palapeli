<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Bootstrap;
use Nette\Bootstrap\Configurator;
use Nette\Database\Connection;
use Nette\DI\Container;


/**
 * Dedicated test database, created from docs/schema.sql at the start of every test run. Each test
 * runs in a transaction that is rolled back, so every test starts with empty tables.
 *
 * Connection settings come from environment variables (defaults fit a local XAMPP MariaDB):
 * PALAPELI_TEST_DB_HOST, PALAPELI_TEST_DB_NAME, PALAPELI_TEST_DB_USER, PALAPELI_TEST_DB_PASSWORD.
 */
final class TestDatabase
{
	private static ?Configurator $configurator = null;
	private static ?Connection $connection = null;

	/** @var ?class-string<Container> */
	private static ?string $containerClass = null;


	/**
	 * Drops the test database and creates it again from docs/schema.sql.
	 */
	public static function recreate(): void
	{
		$name = self::env('PALAPELI_TEST_DB_NAME', 'palapeli_phpunit');
		if (!preg_match('~^\w*(test|phpunit)\w*$~i', $name)) {
			// the database is dropped, never let the tests touch a real one
			throw new \LogicException("The test database name must contain 'test' or 'phpunit', '$name' given.");
		}

		$pdo = new \PDO(
			sprintf('mysql:host=%s;charset=utf8mb4', self::env('PALAPELI_TEST_DB_HOST', '127.0.0.1')),
			self::env('PALAPELI_TEST_DB_USER', 'root'),
			self::env('PALAPELI_TEST_DB_PASSWORD', ''),
			[\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
		);
		$pdo->exec("DROP DATABASE IF EXISTS `$name`");
		$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8 COLLATE utf8_czech_ci");
		$pdo->exec("USE `$name`");
		// the tables in the dump are ordered alphabetically, not by their foreign keys
		$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
		$pdo->exec((string) file_get_contents(__DIR__ . '/../../docs/schema.sql'));
		$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
	}


	/**
	 * Configurator of the application connected to the test database (debug mode, so exceptions are not
	 * turned into error pages).
	 */
	public static function getConfigurator(): Configurator
	{
		if (self::$configurator === null) {
			$tempDir = __DIR__ . '/../../temp/tests';
			@mkdir($tempDir, 0777, true);

			self::$configurator = Bootstrap::createConfigurator($tempDir, debugMode: true);
			self::$configurator->addConfig([
				'database' => [
					'dsn' => sprintf(
						'mysql:host=%s;dbname=%s',
						self::env('PALAPELI_TEST_DB_HOST', '127.0.0.1'),
						self::env('PALAPELI_TEST_DB_NAME', 'palapeli_phpunit'),
					),
					'user' => self::env('PALAPELI_TEST_DB_USER', 'root'),
					'password' => self::env('PALAPELI_TEST_DB_PASSWORD', ''),
					'options' => ['charset' => 'utf8mb4'],
					// the Tracy panel would add a query listener to the shared connection in every request
					'debugger' => false,
				],
				// the Latte panel registers itself on the global Tracy bar and keeps every rendered presenter
				'latte' => ['debugger' => false],
			]);
		}

		return self::$configurator;
	}


	/**
	 * New, not initialized DI container. The container class is checked for changes (and compiled) only once
	 * per test run, not before every simulated request.
	 */
	public static function createContainer(): Container
	{
		self::$containerClass ??= self::getConfigurator()->loadContainer();
		$container = new (self::$containerClass);
		// the global Tracy bar and blue screen would collect panels (and with them every container) of the whole test run
		$container->addService('tracy.bar', new \Tracy\Bar);
		$container->addService('tracy.blueScreen', new \Tracy\BlueScreen);
		return $container;
	}


	/**
	 * One connection shared by the tests and all simulated requests, so that they see each other's
	 * uncommitted data and everything can be rolled back.
	 */
	public static function getConnection(): Connection
	{
		if (self::$connection === null) {
			self::$connection = self::getConfigurator()->createContainer()->getByType(Connection::class);
			self::configureConnection(self::$connection);
		}

		return self::$connection;
	}


	public static function configureConnection(Connection $connection): void
	{
		// CURRENT_TIMESTAMP in SQL (game start/end) must agree with the PHP clock (Europe/Prague)
		$connection->query('SET time_zone = ?', date('P'));
	}


	private static function env(string $name, string $default): string
	{
		$value = getenv($name);
		return $value === false ? $default : $value;
	}
}
