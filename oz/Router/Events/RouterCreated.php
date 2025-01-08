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

namespace OZONE\Core\Router\Events;

use OZONE\Core\Router\RouteGroup;
use OZONE\Core\Router\Router;
use PHPUtils\Events\Event;

/**
 * Class RouterCreated.
 *
 * This event is triggered when the router is created.
 * And gives you the opportunity to add global middlewares, guards, auth methods etc.
 */
final class RouterCreated extends Event
{
	/**
	 * RouterCreated constructor.
	 *
	 * @param Router     $router        the router
	 * @param RouteGroup $topLevelGroup the top level group
	 * @param bool       $isApi         true if the router is for api
	 */
	public function __construct(
		public readonly Router $router,
		public readonly RouteGroup $topLevelGroup,
		public readonly bool $isApi,
	) {}
}
