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
 * Routes of every host mapped before routes of one host on the same paths, static and dynamic: the
 * host-bound ones must win on their host, and only there.
 */
final class TableHostProvider implements RouteProviderInterface
{
	#[Override]
	public static function routesVersion(): ?string
	{
		return null;
	}

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$handler = static fn () => null;

		$router->get('/dashboard', $handler)->name('any.dashboard');
		$router->get('/projects/:id', $handler)->name('any.project');

		$router->group('/', static function (Router $router) use ($handler): void {
			$router->get('/dashboard', $handler)->name('tenant.dashboard');
			$router->get('/projects/:id', $handler)->name('tenant.project');
			$router->post('/projects', $handler)->name('tenant.create');
		})
			->host('{tenant}.example.com')
			->param('tenant', '[a-z0-9-]+');

		$router->get('/admin', $handler)
			->name('admin')
			->host('admin.example.com');
	}
}
