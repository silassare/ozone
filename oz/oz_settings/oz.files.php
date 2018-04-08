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
		// the uri format you want for file access: must provide in order
		//		oz_file_id
		//		oz_file_key
		//		oz_file_quality
		// format: /ozone-oz_file_id-oz_file_key-oz_file_quality.ext or /oz_file_id/oz_file_key/oz_file_quality/
		// ex:
		// 		/ozone-7000000000-fe5017db3a4b07eb5297c745ba198355-1.o
		// 		/ozone-7000000000-fe5017db3a4b07eb5297c745ba198355-1
		'OZ_GET_FILE_URI_EXTRA_FORMAT' => 'ozone-{oz_file_id}-{oz_file_key}-{oz_file_quality}',
		// the name used, when user download a file
		// you can use oz_file_id, oz_file_quality and oz_file_extension
		'OZ_GET_FILE_NAME'             => 'ozone-{oz_file_id}-{oz_file_quality}.{oz_file_extension}',
		// who is able to access files through the "files service"
		// 	users				=> serve file only for verified users
		// 	session (default)	=> serve file only when there is an active session
		// 	any					=> serve file without restriction
		'OZ_GET_FILE_ACCESS_LEVEL'     => 'session',
		'OZ_CAPTCHA_FILE_NAME'         => 'oz-captcha-{oz_captcha_key}.png',
		'OZ_CAPTCHA_URI_EXTRA_FORMAT'  => 'oz-captcha-{oz_captcha_key}.png',
		'OZ_QR_CODE_FILE_NAME'         => 'oz-qrcode-{oz_qrcode_key}.png',
		'OZ_QR_CODE_URI_EXTRA_FORMAT'  => 'oz-qrcode-{oz_qrcode_key}.png'
	];