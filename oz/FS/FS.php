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

namespace OZONE\Core\FS;

use InvalidArgumentException;
use OZONE\Core\App\Settings;
use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Db\OZFilesQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\Interfaces\StorageInterface;
use OZONE\Core\Http\UploadedFile;
use Throwable;

/**
 * Class FS.
 */
class FS
{
	/**
	 * the default mime type for any file.
	 */
	public const DEFAULT_FILE_MIME_TYPE = 'application/octet-stream';

	/**
	 * the default extension for any file.
	 */
	public const DEFAULT_FILE_EXTENSION = 'ext';

	public const DEFAULT_STORAGE = 'default';
	public const PUBLIC_STORAGE  = 'public';
	public const PRIVATE_STORAGE = 'private';

	/**
	 * Gets extension from a file with a given file name and expected mime type.
	 *
	 * when the file extension does not match the given mime type
	 * we use the mime type to guess the extension.
	 *
	 * @param string $file_name          the file name
	 * @param string $expected_mime_type the expected file mime type
	 *
	 * @return string
	 */
	public static function getRealExtension(string $file_name, string $expected_mime_type): string
	{
		$name_ext = \strtolower(\substr($file_name, \strrpos($file_name, '.') + 1));

		if ($name_ext && self::extensionToMimeType($name_ext) === $expected_mime_type) {
			return $name_ext;
		}

		return self::mimeTypeToExtension($expected_mime_type);
	}

	/**
	 * Convert php upload error code to message.
	 *
	 * @param int $error
	 *
	 * @return array{message: string, reason: string}
	 */
	public static function uploadErrorInfo(int $error): array
	{
		$message = match ($error) {
			\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'OZ_FILE_UPLOAD_TOO_BIG',
			\UPLOAD_ERR_NO_FILE => 'OZ_FILE_UPLOAD_IS_EMPTY',
			default             => 'OZ_FILE_UPLOAD_FAILS',
		};
		$reason  = match ($error) {
			\UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
			\UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
			\UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
			\UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded',
			\UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
			\UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
			\UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension',
			default                => 'Unknown upload error',
		};

		return [
			'message' => $message,
			'reason'  => $reason,
		];
	}

	/**
	 * Guess mime type that match to a given file extension.
	 *
	 * when none found, default to \OZONE\Core\FS\FilesUtils::DEFAULT_FILE_MIME_TYPE
	 *
	 * @param string $ext the file extension
	 *
	 * @return string the mime type
	 */
	public static function extensionToMimeType(string $ext): string
	{
		$map = Settings::load('oz.map.ext.to.mime');
		$ext = \strtolower($ext);

		return $map[$ext] ?? self::DEFAULT_FILE_MIME_TYPE;
	}

	/**
	 * Guess file extension that match to a given file mime type.
	 *
	 * when none found, default to FilesUtils::DEFAULT_FILE_EXTENSION
	 *
	 * @param string $type the file mime type
	 *
	 * @return string the file extension that match to this file mime type
	 */
	public static function mimeTypeToExtension(string $type): string
	{
		$map  = Settings::load('oz.map.mime.to.ext');
		$type = \strtolower($type);

		return $map[$type] ?? self::DEFAULT_FILE_EXTENSION;
	}

	/**
	 * Gets file from database with a given file id.
	 *
	 * @param string $id the file id
	 *
	 * @return null|\OZONE\Core\Db\OZFile
	 */
	public static function getFileWithId(string $id): ?OZFile
	{
		try {
			$qb = new OZFilesQuery();

			return $qb->whereIdIs($id)
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException(\sprintf('Unable to get file with id: %s', $id), null, $t);
		}
	}

	/**
	 * Parse an alias file.
	 *
	 * @param \OZONE\Core\Http\UploadedFile $upload
	 *
	 * @return null|\OZONE\Core\Db\OZFile
	 */
	public static function parseFileAlias(UploadedFile $upload): ?OZFile
	{
		// checks file name and extension
		if (!\preg_match('~\.ofa$~i', $upload->getClientFilename())) {
			return null;
		}

		// the correct mime type
		if ('text/x-ozone-file-alias' !== $upload->getClientMediaType()) {
			return null;
		}

		// checks alias file size 4ko
		if ($upload->getSize() > 4 * 1024) {
			return null;
		}

		$content = $upload->getStream()
			->getContents();

		try {
			$data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to parse file alias.', ['content' => $content], $t);
		}

		if (!\is_array($data) || !\array_key_exists('file_id', $data) || !\array_key_exists('file_key', $data)) {
			throw new RuntimeException('File alias content is invalid.', ['content' => $content]);
		}

		$f = self::getFileWithId($data['file_id']);

		if (!$f || $f->isValid()) {
			throw new RuntimeException('Unable to identify aliased file.', ['content' => $content]);
		}

		if (!\hash_equals($f->getKey(), (string) $data['file_key'])) {
			throw new RuntimeException('Invalid file alias key.', ['content' => $content]);
		}

		return $f;
	}

