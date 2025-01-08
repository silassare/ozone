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

namespace OZONE\Core\Queue;

/**
 * Enum JobState.
 */
enum JobState: int
{
	case PENDING = 0;

	case RUNNING = 1;

	case DONE = 2;

	case FAILED = 3;

	case CANCELLED = 4;
}
