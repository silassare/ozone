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
use Psr\Http\Message\StreamInterface;

/**
 * Class Stream.
 */
class Stream implements StreamInterface
{
	/**
	 * Bit mask to determine if the stream is a pipe.
	 *
	 * This is octal as per header stat.h
	 */
	public const FSTAT_MODE_S_IFIFO = 0010000;

	/**
	 * Resource modes.
	 *
	 * @see http://php.net/manual/function.fopen.php
	 */
	protected static array $modes = [
		'readable' => ['r', 'r+', 'w+', 'a+', 'x+', 'c+'],
		'writable' => ['r+', 'w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+'],
	];

	/**
	 * The underlying stream resource.
	 *
	 * @var resource
	 */
	protected $stream;

	/**
	 * Stream metadata.
	 */
	protected ?array $meta = null;

	/**
	 * Is this stream readable?
	 */
	protected ?bool $readable = null;

	/**
	 * Is this stream writable?
	 */
	protected ?bool $writable = null;

	/**
	 * Is this stream seekable?
	 */
	protected ?bool $seekable = null;

	/**
	 * The size of the stream if known.
	 */
	protected ?int $size = null;

	/**
	 * Is this stream a pipe?
	 */
	protected ?bool $isPipe = null;

	/**
	 * Creates a new Stream.
	 *
	 * @param resource $stream a PHP resource handle
	 *
	 * @throws InvalidArgumentException if argument is not a resource
	 */
	public function __construct($stream)
	{
		$this->attach($stream);
	}

	/**
	 * {@inheritDoc}
	 */
	public function __toString(): string
	{
		if (!$this->isAttached()) {
			return '';
		}

		try {
			$this->rewind();

			return $this->getContents();
		} catch (\RuntimeException) {
			return '';
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function detach()
	{
		$oldResource    = $this->stream;
		$this->stream   = null;
		$this->meta     = null;
		$this->readable = null;
		$this->writable = null;
		$this->seekable = null;
		$this->size     = null;
		$this->isPipe   = null;

		return $oldResource;
	}

	/**
	 * {@inheritDoc}
	 */
	public function rewind(): void
	{
		if (!$this->isSeekable() || false === \rewind($this->stream)) {
			throw new RuntimeException('Could not rewind stream');
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSeekable(): bool
	{
		if (null === $this->seekable) {
			$this->seekable = false;

			if ($this->isAttached()) {
				$meta           = $this->getMetadata();
				$this->seekable = !$this->isPipe() && $meta['seekable'];
			}
		}

		return $this->seekable;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMetadata(?string $key = null): mixed
	{
		$this->meta = \stream_get_meta_data($this->stream);

		if ((null === $key) === true) {
			return $this->meta;
		}

		return $this->meta[$key] ?? null;
	}

	/**
	 * Returns whether or not the stream is a pipe.
	 *
	 * @return bool
	 */
	public function isPipe(): bool
	{
		if (null === $this->isPipe) {
			$this->isPipe = false;

			if ($this->isAttached()) {
				$mode         = \fstat($this->stream)['mode'];
				$this->isPipe = ($mode & self::FSTAT_MODE_S_IFIFO) !== 0;
			}
		}

		return $this->isPipe;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getContents(): string
	{
		$result = false;

		if ($this->isReadable()) {
			$this->rewind();

			$result = \stream_get_contents($this->stream);
		}

		if (false === $result) {
			throw new RuntimeException('Could not get contents of stream');
		}

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isReadable(): bool
	{
		if (null === $this->readable) {
			if ($this->isPipe()) {
				$this->readable = true;
			} else {
				$this->readable = false;

				if ($this->isAttached()) {
					$meta = $this->getMetadata();

					foreach (self::$modes['readable'] as $mode) {
						if (\str_starts_with($meta['mode'], $mode)) {
							$this->readable = true;

							break;
						}
					}
				}
			}
		}

		return $this->readable;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @psalm-suppress InvalidPropertyAssignmentValue
	 */
	public function close(): void
	{
		if (true === $this->isAttached()) {
			if ($this->isPipe()) {
				\pclose($this->stream);
			} else {
				\fclose($this->stream);
			}
		}

		$this->detach();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSize(): ?int
	{
		if (!$this->size && true === $this->isAttached()) {
			$stats      = \fstat($this->stream);
			$this->size = isset($stats['size']) && !$this->isPipe() ? $stats['size'] : null;
		}

		return $this->size;
	}

	/**
	 * {@inheritDoc}
	 */
	public function tell(): int
	{
		if (!$this->isAttached() || ($position = \ftell($this->stream)) === false || $this->isPipe()) {
			throw new RuntimeException('Could not get the position of the pointer in stream');
		}

		return $position;
	}

	/**
	 * {@inheritDoc}
	 */
	public function eof(): bool
	{
		return !$this->isAttached() || \feof($this->stream);
	}

	/**
	 * {@inheritDoc}
	 */
	public function seek(int $offset, int $whence = \SEEK_SET): void
	{
		// Note that fseek returns 0 on success!
		if (!$this->isSeekable() || -1 === \fseek($this->stream, $offset, $whence)) {
			throw new RuntimeException('Could not seek in stream');
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function read(int $length): string
	{
		if (!$this->isReadable() || ($data = \fread($this->stream, $length)) === false) {
			throw new RuntimeException('Could not read from stream');
		}

		return $data;
	}

	/**
	 * {@inheritDoc}
	 */
	public function write(string $string): int
	{
		if (!$this->isWritable() || ($written = \fwrite($this->stream, $string)) === false) {
			throw new RuntimeException('Could not write to stream');
		}

		// resets size so that it will be recalculated on next call to getSize()
		$this->size = null;

		return $written;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isWritable(): bool
	{
		if (null === $this->writable) {
			$this->writable = false;

			if ($this->isAttached()) {
				$meta = $this->getMetadata();

				foreach (self::$modes['writable'] as $mode) {
					if (\str_starts_with($meta['mode'], $mode)) {
						$this->writable = true;

						break;
					}
				}
			}
		}

		return $this->writable;
	}

	/**
	 * Attach new resource to this object.
	 *
	 * @param resource $newStream a PHP resource handle
	 */
	protected function attach($newStream): void
	{
		if (false === \is_resource($newStream)) {
			throw new InvalidArgumentException(__METHOD__ . ' argument must be a valid PHP resource');
		}

		if (true === $this->isAttached()) {
			$this->detach();
		}

		$this->stream = $newStream;
	}

	/**
	 * Is a resource attached to this stream?
	 *
	 * @return bool
	 */
	protected function isAttached(): bool
	{
		return \is_resource($this->stream);
	}
}
