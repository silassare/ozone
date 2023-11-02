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
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Interfaces\FileAuthProviderInterface;
use OZONE\Core\FS\Interfaces\StorageInterface;
use OZONE\Core\FS\Views\GetFilesView;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\UploadedFile;
use OZONE\Core\Http\Uri;
use OZONE\Core\Utils\Hasher;
use OZONE\Core\Utils\Random;
use PHPUtils\Str;
use Throwable;

/**
 * Class AbstractLocalStorage.
 */
abstract class AbstractLocalStorage implements StorageInterface
{
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

		// should we replace client filename extension with our extension ?
		$filename = $upload->getClientFilename();
		$mimetype = $upload->getClientMediaType();

		$ext = FS::getRealExtension($filename, $mimetype);
		$ref = self::safeRef($filename, $ext);

		$destination = $this->getDestinationWithRef($ref);

		$f = new OZFile();

		$upload->moveTo($destination);

		$filesize = \filesize($destination);

		$f->setName($filename)
			->setRef($ref)
			->setMimeType($mimetype)
			->setExtension($ext)
			->setSize($filesize);

		return $f;
	}

	/**
	 * {@inheritDoc}
	 */
	public function saveStream(FileStream $source, string $mimetype, string $filename): OZFile
	{
		$f = new OZFile();

		$ext = FS::getRealExtension($filename, $mimetype);
		$ref = self::safeRef($filename, $ext, 'save');

		$destination = $this->getDestinationWithRef($ref);

		$fw = \fopen($destination, 'wb');

		while (!$source->eof()) {
			\fwrite($fw, $source->read(4096));
		}

		\fclose($fw);

		$filesize = \filesize($destination);

		$f->setName($filename)
			->setRef($ref)
			->setMimeType($mimetype)
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
	public function restrictedUri(Context $context, FileAuthProviderInterface $provider): Uri
	{
		$file = $provider->getFile();
		$this->require($file->getRef());

		$credentials = $provider->getCredentials();

		$ref = $credentials->getReference();
		$key = $credentials->getToken();

		return $context->buildRouteUri(GetFilesView::MAIN_ROUTE, [
			'oz_file_id'  => $file->getID(),
			'oz_file_key' => $key,
			'oz_file_ref' => $ref,
		]);
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
		$mime_type = $file->getMimeType();
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

		(new FilesManager())->wf($abs_path, $content);

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

		(new FilesManager())->append($abs_path, $data);

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

		(new FilesManager())->prepend($abs_path, $data);

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

		(new FilesManager())->rm($abs_path);

		return true;
	}

	/**
	 * Gets the files uploads directory.
	 *
	 * @return \OZONE\Core\FS\FilesManager
	 */
	abstract protected function uploadsDir(): FilesManager;

	/**
	 * Creates a safe reference.
	 *
	 * The reference here is a unique filename built from the given filename.
	 *
	 * @param string $filename
	 * @param string $ext
	 * @param string $prefix
	 *
	 * @return string
	 */
	protected static function safeRef(string $filename, string $ext, string $prefix = 'upload'): string
	{
		$name = Str::stringToURLSlug($filename);

		if (!$name) {
			$name = Random::fileName($prefix);
		}

		// ensure the name has less than 100 char before we add the hash
		$name = \trim(\substr($name, 0, 100), '-');

		// the hash is used to avoid name collision
		$name .= '-' . Hasher::shorten(Random::string());
		$ext = \strtolower($ext);

		if (\str_contains($ext, 'php') || \str_contains($ext, 'inc') || \str_contains($ext, 'phtml')) {
			return $name;
		}

		return $name . '.' . $ext;
	}

	/**
	 * Returns file absolute path with the given ref.
	 *
	 * @param string $ref
	 *
	 * @return string
	 */
	protected function getDestinationWithRef(string $ref): string
	{
		$hash = \md5($ref);
		$dir1 = \substr($hash, 0, 2);
		$dir2 = \substr($hash, 2, 2);
		$fs   = new FilesManager();
		$path = $this->uploadsDir()
			->resolve('.' . DS . $dir1 . DS . $dir2);

		return $fs->cd($path, true)->resolve($ref);
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
		$fs          = new FilesManager();
		$destination = $this->getDestinationWithRef($ref);
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
