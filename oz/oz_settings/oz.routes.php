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
		// routes that start with `/oz:` are for internal use only
		// any external request of such route will be rejected

		'oz:error' => [
			'path'    => '/oz:error',
			'handler' => 'OZONE\OZ\WebRoute\Views\ErrorView'
		]
	];