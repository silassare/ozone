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

use OZONE\Core\FS\Filters\ImageFileFilterHandler;
use OZONE\Core\FS\Filters\ImageFilterTokens;

return [
	/**
	 * File uri path format.
	 *
	 * All available parameters:
	 * - oz_file_id
	 * - oz_file_auth_key
	 * - oz_file_auth_ref
	 * - oz_file_name
	 * - oz_file_extension
	 * - oz_file_filters
	 *
	 * the uri format you want for file access must use.
	 *
	 *  oz_file_id
	 *  oz_file_auth_key
	 *  oz_file_auth_ref (Required to create scoped access, so may be in optional part)
	 *
	 * eg:
	 *  /files/ozone-7000000000-fe5017db3a4b07eb5297c745ba198355-thumb.png
	 *  /files/ozone-7000000000-fe5017db3a4b07eb5297c745ba198355-thumb
	 *  /files/ozone-7000000000-eaabf4cdc3f909a61be62e1fa4d231ed-fe5017db3a4b07eb5297c745ba198355-thumb
	 */
	'OZ_GET_FILE_URI_PATH_FORMAT'         => '/files/ozone-{oz_file_id}[-{oz_file_auth_ref}]-{oz_file_auth_key}'
		. '[-{oz_file_filters}][.{oz_file_extension}]',

	/**
	 * Alternative file uri path formats.
	 */
	'OZ_GET_FILE_URI_PATH_FORMAT_ALTS'    => [
		'/uploads/{oz_file_id}[-{oz_file_auth_ref}]-{oz_file_auth_key}[-{oz_file_filters}][/{oz_file_name}]',
	],

	/**
	 * when user download a file should we
	 * specify the uploaded file name in the response headers ?
	 */
	'OZ_GET_FILE_SHOW_REAL_NAME'          => false,

	/**
	 * maximum number of files that can be uploaded at once.
	 */
	'OZ_UPLOAD_FILE_MAX_COUNT'            => 10,

	/**
	 * maximum size of a file: in bytes.
	 */
	'OZ_UPLOAD_FILE_MAX_SIZE'             => 10 * 1000 * 1000, // 10 MB

	/**
	 * maximum total size of all files that can be uploaded at once: in bytes.
	 */
	'OZ_UPLOAD_FILE_MAX_TOTAL_SIZE'       => 100 * 1000 * 1000, // 100 MB

	/**
	 * anonymous upload rate limit: in requests per minute.
	 */
	'OZ_UPLOAD_ANONYMOUS_RATE_LIMIT'      => 10,

	/**
	 * authenticated upload rate limit: in requests per minute.
	 */
	'OZ_UPLOAD_AUTHENTICATED_RATE_LIMIT'  => 100,

	/**
	 * Length in seconds of the per-IP upload window, which the two rates above are counted in.
	 *
	 * @default 60
	 */
	'OZ_UPLOAD_RATE_INTERVAL'             => 60,

	/**
	 * maximum size of one chunk of a chunked upload: in bytes.
	 *
	 * A client asks the server for it and slices accordingly, so raising it makes for fewer requests
	 * on a big file, and lowering it for smaller ones a poor connection can finish. The form of
	 * `/upload/chunk/add` refuses a bigger chunk.
	 */
	'OZ_UPLOAD_CHUNK_MAX_SIZE'            => 1000 * 1000, // 1 MB

	/**
	 * The image driver renditions are rendered with: `auto` (the first the server has: libvips with
	 * ext-ffi and intervention/image-driver-vips, then ext-imagick, then ext-gd), `vips`, `imagick`
	 * or `gd`. A driver named here and missing on the server fails the first rendition, saying so.
	 *
	 * @see \OZONE\Core\FS\Images\Images
	 */
	'OZ_IMAGE_DRIVER'                     => 'auto',

	/**
	 * maximum size of a thumbnail: in pixels.
	 */
	'OZ_THUMBNAIL_MAX_SIZE'               => 640,

	/**
	 * How long an image filter rendition is kept, in seconds (0: never removed). Renditions are files
	 * in the scope's cache directory; the garbage collector removes older ones, rendered again when
	 * next asked for.
	 *
	 * @see ImageFileFilterHandler
	 */
	'OZ_IMAGE_FILTERS_CACHE_TTL'          => 604800,

	/**
	 * The sizes an image filter may render, in pixels: a width, a height or a thumbnail asked for is
	 * snapped to the nearest (the larger on a tie), so the renditions of an image are bounded. The
	 * widths a page asks for in a `srcset` are these.
	 *
	 * @see ImageFilterTokens
	 */
	'OZ_IMAGE_FILTERS_SIZES'              => [
		16, 32, 48, 64, 96, 128, 256, 384, 640, 750, 828, 1080, 1200, 1920, 2048, 3840,
	],

	/**
	 * The largest image, in bytes, whose size and dominant color are read when it is stored (0: no
	 * limit): decoding a huge image takes its memory.
	 *
	 * @see \OZONE\Core\FS\Images\ImageProbe
	 */
	'OZ_IMAGE_PROBE_MAX_SIZE'             => 30 * 1000 * 1000,

	/**
	 * Watermarks by name, drawn by the token `wm{name}` (a name of `[a-z0-9]`, at most 32):
	 *
	 * ```php
	 * 'logo' => [
	 *     'path'     => 'app/assets/watermark.png', // an image, from the project's root
	 *     'position' => 'bottom-right',             // top-left ... center ... bottom-right
	 *     'opacity'  => 0.5,                        // 0 to 1
	 *     'width'    => 20,                         // its width, in % of the image's
	 *     'margin'   => 2,                          // from the edges, in % of the image's width
	 * ],
	 * ```
	 *
	 * @see \OZONE\Core\FS\Images\ImageWatermarks
	 */
	'OZ_IMAGE_WATERMARKS'                 => [],

	/**
	 * Watermarks forced on images, on every rendition and on the plain URL: a list of
	 * `['for_label' => 'product_photo', 'watermark' => 'logo']`. A file's own `watermark` data names
	 * one for that file alone.
	 *
	 * @see \OZONE\Core\FS\Images\ImageWatermarks::enforcedFor()
	 */
	'OZ_IMAGE_WATERMARK_POLICY'           => [],

	/**
	 * A project's own image tokens: classes implementing
	 * `OZONE\Core\FS\Images\Interfaces\ImageTokenInterface`.
	 *
	 * @see \OZONE\Core\FS\Images\ImageTokens
	 */
	'OZ_IMAGE_TOKENS'                     => [],

	/**
	 * The most passes a blur may take, and the most tokens a rendition may have.
	 *
	 * @see ImageFilterTokens
	 */
	'OZ_IMAGE_FILTERS_MAX_BLUR'           => 10,
	'OZ_IMAGE_FILTERS_MAX_TOKENS'         => 12,

	/**
	 * Should we use nginx x-sendfile or x-accel to serve files ?
	 *
	 * @see https://www.nginx.com/resources/wiki/start/topics/examples/xsendfile/
	 * @see https://www.nginx.com/resources/wiki/start/topics/examples/x-accel/
	 * @see https://www.mediasuite.co.nz/blog/proxying-s3-downloads-nginx/
	 */
	'OZ_SERVER_SENDFILE_ENABLED'          => false,

	/**
	 * The path to use for nginx x-sendfile or x-accel.
	 */
	'OZ_SERVER_SENDFILE_REDIRECT_PATH'    => '/send-file/',

	/**
	 * Should we allow direct access to public files or serve them through a dedicated route ?
	 */
	'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED' => false,
];
