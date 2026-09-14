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

namespace OZONE\Core\FS\S3\Interfaces;

use OZONE\Core\FS\S3\S3Client;
use OZONE\Core\FS\S3\S3Request;
use OZONE\Core\FS\S3\S3Response;

/**
 * Interface S3TransportInterface.
 *
 * How an {@see S3Client} puts a signed request on the wire.
 */
interface S3TransportInterface
{
	/**
	 * A short name, for diagnostics.
	 */
	public static function getName(): string;

	/**
	 * Whether this transport can run here.
	 */
	public static function isSupported(): bool;

	/**
	 * Whether a stream request body is sent as it is read, rather than flattened into a string.
	 *
	 * A client uses this to decide whether a single upload is safe or has to be split into parts.
	 */
	public static function streamsRequestBody(): bool;

	/**
	 * Sends a request.
	 *
	 * Transport errors throw; an error *response* is returned, for the caller to interpret.
	 *
	 * @param S3Request $request
	 *
	 * @return S3Response
	 */
	public function send(S3Request $request): S3Response;
}
