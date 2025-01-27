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

/**
 * Class HTTPEnvironment.
 */
class HTTPEnvironment extends Collection
{
	/**
	 * Creates mock HTTP environment.
	 *
	 * @param array $env Array of custom HTTP environment keys and values
	 *
	 * @return self
	 */
	public static function mock(array $env = []): self
	{
		$env = \array_merge([
			'SERVER_PROTOCOL'      => 'HTTP/1.1',
			'REQUEST_METHOD'       => 'GET',
			'SCRIPT_NAME'          => '/index.php',
			'REQUEST_URI'          => '/',
			'QUERY_STRING'         => '',
			'SERVER_NAME'          => 'localhost',
			'SERVER_PORT'          => 80,
			'HTTP_HOST'            => 'localhost',
			'HTTP_ACCEPT'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
			'HTTP_ACCEPT_CHARSET'  => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
			'HTTP_USER_AGENT'      => OZ_OZONE_VERSION_NAME,
			'REMOTE_ADDR'          => '127.0.0.1',
			'REQUEST_TIME'         => \time(),
			'REQUEST_TIME_FLOAT'   => \microtime(true),
		], $env);

		return new static($env);
	}
}
