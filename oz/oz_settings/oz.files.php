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

return [
	/**
	 * the uri format you want for file access must use/provide.
	 *
	 *  oz_file_id
	 *  oz_file_key
	 *
	 * eg:
	 *  /files/ozone-7000000000-fe5017db3a4b07eb5297c745ba198355-thumb.png
	 *  /files/ozone-7000000000-fe5017db3a4b07eb5297c745ba198355-thumb
	 *  /files/ozone-7000000000-eaabf4cdc3f909a61be62e1fa4d231ed-fe5017db3a4b07eb5297c745ba198355-thumb
	 */
	'OZ_GET_FILE_URI_PATH_FORMAT' => '/files/ozone-{oz_file_id}[-{oz_file_ref}]-{oz_file_key}[-{oz_file_filter}][.{oz_file_extension}]',

	/**
	 * when user download a file should we
	 * specify the uploaded file name in the response headers ?
	 */
	'OZ_GET_FILE_SHOW_REAL_NAME' => false,

	/**
	 * maximum number of files that can be uploaded at once.
	 */
	'OZ_UPLOAD_FILE_MAX_COUNT' => 10,

	/**
	 * maximum size of a file: in bytes.
	 */
	'OZ_UPLOAD_FILE_MAX_SIZE' => 10 * 1000 * 1000, // 10 Mb

	/**
	 * maximum total size of all files that can be uploaded at once: in bytes.
	 */
	'OZ_UPLOAD_FILE_MAX_TOTAL_SIZE' => 100 * 1000 * 1000, // 100 Mb

	/**
	 * maximum size of a thumbnail: in pixels.
	 */
	'OZ_THUMBNAIL_MAX_SIZE' => 640,

	/**
	 * Should we use nginx x-sendfile or x-accel to serve files ?
	 *
	 * @see https://www.nginx.com/resources/wiki/start/topics/examples/xsendfile/
	 * @see https://www.nginx.com/resources/wiki/start/topics/examples/x-accel/
	 * @see https://www.mediasuite.co.nz/blog/proxying-s3-downloads-nginx/
	 */
	'OZ_SERVER_SENDFILE_ENABLED' => false,

	/**
	 * The path to use for nginx x-sendfile or x-accel.
	 */
	'OZ_SERVER_SENDFILE_REDIRECT_PATH' => '/send-file/',

	/**
	 * Should we allow direct access to public files or serve them through a dedicated route ?
	 */
	'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED' => false,
];
