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

namespace OZONE\Core\FS\Filters\Interfaces;

use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\FileStream;
use OZONE\Core\Http\Response;

/**
 * Interface FileFilterHandlerInterface.
 *
 * Implement this interface to add file filter support for a new file type
 * (e.g. PDF thumbnail generation, video transcoding, etc.), then register
 * the handler in the `oz.files.filters` settings group or by calling
 * FileFilters::register() during your plugin's boot() method.
 */
interface FileFilterHandlerInterface
{
	/**
	 * Returns true when this handler can process the given file
	 * using the provided filter tokens.
	 *
	 * @param OZFile   $file         the file entity
	 * @param string[] $filterTokens the individual filter tokens (already split on FS::FILTERS_SEPARATOR)
	 *
	 * @return bool
	 */
	public function canHandle(OZFile $file, array $filterTokens): bool;

	/**
	 * Processes the file stream and returns a populated response.
	 *
	 * @param OZFile     $file         the file entity
	 * @param FileStream $stream       a fresh, unread stream of the file content
	 * @param Response   $response     the response object to populate and return
	 * @param string[]   $filterTokens the individual filter tokens to apply
	 *
	 * @return Response
	 */
	public function handle(OZFile $file, FileStream $stream, Response $response, array $filterTokens): Response;
}
