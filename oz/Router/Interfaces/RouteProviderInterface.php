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

namespace OZONE\Core\Router\Interfaces;

use OZONE\Core\Router\Router;

/**
 * Interface RouteProviderInterface.
 */
interface RouteProviderInterface
{
	/**
	 * Called to register the service routes.
	 */
	public static function registerRoutes(Router $router);

	/**
	 * What the routes this provider maps were built from, or null for routes written in code.
	 *
	 * In production a router routes through a table compiled once and keyed on the release, the
	 * providers and the stateful settings: a provider whose routes come from data that changes
	 * without any of those -- files edited on the server, rows in a database -- answers a version of
	 * that data here, and a new one compiles a new table. Asked on every request served from a
	 * table, so it answers from something cheap to read, never by reading the data itself.
	 */
	public static function routesVersion(): ?string;
}
