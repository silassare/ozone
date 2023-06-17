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
use OZONE\Core\Utils\Random;

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
		$ref = Random::fileName('upload') . '.' . $ext;

		$destination = $this->uploadsDir()
			->resolve($ref);

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
		$ref = Random::fileName('save') . '.' . $ext;

		$destination = $this->uploadsDir()
			->resolve($ref);

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
	public function publicUri(Context $context, OZFile $file): Uri
	{
		$this->require($file->getRef());

		// TODO for local files, we could send the public file uri to let the web server handle the file serving
		return $context->buildRouteUri(GetFilesView::MAIN_ROUTE, [
			'oz_file_id'  => $file->getID(),
			'oz_file_key' => $file->getKey(),
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function serve(OZFile $file, Response $response): Response
	{
		$abs_path = $this->require($file->getRef());

		// TODO should we keep this? see publicUri
		if (Settings::get('oz.files', 'OZ_SERVER_SENDFILE_ENABLED')) {
			$path = Settings::get('oz.files', 'OZ_SERVER_SENDFILE_REDIRECT_PATH') . $file->getRef();

			return $response->withHeader('X-Accel-Redirect', $path);
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
	 */
	public function write(OZFile $file, string $content): self
	{
		$abs_path = $this->require($file->getRef());

		(new FilesManager())->wf($abs_path, $content);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function append(OZFile $file, string $data): self
	{
		$abs_path = $this->require($file->getRef());

		(new FilesManager())->append($abs_path, $data);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepend(OZFile $file, string $data): self
	{
		$abs_path = $this->require($file->getRef());

		(new FilesManager())->prepend($abs_path, $data);

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
	 * Returns file absolute path with the given ref.
	 *
	 * @param string $ref
	 *
	 * @return null|string
	 */
	private function localize(string $ref): ?string
	{
		$dir = $this->uploadsDir();
		if ($dir->filter()
			->isFile()
			->check($ref)) {
			return $dir->resolve($ref);
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
	private function require(string $ref): string
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
