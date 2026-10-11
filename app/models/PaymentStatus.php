<?php

declare(strict_types=1);

namespace App\Models;


/**
 * Entry fee payment state of a team in one year (column teamsyear.paid).
 */
enum PaymentStatus: int
{
	case Unpaid = 0;
	case Paid = 1;
	case AtStart = 2;


	public function label(): string
	{
		return match ($this) {
			self::Unpaid => 'Nezaplaceno',
			self::Paid => 'Zaplaceno',
			self::AtStart => 'Platba na startu',
		};
	}


	/**
	 * @return array<int, string>  value => label, for select boxes and radio lists
	 */
	public static function options(): array
	{
		$options = [];
		foreach (self::cases() as $case) {
			$options[$case->value] = $case->label();
		}

		return $options;
	}
}
