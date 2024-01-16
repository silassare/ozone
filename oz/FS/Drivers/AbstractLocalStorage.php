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

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Providers\FileAuthProvider;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Interfaces\StorageInterface;
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
	/**
	 * AbstractLocalStorage constructor.
	 *
	 * @param string $name the driver assigned name
	 */
	public function __construct(protected readonly string $name) {}

	/**
	 * {@inheritDoc}
	 */
	public function getStream(OZFile $file): FileStream
	{
		$abs_path = $this->require($file->getRef());

		return FileStream::fromPath($abs_path);
	}

	/**
	 * {@inheritDoc}
	 */
	public function upload(UploadedFile $upload): OZFile
	{
		if (($error = $upload->getError()) !== \UPLOAD_ERR_OK) {
			$info             = FS::uploadErrorInfo($error);
			$debug['_reason'] = $info['reason'];

			throw new RuntimeException($info['message'], $debug);
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

		$filesize = \filesize($destination);

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
	public function saveStream(FileStream $source, string $mimetype, string $filename): OZFile
	{
		$filename   = \trim($filename);
		$ext        = FS::getRealExtension($filename, $mimetype);
		$clean_name = FS::sanitizeFilename($filename, $ext, 'save');

		if (empty($filename)) {
			$filename = $clean_name;
		}

		$destination = $this->createDestinationPath($clean_name, $ref);

		$fw = \fopen($destination, 'wb');

		while (!$source->eof()) {
			\fwrite($fw, $source->read(4096));
		}

		\fclose($fw);

		$filesize = \filesize($destination);

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
	public function saveFromPath(string $path, string $mimetype, string $filename): OZFile
	{
		return $this->saveStream(FileStream::fromPath($path), $mimetype, $filename);
	}

	/**
	 * {@inheritDoc}
	 */
	public function saveRaw(string $content, string $mimetype, string $filename): OZFile
	{
		return $this->saveStream(FileStream::fromString($content), $mimetype, $filename);
	}

	/**
	 * {@inheritDoc}
	 */
	public function exists(OZFile $file): bool
	{
		return null !== $this->localize($file->getRef());
	}

	/**
	 * {@inheritDoc}
	 */
	public function revocableAccessUri(Context $context, FileAuthProvider $provider): Uri
	{
		$file = $provider->getFile();
		$this->require($file->getRef());

		$credentials = $provider->getCredentials();

		return FS::buildFileUri($context, $file, $credentials);
	}

	/**
	 * {@inheritDoc}
	 */
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

		$size      = $file->getSize();
		$mime_type = $file->getMime();
		$body      = Body::fromPath($abs_path);

		return $response->withHeader('Content-Transfer-Encoding', 'binary')
			->withHeader('Content-Length', (string) $size)
			->withHeader('Content-type', $mime_type)
			->withBody($body);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	public function write(OZFile $file, FileStream|string $content): self
	{
		$abs_path = $this->require($file->getRef());

		if (\file_exists($abs_path)) {
			$f = \fopen($abs_path, 'wb');
			\ftruncate($f, 0);
			\fclose($f);
		}

		FS::fromRoot()->wf($abs_path, $content);

		\clearstatcache(true, $abs_path);

		$file->setSize(\filesize($abs_path))->setUpdatedAt(\time())->save();

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	public function append(OZFile $file, FileStream|string $data): self
	{
		$abs_path = $this->require($file->getRef());

		FS::fromRoot()->append($abs_path, $data);

		\clearstatcache(true, $abs_path);

		$file->setSize(\filesize($abs_path))->setUpdatedAt(\time())->save();

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	public function prepend(OZFile $file, FileStream|string $data): self
	{
		$abs_path = $this->require($file->getRef());

		FS::fromRoot()->prepend($abs_path, $data);

		\clearstatcache(true, $abs_path);

		$file->setSize(\filesize($abs_path))->setUpdatedAt(\time())->save();

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
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
}
