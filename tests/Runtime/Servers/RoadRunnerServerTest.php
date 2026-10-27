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

namespace OZONE\Tests\Runtime\Servers;

use OZONE\Core\Runtime\Bridges\RoadRunnerBridge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Class RoadRunnerServerTest.
 *
 * OZone served by RoadRunner, through {@see RoadRunnerBridge}.
 *
 * @internal
 */
#[Group('roadrunner')]
#[CoversClass(RoadRunnerBridge::class)]
final class RoadRunnerServerTest extends WorkerServerTestCase
{
	protected static function loopName(): string
	{
		return 'roadrunner';
	}

	protected static function urlVariable(): string
	{
		return 'OZ_TEST_ROADRUNNER_URL';
	}
}
