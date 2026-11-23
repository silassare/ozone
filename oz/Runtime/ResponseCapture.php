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

namespace OZONE\Core\Runtime;

use Override;
use OZONE\Core\Http\Response;
use OZONE\Core\Runtime\Interfaces\ResponseSinkInterface;

/**
 * A response sink that keeps what it is given: the answer of a request made in process, which goes
 * back to the code that made it rather than to a client.
 *
 * @internal
 */
final class ResponseCapture implements ResponseSinkInterface
{
	private ?Response $response = null;

	#[Override]
	public function send(Response $response): void
	{
		$this->response = $response;
	}

	/**
	 * The response sent, or null when none was.
	 */
	public function response(): ?Response
	{
		return $this->response;
	}
}
