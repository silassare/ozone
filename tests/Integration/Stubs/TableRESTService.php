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

namespace __PLH_NAMESPACE__;

use OZONE\Core\REST\RESTFulService;
use OZONE\Core\Router\Router;

/**
 * The REST service of the `__PLH_TABLE__` table, and nothing else: one per table, each in a
 * namespace of its own.
 */
final class TableRESTService extends RESTFulService
{
	public const string SERVICE_NAME = '__PLH_TABLE__';
	public const string SERVICE_PATH = '/__PLH_TABLE__';
	public const string TABLE_NAME   = '__PLH_TABLE__';

	public static function registerRoutes(Router $router): void
	{
		self::registerRESTRoutes($router);
	}
}
