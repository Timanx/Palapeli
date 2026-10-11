<?php

declare(strict_types=1);

namespace App\Models;

use Nette\Utils\Random;


/**
 * Hashing of team passwords.
 *
 * Passwords are stored as unsalted RIPEMD-160 hex digests. This scheme is kept for
 * compatibility with existing accounts; it is weak and should be migrated to
 * password_hash() (see docs/security.md). All password handling goes through this class,
 * so the migration only needs to change it.
 */
final class PasswordHasher
{
	/** Length and alphabet of generated passwords (forgotten password feature). */
	private const GeneratedLength = 7;
	private const GeneratedCharacters = 'a-z';


	public function hash(string $password): string
	{
		return hash('ripemd160', $password);
	}


	public function verify(string $password, ?string $storedHash): bool
	{
		return $storedHash !== null && hash_equals($storedHash, $this->hash($password));
	}


	public function generatePassword(): string
	{
		return Random::generate(self::GeneratedLength, self::GeneratedCharacters);
	}
}
