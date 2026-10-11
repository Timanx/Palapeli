<?php

declare(strict_types=1);

namespace App\Models;

use Nette\Database\Explorer;


/**
 * Common ancestor of all database models.
 *
 * Models are stateless: every method that works with data of one game edition
 * receives the year number explicitly.
 */
abstract class BaseModel
{
	public function __construct(
		protected readonly Explorer $database,
	) {
	}
}
