<?php

declare(strict_types=1);

namespace App\Utils;

use Nette;


/**
 * Brand colors used by templates (each web section has its own color).
 */
final class Colors
{
	use Nette\StaticClass;

	public const Blue = '#005BD0';
	public const Red = '#FF0000';
	public const Yellow = '#FFCA05';
	public const Green = '#69B300';

	public const BlueTint = '#A9CEFF';
	public const RedTint = '#FFB1B1';
	public const YellowTint = '#FFEBA2';
	public const GreenTint = '#D7FF9F';

	public const Team = self::Blue;
	public const Info = self::Red;
	public const Discussion = self::Yellow;
	public const Game = self::Green;
	public const Administration = 'black';
}
