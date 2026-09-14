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

namespace OZONE\Core\Http;

use OZONE\Core\App\Settings;

/**
 * Class MethodOverride.
 *
 * Lets clients that can only send GET and POST ask for another method: a POST carrying the
 * `OZ_REAL_METHOD_HEADER_NAME` header (`X-OZONE-Real-Method: DELETE`) is handled as that
 * method when `OZ_REAL_METHOD_HEADER_ALLOWED` is on.
 */
final class MethodOverride
{
	/**
	 * The name of the override header.
	 */
	public static function headerName(): string
	{
		return \strtolower(\str_replace('_', '-', (string) Settings::get('oz.request', 'OZ_REAL_METHOD_HEADER_NAME')));
	}

	/**
	 * The method a request is handled as.
	 *
	 * @param string $method    the method of the request line
	 * @param string $requested the override header value, possibly empty
	 */
	public static function resolve(string $method, string $requested): string
	{
		if (
			'POST' !== $method
			|| '' === \trim($requested)
			|| !Settings::get('oz.request', 'OZ_REAL_METHOD_HEADER_ALLOWED')
		) {
			return $method;
		}

		return Request::filterMethod(\trim($requested));
	}
}
