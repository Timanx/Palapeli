<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;


/**
 * Builds the DI container configurator for the whole application.
 */
final class Bootstrap
{
	/**
	 * Environment variable that forces Tracy debug mode:
	 * "1" = on, "0" = off, anything else = comma separated list of allowed IP addresses.
	 * When it is not set, debug mode is enabled for requests from localhost only.
	 */
	public const DebugEnvVariable = 'PALAPELI_DEBUG';


	public static function boot(): Configurator
	{
		$appDir = __DIR__;
		$rootDir = dirname($appDir);

		$configurator = self::createConfigurator($rootDir . '/temp', self::detectDebugMode());
		$configurator->enableTracy($rootDir . '/log');
		$configurator->addConfig($appDir . '/config/config.neon');

		$localConfig = $appDir . '/config/config.local.neon';
		if (is_file($localConfig)) {
			$configurator->addConfig($localConfig);
		}

		return $configurator;
	}


	/**
	 * Configurator with the versioned configuration only (no credentials, no Tracy);
	 * the web adds config.neon to it, the tests their own database configuration.
	 */
	public static function createConfigurator(string $tempDir, bool $debugMode): Configurator
	{
		$appDir = __DIR__;
		$rootDir = dirname($appDir);

		$configurator = new Configurator;
		$configurator->setDebugMode($debugMode);
		$configurator->setTimeZone('Europe/Prague');
		$configurator->setTempDirectory($tempDir);
		$configurator->addStaticParameters([
			'rootDir' => $rootDir,
			'wwwDir' => $rootDir . '/www',
		]);

		$configurator->createRobotLoader()
			->addDirectory($appDir)
			->register();

		$configurator->addConfig($appDir . '/config/common.neon');
		$configurator->addConfig($appDir . '/config/services.neon');
		return $configurator;
	}


	private static function detectDebugMode(): bool
	{
		$value = getenv(self::DebugEnvVariable);
		return match ($value) {
			false, '' => Configurator::detectDebugMode(),
			'1' => true,
			'0' => false,
			default => Configurator::detectDebugMode($value),
		};
	}
}
