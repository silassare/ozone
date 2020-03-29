<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
	
	\defined('OZ_SELF_SECURITY_CHECK') || die;

	return [
		'project'   => '\OZONE\OZ\Cli\Cmd\Project',
		'webclient' => '\OZONE\OZ\Cli\Cmd\WebClient',
		'db'        => '\OZONE\OZ\Cli\Cmd\Db',
		'service'   => '\OZONE\OZ\Cli\Cmd\Service',
	];
