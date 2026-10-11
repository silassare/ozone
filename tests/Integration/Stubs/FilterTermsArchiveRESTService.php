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
 * A second REST service over the `terms` table of RESTFilterRefusalTest: both register the access
 * rights actions named after that table.
 */
final class FilterTermsArchiveRESTService extends RESTFulService
{
	public const SERVICE_NAME = 'terms_archive';
	public const SERVICE_PATH = '/terms-archive';
	public const TABLE_NAME   = 'terms';

	public static function registerRoutes(Router $router): void
	{
		self::registerRESTRoutes($router);
	}
}
