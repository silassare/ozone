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

namespace OZONE\Core\Hooks\Events;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use PHPUtils\Events\Event;

/**
 * Class DbReadyHook.
 *
 * This event is triggered when the database is loaded and ready to be used.
 */
final class DbReadyHook extends Event
{
	/**
	 * DbReadyHook constructor.
	 *
	 * @param RDBMSInterface $db the database instance
	 */
	public function __construct(public readonly RDBMSInterface $db) {}
}
