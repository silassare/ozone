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

namespace OZONE\OZ\FS\Interfaces;

use OZONE\OZ\Db\OZFile;
use OZONE\OZ\FS\FileStream;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Http\UploadedFile;

/**
 * Interface FilesProviderInterface.
 */
interface FilesDriverInterface
{
	/**
	 * Gets driver instance.
	 *
	 * @return $this
	 */
	public static function getInstance(): self;

	/**
	 * Should return stream.
	 *
	 * @param \OZONE\OZ\Db\OZFile $file
	 *
	 * @return \OZONE\OZ\FS\FileStream
	 */
	public function getStream(OZFile $file): FileStream;

	/**
	 * Handle uploaded file.
	 *
	 * @param \OZONE\OZ\Http\UploadedFile $upload
	 *
	 * @return \OZONE\OZ\Db\OZFile
	 */
	public function upload(UploadedFile $upload): OZFile;

	/**
	 * Add a file with content from a given stream.
	 *
	 * @param \OZONE\OZ\FS\FileStream $source
	 * @param string                  $mimetype
	 * @param string                  $filename
	 *
	 * @return \OZONE\OZ\Db\OZFile
	 */
	public function saveStream(FileStream $source, string $mimetype, string $filename): OZFile;

	/**
	 * Add a file at a given file path.
	 *
	 * @param string $path
	 * @param string $mimetype
	 * @param string $filename
	 *
	 * @return \OZONE\OZ\Db\OZFile
	 */
	public function saveFromPath(string $path, string $mimetype, string $filename): OZFile;

	/**
	 * Add a file with a given raw content.
	 *
	 * @param string $content
	 * @param string $mimetype
	 * @param string $filename
	 *
	 * @return \OZONE\OZ\Db\OZFile
	 */
	public function saveRaw(string $content, string $mimetype, string $filename): OZFile;

	/**
	 * Checks if a given file exists.
	 *
	 * @param \OZONE\OZ\Db\OZFile $file
	 *
	 * @return bool
	 */
	public function exists(OZFile $file): bool;

	/**
	 * Should serve the file to the HTTP client.
	 *
	 * @param \OZONE\OZ\Db\OZFile     $file
	 * @param \OZONE\OZ\Http\Response $response
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function serve(OZFile $file, Response $response): Response;

	/**
	 * Writes data to the given file, this should overwrite the file content.
	 *
	 * @param \OZONE\OZ\Db\OZFile $file
	 * @param string              $content
	 *
	 * @return $this
	 */
	public function write(OZFile $file, string $content): self;

	/**
	 * Appends data to the given file.
	 *
	 * @param \OZONE\OZ\Db\OZFile $file
	 * @param string              $data
	 *
	 * @return $this
	 */
	public function append(OZFile $file, string $data): self;

	/**
	 * Prepends data to the given file.
	 *
	 * @param \OZONE\OZ\Db\OZFile $file
	 * @param string              $data
	 *
	 * @return $this
	 */
	public function prepend(OZFile $file, string $data): self;

	/**
	 * Delete a file.
	 *
	 * @param \OZONE\OZ\Db\OZFile $file
	 *
	 * @return bool
	 */
	public function delete(OZFile $file): bool;
}
