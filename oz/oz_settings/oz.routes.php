<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	defined('OZ_SELF_SECURITY_CHECK') or die;

	return [
		// routes that start with `/oz:` are for internal use only
		// any external request of such route will be rejected

		'oz:error'        => [
			'provider' => 'OZONE\OZ\Exceptions\Views\ErrorView'
		],
		'oz:redirect'     => [
			'provider' => 'OZONE\OZ\Web\Views\RedirectView'
		],
		'oz-static'       => [
			'provider' => 'OZONE\OZ\FS\Views\GetFilesView'
		],
		'oz-session-share' => [
			'provider' => 'OZONE\OZ\User\Views\SessionShareView'
		]
	];