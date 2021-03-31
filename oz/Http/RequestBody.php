<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Http;

/**
 * Provides a PSR-7 implementation of a reusable raw request body
 */
class RequestBody extends Body
{
	/**
	 * Creates a new RequestBody.
	 */
	public function __construct()
	{
		$stream = \fopen('php://temp', 'w+');
		\stream_copy_to_stream(\fopen('php://input', 'r'), $stream);
		\rewind($stream);

		parent::__construct($stream);
	}
}
