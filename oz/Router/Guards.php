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
use OZONE\Core\Router\Interfaces\RouteGuardInterface;
use OZONE\Core\Router\Interfaces\RouteGuardProviderInterface;

/**
 * Class Guards.
 */
final class Guards
{
	/**
	 * Resolves route guards from rules.
	 *
	 * @param array<string, array> $rules map of guard class or name to rules
	 *                                    name should be declared in settings: oz.guards
	 *
	 * @return RouteGuardInterface[]
	 */
	public static function resolve(array $rules): array
	{
		$guards = [];

		foreach ($rules as $name => $rule) {
			/** @var RouteGuardInterface $guard_class */
			$guard_class = self::get($name);

			$guards[] = $guard_class::fromRules($rule);
		}

		return $guards;
	}

	/**
	 * Gets guard class from settings.
	 *
	 * If the name is a class name and is subclass of {@link RouteGuardInterface} then it will be returned.
	 *
	 * @param string $name
	 *
	 * @return class-string<RouteGuardInterface>
	 */
	public static function get(string $name): string
	{
		if (\class_exists($name) && \is_subclass_of($name, RouteGuardInterface::class)) {
			return $name;
		}

		$guard_class = Settings::get('oz.guards', $name);

		if (!$guard_class) {
			throw (new RuntimeException(\sprintf(
				'Route guard "%s" not found in settings.',
				$name
			)))->suspectConfig('oz.guards', $name);
		}

		if (!\class_exists($guard_class) || !\is_subclass_of($guard_class, RouteGuardInterface::class)) {
			throw (new RuntimeException(\sprintf(
				'Route guard "%s" should be subclass of: %s',
				$guard_class,
				RouteGuardInterface::class
			)))->suspectConfig('oz.guards', $name);
		}

		return $guard_class;
	}

	/**
	 * Gets guard provider class from settings.
	 *
	 * @param string $name
	 *
	 * @return class-string<RouteGuardProviderInterface>
	 */
	public static function provider(string $name): string
	{
		$provider_class = Settings::get('oz.guards.providers', $name);

		if (!$provider_class) {
			throw (new RuntimeException(\sprintf(
				'Route guard provider "%s" not found in settings.',
				$name
			)))->suspectConfig('oz.guards.providers', $name);
		}

		if (!\class_exists($provider_class) || !\is_subclass_of($provider_class, RouteGuardProviderInterface::class)) {
			throw (new RuntimeException(\sprintf(
				'Route guard provider "%s" should be subclass of: %s',
				$provider_class,
				RouteGuardProviderInterface::class
			)))->suspectConfig('oz.guards.providers', $name);
		}

		return $provider_class;
	}
}
