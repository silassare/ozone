<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\Core\REST\Interfaces;

use OZONE\Core\REST\ApiDoc;

/**
 * Interface ApiDocProviderInterface.
 */
interface ApiDocProviderInterface
{
	/**
	 * Document the API.
	 */
	public static function apiDoc(ApiDoc $doc): void;
}
