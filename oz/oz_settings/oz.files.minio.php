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

use OZONE\Core\FS\Drivers\MinioStorage;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\S3\S3Client;

/**
 * MinIO, or any service speaking the S3 API, for {@see MinioStorage}.
 *
 * Nothing uses it until a storage slot of `oz.files.storages` is mapped to the driver, e.g.
 * `FS::PRIVATE_STORAGE => MinioStorage::class`. The credentials come from the project `.env`.
 */
return [
	/**
	 * The service URL OZone calls, e.g. `http://minio:9000` in a Docker network.
	 */
	'OZ_MINIO_ENDPOINT'        => env('OZ_MINIO_ENDPOINT', 'http://127.0.0.1:9000'),

	/**
	 * The service URL clients use, for presigned and direct links; null: the endpoint.
	 */
	'OZ_MINIO_PUBLIC_ENDPOINT' => env('OZ_MINIO_PUBLIC_ENDPOINT'),

	/**
	 * The region (MinIO accepts any).
	 */
	'OZ_MINIO_REGION'          => env('OZ_MINIO_REGION', 'us-east-1'),

	'OZ_MINIO_ACCESS_KEY'      => env('OZ_MINIO_ACCESS_KEY'),
	'OZ_MINIO_SECRET_KEY'      => env('OZ_MINIO_SECRET_KEY'),

	/**
	 * Whether the bucket is in the path (MinIO) rather than in the host name (`<bucket>.<host>`).
	 */
	'OZ_MINIO_PATH_STYLE'      => true,

	/**
	 * Whether to verify the TLS certificate of an `https` endpoint.
	 */
	'OZ_MINIO_VERIFY_TLS'      => true,

	/**
	 * Connection and read timeout, in seconds.
	 */
	'OZ_MINIO_TIMEOUT'         => 30,

	/**
	 * Size, in bytes, of one part of a multipart upload.
	 *
	 * It is also the size above which an upload is split into parts, and therefore the peak memory
	 * an upload costs when `ext-curl` is missing (the fallback transport cannot stream a request
	 * body, so it sends one part at a time). The service rejects a non-final part below 5 MiB, so
	 * a smaller value is raised to that.
	 *
	 * @see S3Client::DEFAULT_PART_SIZE
	 */
	'OZ_MINIO_PART_SIZE'       => S3Client::DEFAULT_PART_SIZE,

	/**
	 * How a file is sent once OZone checked the access: `proxy` (through PHP, like local files)
	 * or `redirect` (to a presigned URL valid OZ_MINIO_PRESIGN_TTL seconds).
	 */
	'OZ_MINIO_SERVE_MODE'      => 'proxy',

	/**
	 * Lifetime of presigned URLs, in seconds.
	 */
	'OZ_MINIO_PRESIGN_TTL'     => 300,

	/**
	 * Storage slot -> bucket, key prefix, and whether its files are public. A public slot hands out
	 * direct links when OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED is on (`oz.files`); the bucket must then
	 * allow anonymous reads of that prefix.
	 */
	'OZ_MINIO_BUCKETS'         => [
		FS::DEFAULT_STORAGE => ['bucket' => env('OZ_MINIO_BUCKET', 'ozone'), 'prefix' => 'public', 'public' => true],
		FS::PUBLIC_STORAGE  => ['bucket' => env('OZ_MINIO_BUCKET', 'ozone'), 'prefix' => 'public', 'public' => true],
		FS::PRIVATE_STORAGE => ['bucket' => env('OZ_MINIO_BUCKET', 'ozone'), 'prefix' => 'private', 'public' => false],
	],
];
