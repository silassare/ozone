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

namespace OZONE\Core\Auth\Picture;

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\Enums\FileKind;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\FS;

/**
 * An image answered in place of a user's picture: a file, or bytes with their type. It is rendered
 * as a picture is, so it must be an image.
 */
final class AuthUserPictureImage
{
	private function __construct(
		private readonly ?string $path,
		private readonly ?string $bytes,
		private readonly string $mime
	) {
		if (FileKind::IMAGE !== FileKind::fromMime($mime)) {
			throw new RuntimeException('A user picture fallback must be an image.', ['_mime' => $mime]);
		}
	}

	/**
	 * An image file: absolute, or from the project's root (`app/files/avatar.png`).
	 */
	public static function fromPath(string $path): self
	{
		$abs = app()->getProjectDir()->resolve($path);

		if (!\is_file($abs) || !\is_readable($abs)) {
			throw new RuntimeException('A user picture fallback names no readable file.', ['_path' => $abs]);
		}

		$mime = FS::getMimeType($abs);

		return new self($abs, null, \is_string($mime) ? $mime : '');
	}

	/**
	 * Image bytes, and their media type (`image/png`).
	 */
	public static function fromBytes(string $bytes, string $mime): self
	{
		return new self(null, $bytes, $mime);
	}

	/**
	 * The image's media type.
	 */
	public function getMime(): string
	{
		return $this->mime;
	}

	/**
	 * What the image is, changing with its content: its renditions are kept under it.
	 */
	public function identity(): string
	{
		if (null !== $this->path) {
			return 'path:' . $this->path . ':' . (int) \filesize($this->path) . ':' . (int) \filemtime($this->path);
		}

		return 'bytes:' . \hash('xxh128', (string) $this->bytes);
	}

	/**
	 * A fresh, unread stream of the image.
	 */
	public function stream(): FileStream
	{
		return null !== $this->path ? FileStream::fromPath($this->path) : FileStream::fromString((string) $this->bytes);
	}
}
