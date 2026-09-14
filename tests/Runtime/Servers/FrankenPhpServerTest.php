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

use OZONE\Core\Runtime\Bridges\FrankenPhpBridge;

/**
 * Class FrankenPhpServerTest.
 *
 * OZone served by FrankenPHP in worker mode, through {@see FrankenPhpBridge}.
 *
 * @internal
 *
 * @group frankenphp
 *
 * @covers \OZONE\Core\Runtime\Bridges\FrankenPhpBridge
 */
final class FrankenPhpServerTest extends WorkerServerTestCase
{
	protected static function loopName(): string
	{
		return 'frankenphp';
	}

	protected static function urlVariable(): string
	{
		return 'OZ_TEST_FRANKENPHP_URL';
	}
}
