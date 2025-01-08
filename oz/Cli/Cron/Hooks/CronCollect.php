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

namespace OZONE\Core\Cli\Cron\Hooks;

use PHPUtils\Events\Event;

/**
 * Class CronCollect.
 *
 * This event is triggered for you to register the project cron tasks.
 */
final class CronCollect extends Event
{
	/**
	 * CronCollect constructor.
	 */
	public function __construct() {}
}
