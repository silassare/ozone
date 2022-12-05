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

namespace OZONE\OZ\FS\Drivers;

use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Db\OZFile;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\FS\FilesManager;
use OZONE\OZ\FS\FileStream;
use OZONE\OZ\FS\FilesUtils;
use OZONE\OZ\FS\Interfaces\FilesDriverInterface;
use OZONE\OZ\Http\Body;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Http\UploadedFile;

/**
 * Class LocalFilesDriver.
 */
class LocalFilesDriver implements FilesDriverInterface
{
	/**
	 * {@inheritDoc}
	 */
	public static function getInstance(): self
	{
		return new self();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getStream(OZFile $file): FileStream
	{
		$src = $this->toAbsolutePath($file->getRef());

		$this->assertFile($src);

		return FileStream::fromPath($src);
	}

	/**
	 * {@inheritDoc}
	 */
	public function upload(UploadedFile $upload): OZFile
	{
		if (($error = $upload->getError()) !== \UPLOAD_ERR_OK) {
			throw new RuntimeException(FilesUtils::uploadErrorMessage($error));
		}

		if (null !== ($result = FilesUtils::parseFileAlias($upload))) {
			return $result->cloneFile();
		}

		// should we replace client filename extension with our extension ?
		$filename = $upload->getClientFilename();
		$mimetype = $upload->getClientMediaType();

		$ext = FilesUtils::getRealExtension($filename, $mimetype);
		$ref = Hasher::genFileName('upload') . '.' . $ext;

		$destination = $this->toAbsolutePath($ref);

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

		$ext = FilesUtils::getRealExtension($filename, $mimetype);
		$ref = Hasher::genFileName('save') . '.' . $ext;

		$destination = $this->toAbsolutePath($ref);

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
		$fm = new FilesManager(FilesUtils::getFilesUploadDirectory());

		return $fm->filter()
			->isFile()
			->exists()
			->check($file->getRef());
	}

	/**
	 * {@inheritDoc}
	 */
	public function serve(OZFile $file, Response $response): Response
	{
		$src = $this->toAbsolutePath($file->getRef());

		$this->assertFile($src);

		if (Configs::get('oz.files', 'OZ_SERVER_SENDFILE_ENABLED')) {
			$path = Configs::get('oz.files', 'OZ_SERVER_SENDFILE_REDIRECT_PATH') . $file->getRef();

			return $response->withHeader('X-Accel-Redirect', $path);
		}

		$size      = $file->getSize();
		$mime_type = $file->getMimeType();
		$body      = Body::fromPath($src);

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
		$fm       = new FilesManager();
		$abs_path = $this->toAbsolutePath($file->getRef());
		$fm->wf($abs_path, $content);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function append(OZFile $file, string $data): self
	{
		$fm       = new FilesManager();
		$abs_path = $this->toAbsolutePath($file->getRef());

		$fm->append($abs_path, $data);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepend(OZFile $file, string $data): self
	{
		$fm       = new FilesManager();
		$abs_path = $this->toAbsolutePath($file->getRef());

		$fm->prepend($abs_path, $data);

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

		$abs_path = $this->toAbsolutePath($file->getRef());
		$fm       = new FilesManager();

		if (!$fm->filter()
			->exists()
			->check($abs_path)) {
			return false;
		}

		$fm->rm($abs_path);

		return true;
	}

	/**
	 * @param string $path
	 */
	private function assertFile(string $path): void
	{
		$fm = new FilesManager();
		$fm->filter()
			->exists()
			->isFile()
			->isReadable()
			->assert($path);
	}

	/**
	 * Returns file absolute path with the given ref.
	 *
	 * @param string $ref
	 *
	 * @return string
	 */
	private function toAbsolutePath(string $ref): string
	{
		$fm = new FilesManager();

		return $fm->cd(FilesUtils::getFilesUploadDirectory(), true)
			->resolve($ref);
	}
}
