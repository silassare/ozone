<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// @ <%if(@length($.oz_header)){%><%$.oz_header%><%}%>
declare(strict_types=1);

namespace MY_SERVICE_NS;

use Gobl\DBAL\Table;
use OZONE\Core\App\Context;
use OZONE\Core\App\Service;
use OZONE\Core\REST\Traits\RESTFulService;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use Throwable;

/**
 * Class MyService.
 *
 * - Add item to `my_table`
 * ```
 * POST /my_path
 * ```
 *
 * - Get the item with the given `:my_id`
 * ```
 * GET /my_path/:my_id
 * ```
 *
 * - Get all items in my_table
 * ```
 * GET /my_path
 * ```
 *
 * - Get a specified relation `:relation` for the item in `my_table` with the given `:my_id`
 * ```
 * GET /my_path/:my_id/:relation
 * ```
 *
 * - Update property(ies) of the item with the given `:my_id`
 * ```
 * PATCH /my_path/:my_id
 * ```
 *
 * - Update property(ies) of all items in `my_table`
 * ```
 * PATCH /my_path
 * ```
 *
 * - Delete item with the given `:my_id`
 * ```
 * DELETE /my_path/:my_id
 * ```
 *
 * - Delete all items in `my_table`
 * ```
 * DELETE  /my_path
 * ```
 */
final class MyService extends Service
{
	use RESTFulService;

	public const SERVICE_PATH = '/my_path';
	public const TABLE_NAME   = 'my_table';
	public const KEY_COLUMN   = 'my_id';

	protected Table $table;

	/**
	 * MyService constructor.
	 *
	 * @param Context|RouteInfo $context
	 */
	public function __construct(Context|RouteInfo $context)
	{
		parent::__construct($context);

		$this->table = db()->getTableOrFail(self::TABLE_NAME);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	public static function registerRoutes(Router $router): void
	{
		self::registerRESTRoutes($router);
	}
}
