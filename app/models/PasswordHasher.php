<?php

declare(strict_types=1);

namespace App\Models;


/**
 * Hashing of team passwords and tokens of password reset links.
 *
 * Passwords are stored as unsalted RIPEMD-160 hex digests. This scheme is kept for
 * compatibility with existing accounts; it is weak and should be migrated to
 * password_hash() (see docs/security.md). All password handling goes through this class,
 * so the migration only needs to change it.
 */
final class PasswordHasher
{
	/** How long a password reset link is valid. */
	public const ResetLinkValidity = '+24 hours';


	public function hash(string $password): string
	{
		return hash('ripemd160', $password);
	}


	public function verify(string $password, ?string $storedHash): bool
	{
		return $storedHash !== null && hash_equals($storedHash, $this->hash($password));
	}


	/**
	 * Token of a password reset link valid until $expires (timestamp).
	 *
	 * The token is signed with the current password hash of the team, so it is not stored anywhere
	 * and stops working once the password is changed (the link can be used only once).
	 */
	public function createResetToken(int $teamId, int $expires, string $storedHash): string
	{
		return hash_hmac('sha256', $teamId . '|' . $expires, $storedHash);
	}


	public function verifyResetToken(int $teamId, int $expires, string $token, ?string $storedHash): bool
	{
		return $storedHash !== null
			&& $expires >= time()
			&& hash_equals($this->createResetToken($teamId, $expires, $storedHash), $token);
	}
}
