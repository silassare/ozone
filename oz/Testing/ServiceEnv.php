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

namespace OZONE\Core\Testing;

/**
 * Class ServiceEnv.
 *
 * The service addresses a test project inherits from its environment -- the Redis, MinIO and ClamAV
 * containers of a Docker test setup (OZone's `docker/compose.yaml`).
 *
 * One list, because both suites need it and they used to keep their own: the unit sandbox forwarded
 * these, `OZTestProject` forwarded only the database, and the image ships `ext-redis` -- which makes
 * `OZ_REDIS_ENABLED` default to true. Every integration project therefore registered the Redis job
 * store and dialled the default `127.0.0.1`, so `oz jobs run`, `oz cron run` and friends failed with
 * "Connection refused" while a healthy Redis sat one hostname away.
 */
final class ServiceEnv
{
	/**
	 * @var list<string>
	 */
	public const KEYS = [
		'OZ_REDIS_HOST',
		'OZ_REDIS_PORT',
		'OZ_REDIS_PASSWORD',
		'OZ_MINIO_ENDPOINT',
		'OZ_MINIO_ACCESS_KEY',
		'OZ_MINIO_SECRET_KEY',
		'OZ_MINIO_BUCKET',
		'OZ_CLAMAV_HOST',
		'OZ_CLAMAV_PORT',
		'OZ_CLAMAV_SOCKET',
	];

	/**
	 * The service variables this environment actually defines.
	 *
	 * @return array<string, string>
	 */
	public static function fromEnvironment(): array
	{
		$found = [];

		foreach (self::KEYS as $key) {
			$value = \getenv($key);

			if (false !== $value && '' !== $value) {
				$found[$key] = $value;
			}
		}

		return $found;
	}
}
