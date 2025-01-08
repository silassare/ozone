<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace OZONE\Core\Migrations\Events;

use PHPUtils\Events\Event;

/**
 * Class MigrationCreated.
 *
 * This event is triggered just after a migration is created.
 */
final class MigrationCreated extends Event
{
	/**
	 * MigrationCreated constructor.
	 *
	 * @param int $version the migration version
	 */
	public function __construct(
		public readonly int $version
	) {}
}
