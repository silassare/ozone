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

use OZONE\Core\Runtime\Bridges\SwooleBridge;

/**
 * Class SwooleServerTest.
 *
 * OZone served by a Swoole HTTP server, through {@see SwooleBridge}.
 *
 * @internal
 *
 * @group swoole
 *
 * @covers \OZONE\Core\Runtime\Bridges\SwooleBridge
 */
final class SwooleServerTest extends WorkerServerTestCase
{
	protected static function loopName(): string
	{
		return 'swoole';
	}

	protected static function urlVariable(): string
	{
		return 'OZ_TEST_SWOOLE_URL';
	}
}
