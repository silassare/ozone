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

use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Router\Interfaces\RouteMiddlewareInterface;

/**
 * Class Middlewares.
 */
final class Middlewares
{
	/**
	 * Gets middlewares class from settings.
	 *
	 * If the name is a class name and is subclass of {@link RouteMiddlewareInterface} then it will be returned.
	 *
	 * @param string $name
	 *
	 * @return class-string<RouteMiddlewareInterface>
	 */
	public static function get(string $name): string
	{
		if (\class_exists($name) && \is_subclass_of($name, RouteMiddlewareInterface::class)) {
			return $name;
		}

		$mdl_class = Settings::get('oz.middlewares', $name);

		if (!$mdl_class) {
			throw (new RuntimeException(\sprintf(
				'Route middleware "%s" not found in settings.',
				$name
			)))->suspectConfig('oz.middlewares', $name);
		}

		if (!\class_exists($mdl_class) || !\is_subclass_of($mdl_class, RouteMiddlewareInterface::class)) {
			throw (new RuntimeException(\sprintf(
				'Route middleware "%s" should be subclass of: %s',
				$mdl_class,
				RouteMiddlewareInterface::class
			)))->suspectConfig('oz.middlewares', $name);
		}

		return $mdl_class;
	}
}
