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

		$configurator = new Configurator;
		$configurator->setDebugMode(self::detectDebugMode());
		$configurator->enableTracy($rootDir . '/log');

		$configurator->setTimeZone('Europe/Prague');
		$configurator->setTempDirectory($rootDir . '/temp');
		$configurator->addStaticParameters([
			'rootDir' => $rootDir,
			'wwwDir' => $rootDir . '/www',
		]);

		$configurator->createRobotLoader()
			->addDirectory($appDir)
			->register();

		$configurator->addConfig($appDir . '/config/common.neon');
		$configurator->addConfig($appDir . '/config/services.neon');
		$configurator->addConfig($appDir . '/config/config.neon');

		$localConfig = $appDir . '/config/config.local.neon';
		if (is_file($localConfig)) {
			$configurator->addConfig($localConfig);
		}

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
