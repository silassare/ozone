<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	return [
		// which uri format you want for file access: must provide in order
		//		1:file_id
		//		2:file_key
		//		3:file_thumb
		// format: /ozone-file_id-file_key-thumb.ext or /file_id/file_key/thumb/
		// ex:
		// 		/ozone-7000000000-fe5017db3adbc7eb5297c745ba198355-1.o
		// 		/ozone-7000000000-fe5017db3adbc7eb5297c745ba198355-1
		'OZ_FILE_URI_EXTRA_REG' => '#^ozone-([0-9]+)-([a-z0-9]+)-(0|1|2|3)(?:\.[a-zA-Z0-9]{1,10})?$#',

		// the name used, when user download a file
		// you can use oz_file_id, oz_thumbnail and oz_file_extension
		'OZ_FILE_DOWNLOAD_NAME' => 'ozone-{oz_file_id}-{oz_thumbnail}.{oz_file_extension}',

		// who is able to access files through the "files service"
		// 	users				=> serve file only for verified users
		// 	session (default)	=> serve file only when there is an active session
		// 	any					=> serve file without restriction
		'OZ_FILE_ACCESS_LEVEL'  => 'session',

		'OZ_CAPTCHA_FILE_NAME' => 'oz-captcha-{oz_captcha_key}.png',
		'OZ_CAPTCHA_FILE_REG'  => '#^oz-captcha-([a-z0-9]{32})\.png$#',

		'OZ_QR_CODE_FILE_NAME' => 'oz-qrcode-{oz_qrcode_key}.png',
		'OZ_QR_CODE_FILE_REG'  => '#^oz-qrcode-([a-z0-9]{32})\.png$#'
	];