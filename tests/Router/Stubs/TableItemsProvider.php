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
 * Static routes, two auto named, and a dynamic route that outranks one of {@see TableItemProvider}.
 */
final class TableItemsProvider implements RouteProviderInterface
{
	public static int $registrations = 0;

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		++self::$registrations;

		$handler = static fn () => null;

		$router->get('/items', $handler)
			->name('items.list');
		$router->post('/items', $handler)
			->name('items.create');
		$router->get('/items/latest', $handler)
			->name('items.latest');
		$router->get('/items/:any', $handler)
			->name('items.any')
			->param('any', 'x-[a-z]+')
			->priority(3);
		$router->get('/anon', $handler);
		$router->post('/anon', $handler);
	}
}
