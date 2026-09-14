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

namespace OZONE\Core\FS\Drivers;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Providers\FileAccessAuthorizationProvider;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Interfaces\StorageInterface;
use OZONE\Core\FS\Traits\FileResponseTrait;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\UploadedFile;
use OZONE\Core\Http\Uri;
use Throwable;

/**
 * Class AbstractLocalStorage.
 */
abstract class AbstractLocalStorage implements StorageInterface
{
	use FileResponseTrait;

	/**
	 * AbstractLocalStorage constructor.
	 *
	 * @param string $name the driver assigned name
	 */
	public function __construct(protected readonly string $name) {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getStream(OZFile $file): FileStream
	{
		$abs_path = $this->require($file->getRef());

		return FileStream::fromPath($abs_path);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function upload(UploadedFile $upload): OZFile
	{
		if (($error = $upload->getError()) !== \UPLOAD_ERR_OK) {
			$info = FS::uploadErrorInfo($error);

			// Initialised, not written into out of nowhere: PHP 8 warns on an undefined variable,
			// and it did so exactly when an upload had already failed.
			throw new RuntimeException($info['message'], ['_reason' => $info['reason']]);
		}

		if (null !== ($result = FS::parseFileAlias($upload))) {
			return $result->cloneFile();
		}

		$filename = \trim($upload->getClientFilename());
		$mimetype = $upload->getCleanMediaType();

		$clean_name = $upload->getCleanFileName();
		$ext        = FS::getRealExtension($clean_name, $mimetype);

		if (empty($filename)) {
			$filename = $clean_name;
		}

		$destination = $this->createDestinationPath($clean_name, $ref);

		$upload->moveTo($destination);

		$filesize = self::sizeOf($destination);

		/** @var string $ref */
		$f = new OZFile();
		$f->setName($clean_name)
			->setRealName($filename)
			->setRef($ref)
			->setStorage($this->name)
			->setMime($mimetype)
			->setExtension($ext)
			->setSize($filesize);

		return $f;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function saveStream(FileStream $source, string $mimetype, string $filename): OZFile
	{
		$filename   = \trim($filename);
		$ext        = FS::getRealExtension($filename, $mimetype);
		$clean_name = FS::sanitizeFilename($filename, $ext, 'save');

		if (empty($filename)) {
			$filename = $clean_name;
		}

		$destination = $this->createDestinationPath($clean_name, $ref);

		// e.g. FileStream::fromString() leaves the stream at its end.
		if ($source->isSeekable()) {
			$source->rewind();
		}

		$fw = @\fopen($destination, 'wb');

		if (false === $fw) {
			throw new RuntimeException(\sprintf('Unable to write the uploaded file at "%s".', $destination));
		}

		while (!$source->eof()) {
			\fwrite($fw, $source->read(4096));
		}

		\fclose($fw);

		$filesize = self::sizeOf($destination);

		/** @var string $ref */
		$f = new OZFile();
		$f->setName($clean_name)
			->setRealName($filename)
			->setRef($ref)
			->setStorage($this->name)
			->setMime($mimetype)
			->setExtension($ext)
			->setSize($filesize);

		return $f;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function saveFromPath(string $path, string $mimetype, string $filename): OZFile
	{
		return $this->saveStream(FileStream::fromPath($path), $mimetype, $filename);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function saveRaw(string $content, string $mimetype, string $filename): OZFile
	{
		return $this->saveStream(FileStream::fromString($content), $mimetype, $filename);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function exists(OZFile $file): bool
	{
		return null !== $this->localize($file->getRef());
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function revocableAccessUri(Context $context, FileAccessAuthorizationProvider $provider): Uri
	{
		$file = $provider->getFile();
		$this->require($file->getRef());

		$credentials = $provider->getCredentials();

		return FS::buildFileUri($context, $file, $credentials);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @psalm-suppress MoreSpecificReturnType, LessSpecificReturnStatement
	 */
	#[Override]
	public function serve(OZFile $file, Response $response): Response
	{
		$abs_path = $this->require($file->getRef());

		if (Settings::get('oz.files', 'OZ_SERVER_SENDFILE_ENABLED')) {
			$base_path = Settings::get('oz.files', 'OZ_SERVER_SENDFILE_REDIRECT_PATH');
			$path      = app()->getProjectDir()->relativePath($abs_path);
			$new_path  = $base_path . $path;

			return $response
				// for nginx
				->withHeader('X-Accel-Redirect', $new_path)
				// for apache
				->withHeader('X-Sendfile', $abs_path);
		}

		return self::withFileHeaders($file, $response)->withBody(Body::fromPath($abs_path));
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	#[Override]
	public function write(OZFile $file, FileStream|string $content): static
	{
		$abs_path = $this->require($file->getRef());

		if (\file_exists($abs_path)) {
			// Truncate before rewriting, so a shorter new content cannot leave a tail of the old one.
			$f = @\fopen($abs_path, 'wb');

			if (false === $f) {
				throw new RuntimeException(\sprintf('Unable to open "%s" for writing.', $abs_path));
			}

			\ftruncate($f, 0);
			\fclose($f);
		}

		FS::fromRoot()->wf($abs_path, $content);

		\clearstatcache(true, $abs_path);

		$file->setSize(self::sizeOf($abs_path))->setUpdatedAt(\time())->save();

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	#[Override]
	public function append(OZFile $file, FileStream|string $data): static
	{
		$abs_path = $this->require($file->getRef());

		FS::fromRoot()->append($abs_path, $data);

		\clearstatcache(true, $abs_path);

		$file->setSize(self::sizeOf($abs_path))->setUpdatedAt(\time())->save();

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	#[Override]
	public function prepend(OZFile $file, FileStream|string $data): static
	{
		$abs_path = $this->require($file->getRef());

		FS::fromRoot()->prepend($abs_path, $data);

		\clearstatcache(true, $abs_path);

		$file->setSize(self::sizeOf($abs_path))->setUpdatedAt(\time())->save();

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function delete(OZFile $file): bool
	{
		if (!$file->canDelete()) {
			return false;
		}

		$abs_path = $this->localize($file->getRef());

		if (!$abs_path) {
			return false;
		}

		FS::fromRoot()->rm($abs_path);

		return true;
	}

	/**
	 * Gets the files uploads directory.
	 *
	 * @return FilesManager
	 */
	abstract protected function uploadsDir(): FilesManager;

	/**
	 * Creates a file destination with a ref.
	 *
	 * @param string      $clean_name
	 * @param null|string &$ref
	 *
	 * @return string
	 */
	protected function createDestinationPath(string $clean_name, ?string &$ref = null): string
	{
		$year  = \date('Y');
		$month = \date('m');
		$dir   = $year . DS . $month;
		$ref   = $dir . DS . $clean_name;

		return $this->uploadsDir()->cd($dir, true)->resolve($clean_name);
	}

	/**
	 * Returns file absolute path with the given ref.
	 *
	 * @param string $ref
	 *
	 * @return null|string
	 */
	protected function localize(string $ref): ?string
	{
		$fs          = FS::fromRoot();
		$destination = $this->uploadsDir()->resolve($ref);
		if (
			$fs->filter()
				->isFile()
				->check($destination)
		) {
			return $destination;
		}

		return null;
	}

	/**
	 * Returns file absolute path with the given ref
	 * or throws an exception if the file is not found.
	 *
	 * @param string $ref
	 *
	 * @return string
	 */
	protected function require(string $ref): string
	{
		$abs_path = $this->localize($ref);

		if (!$abs_path) {
			throw new RuntimeException('File not found.', [
				'_ref' => $ref,
			]);
		}

		return $abs_path;
	}

	/**
	 * The size of a file that was just written.
	 *
	 * `filesize()` returns false when it cannot stat the path -- a full disk, a permission change
	 * between the write and the stat. Passing that false into `OZFile::setSize(int)` is a TypeError
	 * under `strict_types`, so the failure surfaced as a type error rather than as what went wrong.
	 *
	 * @param string $path
	 *
	 * @return int
	 */
	private static function sizeOf(string $path): int
	{
		$size = @\filesize($path);

		if (false === $size) {
			throw new RuntimeException(\sprintf('Unable to read the size of "%s" after writing it.', $path));
		}

		return $size;
	}
}
