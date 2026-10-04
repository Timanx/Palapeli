<?php

declare(strict_types=1);

namespace App\Utils;

use Nette;


final class Utils
{
	use Nette\StaticClass;

	/**
	 * Calculates how many percent of $total is $part (0 when $total is not positive).
	 */
	public static function percentages(int|float $part, int|float $total): float
	{
		return $total > 0 ? ($part / $total) * 100 : 0.0;
	}


	/**
	 * Removes HTML tags from all string values; other values are kept untouched.
	 * @template K of array-key
	 * @param array<K, mixed> $values
	 * @return array<K, mixed>
	 */
	public static function stripTags(array $values): array
	{
		return array_map(
			static fn(mixed $value): mixed => is_string($value) ? strip_tags($value) : $value,
			$values,
		);
	}


	/**
	 * Converts empty strings to null (useful for optional form fields stored in nullable columns).
	 */
	public static function emptyToNull(?string $value): ?string
	{
		return $value === null || trim($value) === '' ? null : $value;
	}
}