	/**
	 * Format a given file size.
	 *
	 * @param float  $size          the file size in bytes
	 * @param int    $precision     The number of decimal digit
	 * @param int    $kb_size       The kb_size (Ex: use 1000 for data, or 1024 for file size)
	 * @param string $decimal_point The decimal point to use (, for french users)
	 * @param string $thousands_sep The thousands separation char
	 *
	 * @return string the formatted file size
	 */
	public static function formatFileSize(float $size, int $precision = 2, int $kb_size = 1024, string $decimal_point = '.', string $thousands_sep = ' '): string
	{
		$unites  = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
		$max_pow = 8;
		$result  = $size;
		$pow     = 0;

		if ($size >= 1) {
			while ($pow <= $max_pow) {
				$div = $kb_size ** $pow;

				if ($size < $div * $kb_size || $pow === $max_pow) {
					$result = $div > 1 ? $size / $div : $size;

					break;
				}

				++$pow;
			}
		} else {
			$result = 0;
		}

		return \number_format($result, $precision, $decimal_point, $thousands_sep) . ' ' . $unites[$pow];
	}

	/**
	 * Converts base64 data to file stream.
	 *
	 * @param string $base64
	 *
	 * @return \OZONE\Core\FS\FileStream
	 */
	public static function base64ToFile(string $base64): FileStream
	{
		// data urls: data:[<mediatype>][;base64],<data>
		if (($pos = \strpos($base64, ',')) !== false) {
			$base64 = \substr($base64, $pos + 1);
		}

		$base64     = \str_replace(' ', '+', $base64);
		$i          = 0;
		$chunk_size = 1024;
		$stream     = FileStream::create();

		// seems to the good way to handle big base64 data
		while ($chunk = \substr($base64, $i, $chunk_size)) {
			$decoded = \base64_decode($chunk, true);

			if (false === $decoded) {
				throw new InvalidArgumentException('Invalid base64 encoded data.');
			}

			$stream->write($decoded);

			$i += $chunk_size;
		}

		return $stream;
	}

	/**
	 * Gets instance of the public files storage driver.
	 *
	 * @return \OZONE\Core\FS\Interfaces\StorageInterface
	 */
	public static function getPublicStorage(): StorageInterface
	{
		return self::getStorage(self::PUBLIC_STORAGE);
	}

	/**
	 * Gets instance of the private files storage driver.
	 *
	 * @return \OZONE\Core\FS\Interfaces\StorageInterface
	 */
	public static function getPrivateStorage(): StorageInterface
	{
		return self::getStorage(self::PRIVATE_STORAGE);
	}

	/**
	 * Gets instance of the files storage driver with the given name.
	 *
	 * @param string $name
	 *
	 * @return \OZONE\Core\FS\Interfaces\StorageInterface
	 */
	public static function getStorage(string $name = self::DEFAULT_STORAGE): StorageInterface
	{
		$driver = Settings::get('oz.files.storages', $name);

		if (!$driver) {
			throw new RuntimeException(\sprintf('Undefined file storage driver: %s', $name));
		}

		$factory = function () use ($driver) {
			if (!\is_subclass_of($driver, StorageInterface::class)) {
				throw new RuntimeException(\sprintf(
					'Files storage driver "%s" should implements "%s".',
					$driver,
					StorageInterface::class
				));
			}

			/* @var StorageInterface $driver */
			return $driver::get();
		};

		return CacheManager::runtime(__METHOD__)
			->factory($name, $factory)
			->get();
	}

	/**
	 * Returns a new temp file path.
	 *
	 * @return string
	 */
	public static function newTempFile(): string
	{
		return \tempnam(\sys_get_temp_dir(), Settings::get('oz.config', 'OZ_PROJECT_PREFIX', 'oz') . '_');
	}
}
