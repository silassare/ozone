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

namespace OZONE\Core\Utils;

use OZONE\Core\App\Settings;
use Redis as PhpRedis;
use RuntimeException;

/**
 * Class RedisFactory.
 *
 * Factory for the shared ext-redis connection.
 *
 * Connection parameters are read from the `oz.redis` settings group.
 * Enable Redis by setting `OZ_REDIS_ENABLED = true` in your app's `oz.redis`
 * settings override.
 *
 * The connection is established lazily on the first call to {@see RedisFactory::get()} and
 * reused for the lifetime of the PHP process. Call {@see RedisFactory::reset()} to discard
 * the cached connection (e.g. after a fork or in tests).
 */
final class RedisFactory
{
	private static ?PhpRedis $instance = null;

	/**
	 * Checks whether the ext-redis PHP extension is installed.
	 *
	 * @return bool
	 */
	public static function isAvailable(): bool
	{
		return \class_exists('\Redis');
	}

	/**
	 * Returns the shared Redis connection, creating it on the first call.
	 *
	 * @return PhpRedis
	 */
	public static function get(): PhpRedis
	{
		if (null === self::$instance) {
			if (!self::isAvailable()) {
				throw new RuntimeException(
					'The PHP ext-redis extension is not installed. '
						. 'Run "apt install php-redis" or equivalent and restart your web server.'
				);
			}

			$host       = (string) Settings::get('oz.redis', 'OZ_REDIS_HOST', '127.0.0.1');
			$port       = (int) Settings::get('oz.redis', 'OZ_REDIS_PORT', 6379);
			$password   = Settings::get('oz.redis', 'OZ_REDIS_PASSWORD');
			$database   = (int) Settings::get('oz.redis', 'OZ_REDIS_DATABASE', 0);
			$timeout    = (float) Settings::get('oz.redis', 'OZ_REDIS_TIMEOUT', 2.5);
			$persistent = (bool) Settings::get('oz.redis', 'OZ_REDIS_PERSISTENT', false);
			$prefix     = (string) Settings::get('oz.redis', 'OZ_REDIS_PREFIX', '');

			$redis = new PhpRedis();

			if ($persistent) {
				$redis->pconnect($host, $port, $timeout);
			} else {
				$redis->connect($host, $port, $timeout);
			}

			if (null !== $password && '' !== $password) {
				$redis->auth($password);
			}

			if (0 !== $database) {
				$redis->select($database);
			}

			if ('' !== $prefix) {
				$redis->setOption(PhpRedis::OPT_PREFIX, $prefix . ':');
			}

			// Keep raw string values so we can handle serialization ourselves.
			$redis->setOption(PhpRedis::OPT_SERIALIZER, PhpRedis::SERIALIZER_NONE);

			self::$instance = $redis;
		}

		return self::$instance;
	}

	/**
	 * Discards the shared connection.
	 *
	 * Useful after a fork or in test suites to force re-connection.
	 */
	public static function reset(): void
	{
		self::$instance = null;
	}
}
