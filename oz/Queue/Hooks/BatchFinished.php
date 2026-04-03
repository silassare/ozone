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

namespace OZONE\Core\Queue\Hooks;

use OZONE\Core\Db\OZJobBatch;
use PHPUtils\Events\Event;

/**
 * Class BatchFinished.
 *
 * Fired when all jobs belonging to a batch have settled into a terminal state
 * (DONE, FAILED, DEAD_LETTER, or CANCELLED). At least one job may have failed.
 */
final class BatchFinished extends Event
{
	/**
	 * BatchFinished constructor.
	 *
	 * @param OZJobBatch $batch     the finished batch entity
	 * @param bool       $has_error true if any job in the batch ended in a non-DONE terminal state
	 */
	public function __construct(
		public readonly OZJobBatch $batch,
		public readonly bool $has_error,
	) {}
}
