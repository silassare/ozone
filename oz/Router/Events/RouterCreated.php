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
	 * @param Router $router
	 * @param RouteGroup $top_level_group
	 * @param bool $is_api
	 */
	public function __construct(
		protected Router     $router,
		protected RouteGroup $top_level_group,
		protected bool       $is_api,
	)
	{
	}

	/**
	 * RouterCreated destructor.
	 */
	public function __destruct()
	{
		unset($this->router, $this->top_level_group);
	}

	/**
	 * Gets the router.
	 *
	 * @return Router
	 */
	public function getRouter(): Router
	{
		return $this->router;
	}

	/**
	 * Gets the top level group.
	 *
	 * @return RouteGroup
	 */
	public function getTopLevelGroup(): RouteGroup
	{
		return $this->top_level_group;
	}

	/**
	 * Is API router.
	 *
	 * @return bool
	 */
	public function isApi(): bool
	{
		return $this->is_api;
	}
}
