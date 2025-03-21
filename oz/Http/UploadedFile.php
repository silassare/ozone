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

use InvalidArgumentException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FS;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class UploadedFile.
 */
class UploadedFile implements UploadedFileInterface
{
	/**
	 * The client-provided full path to the file.
	 */
	protected string $file;

	/**
	 * The client-provided file name.
	 */
	protected string $unsafe_name;

	/**
	 * The client-provided media type of the file.
	 */
	protected ?string $unsafe_type;

	/**
	 * More secure version of the file's media type.
	 */
	protected string $clean_type;

	/**
	 * More secure version of the file's name.
	 */
	protected string $clean_name;

	/**
	 * The size of the file in bytes.
	 */
	protected ?int $size;

	/**
	 * A valid PHP UPLOAD_ERR_xxx code for the file upload.
	 */
	protected int $error = \UPLOAD_ERR_OK;

	/**
	 * Indicates if the upload is from a SAPI environment.
	 */
	protected bool $sapi = false;

	/**
	 * An optional StreamInterface wrapping the file resource.
	 */
	protected ?StreamInterface $stream = null;

	/**
	 * Indicates if the uploaded file has already been moved.
	 */
	protected bool $moved = false;

	/**
	 * Construct a new UploadedFile instance.
	 *
	 * @param string      $file  the full path to the uploaded file
	 * @param null|string $name  the file name
	 * @param null|string $type  the file media type
	 * @param null|int    $size  the file size in bytes
	 * @param int         $error the UPLOAD_ERR_XXX code representing the status of the upload
	 * @param bool        $sapi  indicates if the upload is in a SAPI environment, should be false if file path is not coming from $_FILES
	 */
	public function __construct(
		string $file,
		?string $name = null,
		?string $type = null,
		?int $size = null,
		int $error = \UPLOAD_ERR_OK,
		bool $sapi = true
	) {
		$clean_type = FS::getMimeType($file);

		if (!$clean_type) {
			$clean_type = 'application/octet-stream';
		}

		// live recorded blob files as audio/video/image doesn't have a valid name
		if (empty($name) || 'blob' === $name) {
			$ext        = FS::mimeTypeToExtension($type ?? $clean_type);
			$clean_name = $name = FS::sanitizeFilename('', $ext);
		} else {
			$ext        = FS::getRealExtension($name, $clean_type);
			$clean_name = FS::sanitizeFilename($name, $ext);
		}

		$this->file        = $file;
		$this->unsafe_name = $name;
		$this->unsafe_type = $type;
		$this->clean_type  = $clean_type;
		$this->clean_name  = $clean_name;
		$this->size        = $size;
		$this->error       = $error;
		$this->sapi        = $sapi;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getStream(): Stream
	{
		if ($this->moved) {
			throw new RuntimeException(\sprintf('Uploaded file "%s" has already been moved', $this->unsafe_name));
		}

		if (null === $this->stream) {
			$this->stream = new Stream(\fopen($this->file, 'rb'));
		}

		return $this->stream;
	}

	/**
	 * {@inheritDoc}
	 */
	public function moveTo(string $targetPath): void
	{
		if ($this->moved) {
			throw new RuntimeException('Uploaded file already moved');
		}

		if (\UPLOAD_ERR_OK !== $this->error) {
			$info             = FS::uploadErrorInfo($this->error);
			$debug['_name']   = $this->unsafe_name;
			$debug['_reason'] = $info['reason'];

			throw new RuntimeException($info['message'], $debug);
		}

		$targetIsStream = \strpos($targetPath, '://') > 0;

		if (!$targetIsStream && !\is_writable(\dirname($targetPath))) {
			throw new InvalidArgumentException('Upload target path is not writable');
		}

		if ($targetIsStream) {
			if (!\copy($this->file, $targetPath)) {
				throw new RuntimeException(\sprintf('Error moving uploaded file "%s" to "%s"', $this->unsafe_name, $targetPath));
			}

			if (!\unlink($this->file)) {
				throw new RuntimeException(\sprintf('Error removing uploaded file "%s"', $this->unsafe_name));
			}
		} elseif ($this->sapi) {
			if (!\is_uploaded_file($this->file)) {
				throw new RuntimeException(\sprintf('"%s" is not a valid uploaded file', $this->file));
			}

			if (!\move_uploaded_file($this->file, $targetPath)) {
				throw new RuntimeException(\sprintf('Error moving uploaded file "%s" to "%s"', $this->unsafe_name, $targetPath));
			}
		} elseif (!\rename($this->file, $targetPath)) {
			throw new RuntimeException(\sprintf('Error moving uploaded file "%s" to "%s"', $this->unsafe_name, $targetPath));
		}

		$this->moved = true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getError(): int
	{
		return $this->error;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getClientFilename(): string
	{
		return $this->unsafe_name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getClientMediaType(): ?string
	{
		return $this->unsafe_type;
	}

	/**
	 * Returns a more secure version of the file's media type.
	 *
	 * @return string
	 */
	public function getCleanMediaType(): string
	{
		return $this->clean_type;
	}

	/**
	 * Returns a more secure version of the file's name.
	 *
	 * @return string
	 */
	public function getCleanFileName(): string
	{
		return $this->clean_name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSize(): int
	{
		return $this->size ?? 0;
	}

	/**
	 * Creates a normalized tree of UploadedFile instances from the Environment.
	 *
	 * @param HTTPEnvironment $env The environment
	 *
	 * @return null|array a normalized tree of UploadedFile instances or null if none are provided
	 */
	public static function createFromEnvironment(HTTPEnvironment $env): ?array
	{
		if ($env->has('oz_files') && \is_array($env['oz_files'])) {
			return $env['oz_files'];
		}

		if (!empty($_FILES)) {
			return static::parseUploadedFiles($_FILES);
		}

		return [];
	}

	/**
	 * Parse a non-normalized, i.e. $_FILES superglobal, tree of uploaded file data.
	 *
	 * @param array $uploadedFiles the non-normalized tree of uploaded file data
	 *
	 * @return array a normalized tree of UploadedFile instances
	 */
	private static function parseUploadedFiles(array $uploadedFiles): array
	{
		$parsed = [];

		foreach ($uploadedFiles as $field => $uploadedFile) {
			if (!isset($uploadedFile['error'])) {
				if (\is_array($uploadedFile)) {
					$parsed[$field] = static::parseUploadedFiles($uploadedFile);
				}

				continue;
			}

			$parsed[$field] = [];

			if (!\is_array($uploadedFile['error'])) {
				$parsed[$field] = new static(
					$uploadedFile['tmp_name'],
					$uploadedFile['name'] ?? null,
					$uploadedFile['type'] ?? null,
					$uploadedFile['size'] ?? null,
					$uploadedFile['error'],
					true
				);
			} else {
				$subArray = [];

				foreach ($uploadedFile['error'] as $fileIdx => $error) {
					// normalise subarray and re-parse to move the input's keyname up a level
					$subArray[$fileIdx]['name']     = $uploadedFile['name'][$fileIdx];
					$subArray[$fileIdx]['type']     = $uploadedFile['type'][$fileIdx];
					$subArray[$fileIdx]['tmp_name'] = $uploadedFile['tmp_name'][$fileIdx];
					$subArray[$fileIdx]['error']    = $uploadedFile['error'][$fileIdx];
					$subArray[$fileIdx]['size']     = $uploadedFile['size'][$fileIdx];

					$parsed[$field] = static::parseUploadedFiles($subArray);
				}
			}
		}

		return $parsed;
	}
}
