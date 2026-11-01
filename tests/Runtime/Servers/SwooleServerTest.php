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

use Override;
use OZONE\Core\Runtime\Bridges\SwooleBridge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Class SwooleServerTest.
 *
 * OZone served by a Swoole HTTP server, through {@see SwooleBridge}.
 *
 * @internal
 */
#[Group('swoole')]
#[CoversClass(SwooleBridge::class)]
final class SwooleServerTest extends WorkerServerTestCase
{
	#[Override]
	protected static function loopName(): string
	{
		return 'swoole';
	}

	#[Override]
	protected static function urlVariable(): string
	{
		return 'OZ_TEST_SWOOLE_URL';
	}
}
