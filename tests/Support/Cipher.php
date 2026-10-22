<?php

declare(strict_types=1);

namespace Tests\Support;


/**
 * Configuration of one checkpoint and its cipher (a row of the ciphers table), i.e. every option the
 * administration offers for a cipher that matters to Palainfo.
 */
final class Cipher
{
	public function __construct(
		/** code found on the checkpoint; null = the checkpoint cannot be reached in Palainfo */
		public ?string $code = null,
		/** "Řešením je heslo": the team leaves the checkpoint by typing the password */
		public bool $password = false,
		public ?string $solution = null,
		/** text of the total hint / location revealed by the password; falls back to the solution */
		public ?string $deadSolution = null,
		public ?string $entryMessage = null,
		/** "upřesnítko" of the checkpoint location */
		public ?string $specification = null,
		public ?int $waitMinutes = null,
		public ?string $waitMessage = null,
		public ?string $pickupCode = null,
		/** H:i */
		public ?string $closeTime = null,
	) {
	}


	/**
	 * A typical cipher of the given checkpoint: a code, a solution and a total hint, nothing else.
	 */
	public static function default(int $checkpoint): self
	{
		return new self(
			code: "Kod{$checkpoint}X",
			solution: "Tajné heslo $checkpoint",
			deadSolution: 'Další stanoviště je u kapličky č. ' . ($checkpoint + 1) . '.',
		);
	}


	/**
	 * Copy with the given options changed, e.g. $cipher->with(password: true, waitMinutes: 15).
	 */
	public function with(mixed ...$options): self
	{
		$clone = clone $this;
		foreach ($options as $name => $value) {
			if (!property_exists($this, (string) $name)) {
				throw new \InvalidArgumentException("Unknown cipher option '$name'.");
			}

			$clone->$name = $value;
		}

		return $clone;
	}


	/**
	 * Text a team gets for the total hint or for the correct password (dead_solution, or the solution).
	 */
	public function getRevealedText(): string
	{
		return (string) ($this->deadSolution ?: $this->solution);
	}


	public function hasWait(): bool
	{
		return $this->waitMinutes !== null && $this->waitMinutes > 0;
	}


	/**
	 * @return array<string, mixed>  columns of the ciphers table
	 */
	public function toRow(): array
	{
		return [
			'code' => $this->code,
			'has_password_solution' => (int) $this->password,
			'solution' => $this->solution,
			'dead_solution' => $this->deadSolution,
			'entry_message' => $this->entryMessage,
			'specification' => $this->specification,
			'wait_minutes' => $this->waitMinutes,
			'wait_message' => $this->waitMessage,
			'pickup_code' => $this->pickupCode,
			'checkpoint_close_time' => $this->closeTime,
		];
	}


	/**
	 * Short description for test names, e.g. "password, wait 15 min + pickup code, entry message".
	 */
	public function describe(): string
	{
		$parts = [$this->password ? 'password' : 'location'];
		if ($this->hasWait()) {
			$parts[] = "wait {$this->waitMinutes} min" . ($this->pickupCode === null ? ' without pickup code' : ' + pickup code');
		}

		if ($this->entryMessage !== null) {
			$parts[] = 'entry message';
		}

		if ($this->specification !== null) {
			$parts[] = 'specification';
		}

		$parts[] = $this->deadSolution === null ? 'hint = solution' : 'own hint text';
		return implode(', ', $parts);
	}
}
