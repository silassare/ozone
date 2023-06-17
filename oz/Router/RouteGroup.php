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

namespace OZONE\Core\Router;

/**
 * Class RouteGroup.
 */
final class RouteGroup extends RouteSharedOptions
{
	private static int $group_count = 0;

	public function __construct(string $path, ?self $parent = null)
	{
		parent::__construct($path, $parent);

		$this->name('route_group_' . (++self::$group_count));
	}
}
