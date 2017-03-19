<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	\OZONE\OZ\Core\OZoneSettings::set( 'oz.files', array(
		// which uri format you want for file access: must provide in order
		//		1:fid
		//		2:fkey
		//		3:fthumb
		// format: /ozone-fid-fkey-thumb.ext or /fid/fkey/thumb/
		// ex:
		// 		/ozone-7000000000-fe5017db3adbc7eb5297c745ba198355-1.o
		// 		/ozone-7000000000-fe5017db3adbc7eb5297c745ba198355-1

		'OZ_FILE_URI_EXTRA_REG' => '#^ozone-([0-9]+)-([a-z0-9]+)-(0|1|2|3)(?:\.[a-zA-Z0-9]{1,10})?$#',
		//the name used, when user download a file
		//you can use oz_fid, oz_thumb and oz_ext
		'OZ_FILE_DOWNLOAD_NAME' => 'ozone-{oz_fid}-{oz_thumb}.{oz_ext}'
	) );