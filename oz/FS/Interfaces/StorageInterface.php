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

namespace OZONE\Core\FS\Interfaces;

use OZONE\Core\App\Context;
use OZONE\Core\Auth\Providers\FileAccessAuthProvider;
use OZONE\Core\Auth\Services\AuthService;
use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\FileStream;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\UploadedFile;
use OZONE\Core\Http\Uri;

/**
 * Interface StorageInterface.
 */
interface StorageInterface
{
	/**
	 * Gets driver instance.
	 *
	 * @param string $name the driver assigned name
	 *
	 * @return $this
	 */
	public static function get(string $name): self;

	/**
	 * Should return stream.
	 *
	 * @param OZFile $file
	 *
	 * @return FileStream
	 */
	public function getStream(OZFile $file): FileStream;

	/**
	 * Handle uploaded file.
	 *
	 * @param UploadedFile $upload
	 *
	 * @return OZFile
	 */
	public function upload(UploadedFile $upload): OZFile;

	/**
	 * Add a file with content from a given stream.
	 *
	 * @param FileStream $source
	 * @param string     $mimetype
	 * @param string     $filename
	 *
	 * @return OZFile
	 */
	public function saveStream(FileStream $source, string $mimetype, string $filename): OZFile;

	/**
	 * Add a file at a given file path.
	 *
	 * @param string $path
	 * @param string $mimetype
	 * @param string $filename
	 *
	 * @return OZFile
	 */
	public function saveFromPath(string $path, string $mimetype, string $filename): OZFile;

	/**
	 * Add a file with a given raw content.
	 *
	 * @param string $content
	 * @param string $mimetype
	 * @param string $filename
	 *
	 * @return OZFile
	 */
	public function saveRaw(string $content, string $mimetype, string $filename): OZFile;

	/**
	 * Checks if a given file exists.
	 *
	 * @param OZFile $file
	 *
	 * @return bool
	 */
	public function exists(OZFile $file): bool;

	/**
	 * Should returns a revocable access uri for the given file.
	 *
	 * The file auth provider has all information needed to check if the request is authorized or not.
	 * The given auth provider credentials can be used to contact the auth server.
	 * The file provider is responsible for contacting the auth server to check if the credential is still valid.
	 *
	 * @param Context                $context
	 * @param FileAccessAuthProvider $provider
	 *
	 * @return Uri
	 *
	 * @see AuthService for more information on how to check if the credential
	 *      still valid or not.
	 */
	public function revocableAccessUri(Context $context, FileAccessAuthProvider $provider): Uri;

	/**
	 * Should returns a public access uri for the given file.
	 * Or fail if the file is not public.
	 *
	 * @param Context $context
	 * @param OZFile  $file
	 *
	 * @return Uri
	 */
	public function publicUri(Context $context, OZFile $file): Uri;

	/**
	 * Should serve the file to the HTTP client.
	 *
	 * @param OZFile   $file
	 * @param Response $response
	 *
	 * @return Response
	 */
	public function serve(OZFile $file, Response $response): Response;

	/**
	 * Writes data to the given file, this should overwrite the file content.
	 *
	 * @param OZFile            $file
	 * @param FileStream|string $content
	 *
	 * @return $this
	 */
	public function write(OZFile $file, FileStream|string $content): self;

	/**
	 * Appends data to the given file.
	 *
	 * @param OZFile            $file
	 * @param FileStream|string $data
	 *
	 * @return $this
	 */
	public function append(OZFile $file, FileStream|string $data): self;

	/**
	 * Prepends data to the given file.
	 *
	 * @param OZFile            $file
	 * @param FileStream|string $data
	 *
	 * @return $this
	 */
	public function prepend(OZFile $file, FileStream|string $data): self;

	/**
	 * Delete a file.
	 *
	 * @param OZFile $file
	 *
	 * @return bool
	 */
	public function delete(OZFile $file): bool;
}
