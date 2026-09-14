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

namespace OZONE\Core\FS\Traits;

use OZONE\Core\Db\OZFile;
use OZONE\Core\Http\Response;

/**
 * Trait FileResponseTrait.
 *
 * The headers storages send a file with.
 */
trait FileResponseTrait
{
	/**
	 * Adds the file's type, range support, validators and caching headers.
	 *
	 * Message's `with*()` methods return `static`, which psalm widens to Message here.
	 *
	 * @psalm-suppress MoreSpecificReturnType, LessSpecificReturnStatement
	 */
	protected static function withFileHeaders(OZFile $file, Response $response): Response
	{
		$ts    = $file->getUpdatedAT();
		$mtime = \is_numeric($ts) ? (int) $ts : (int) \strtotime($ts);
		// Derive ETag from public, stable, version-sensitive fields.
		// The file key is an access-control secret and must NOT be exposed in headers.
		$etag = \hash('xxh64', $file->getID() . ':' . $file->getSize() . ':' . $ts);

		return $response
			->withHeader('Content-Type', $file->getMime())
			->withHeader('Accept-Ranges', 'bytes')
			->withHeader('ETag', '"' . $etag . '"')
			->withHeader('Last-Modified', \gmdate('D, d M Y H:i:s \G\M\T', $mtime))
			->withHeader('Cache-Control', 'private, max-age=31536000, immutable');
	}
}
