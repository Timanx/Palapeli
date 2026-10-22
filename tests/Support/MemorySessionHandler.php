<?php

declare(strict_types=1);

namespace Tests\Support;


/**
 * Session storage of the simulated browsers: sessions survive between requests of one test run
 * in the same PHP process, nothing is written to disk.
 */
final class MemorySessionHandler implements \SessionHandlerInterface
{
	/** @var array<string, string> */
	private static array $data = [];


	public static function clear(): void
	{
		self::$data = [];
	}


	public function open(string $path, string $name): bool
	{
		return true;
	}


	public function close(): bool
	{
		return true;
	}


	public function read(string $id): string
	{
		return self::$data[$id] ?? '';
	}


	public function write(string $id, string $data): bool
	{
		self::$data[$id] = $data;
		return true;
	}


	public function destroy(string $id): bool
	{
		unset(self::$data[$id]);
		return true;
	}


	public function gc(int $max_lifetime): int
	{
		return 0;
	}
}
