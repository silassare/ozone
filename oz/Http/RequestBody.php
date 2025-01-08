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
 * Class RequestBody.
 */
class RequestBody extends Body
{
	/**
	 * Creates a new RequestBody.
	 */
	public function __construct()
	{
		$stream = \fopen('php://temp', 'wb+');
		\stream_copy_to_stream(\fopen('php://input', 'rb'), $stream);
		\rewind($stream);

		parent::__construct($stream);
	}
}
