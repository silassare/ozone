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
use OZONE\Core\Router\Route;
use OZONE\Core\Router\Router;

/**
 * Dynamic routes, one using the global parameter of {@see TableLangProvider}, and a route whose
 * refiner maps a sibling.
 */
final class TableItemProvider implements RouteProviderInterface
{
	public static int $registrations = 0;

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		++self::$registrations;

		$handler = static fn () => null;

		$router->get('/items/:id', $handler)
			->name('item.get')
			->param('id', '[0-9]+');
		$router->delete('/items/:id', $handler)
			->name('item.delete')
			->param('id', '[0-9]+');
		$router->get('/items/:slug', $handler)
			->name('item.by_slug');
		$router->get('/refined', $handler)
			->name('refined')
			->pushRefiner(static function (Router $router, Route $route) use ($handler): void {
				$router->post('/refined/sibling', $handler)
					->name('refined.sibling');
			});
		$router->get('/:lang/about', $handler)
			->name('about');
	}
}
