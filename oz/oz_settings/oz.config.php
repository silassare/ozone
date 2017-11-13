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
		// For server that does not support HEAD, PATCH, PUT, DELETE...
		'OZ_APP_ALLOW_REAL_METHOD_HEADER' => true,
		'OZ_APP_REAL_METHOD_HEADER_NAME' => 'ozone-real-method'
	];