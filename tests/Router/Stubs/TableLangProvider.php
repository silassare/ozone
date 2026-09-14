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

namespace OZONE\Tests\Router\Stubs;

use Override;
use OZONE\Core\Router\Interfaces\RouteProviderInterface;
use OZONE\Core\Router\Router;

/**
 * No route, a global parameter: what a router with a route table registers whenever it is created.
 */
final class TableLangProvider implements RouteProviderInterface
{
	public static int $registrations = 0;

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		++self::$registrations;

		$router->addGlobalParam('lang', '[a-z]{2}', static fn (): string => 'en');
	}
}
