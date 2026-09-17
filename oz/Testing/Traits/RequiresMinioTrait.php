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

namespace OZONE\Core\Testing\Traits;

use OZONE\Core\FS\Drivers\MinioStorage;
use OZONE\Core\FS\FS;
use Throwable;

/**
 * Trait RequiresMinioTrait.
 *
 * For tests of the `minio` group: they are skipped without a reachable MinIO (OZ_MINIO_* in the
 * environment, see docker/compose.yaml), and fail instead when `OZ_TEST_MINIO_REQUIRED` is set.
 */
trait RequiresMinioTrait
{
	/**
	 * Skips (or fails) the test unless MinIO answers; creates the bucket of the private storage.
	 */
	protected static function requireMinio(): void
	{
		try {
			MinioStorage::client()->createBucket(MinioStorage::get(FS::PRIVATE_STORAGE)->getBucket());
		} catch (Throwable $t) {
			if (\getenv('OZ_TEST_MINIO_REQUIRED')) {
				self::fail('MinIO is unavailable: ' . $t->getMessage() . ' (OZ_TEST_MINIO_REQUIRED is set)');
			}

			self::markTestSkipped('MinIO is unavailable: ' . $t->getMessage());
		}
	}
}
