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

namespace OZONE\Core\Http\Traits;

use OZONE\Core\Exceptions\RuntimeException;

/**
 * Trait StreamSourcesTraits.
 *
 * Every factory here checks its `fopen()`. It used to hand the `false` straight to the stream
 * constructor, so `fromPath()` on a missing or unreadable file returned a stream that failed later,
 * somewhere else, with an error that named neither the path nor the reason.
 */
trait StreamSourcesTraits
{
	/**
	 * Create new stream.
	 *
	 * @param string $mode fopen mode
	 *
	 * @return static
	 */
	public static function create(string $mode = 'rb+'): static
	{
		return new self(self::openOrFail('php://temp', $mode));
	}

	/**
	 * Create new stream with string.
	 *
	 * @param string $content content
	 * @param string $mode    fopen mode, default to 'rb+' for both reading and writing in append mode
	 *
	 * @return static
	 */
	public static function fromString(string $content, string $mode = 'rb+'): static
	{
		$self = new self(self::openOrFail('php://temp', $mode));
		$self->write($content);

		return $self;
	}

	/**
	 * Create new stream with string.
	 *
	 * @param string $path file path
	 * @param string $mode fopen mode, default to 'rb' read binary only mode
	 *
	 * @return static
	 */
	public static function fromPath(string $path, string $mode = 'rb'): static
	{
		return new self(self::openOrFail($path, $mode));
	}

	/**
	 * Opens a path, or fails saying which and why.
	 *
	 * @param string $path
	 * @param string $mode
	 *
	 * @return resource
	 */
	private static function openOrFail(string $path, string $mode)
	{
		// The warning is suppressed and the result checked: PHP's own message would carry the path
		// into the output, and the exception below says the same thing where it belongs.
		$handle = @\fopen($path, $mode);

		if (false === $handle) {
			throw new RuntimeException(\sprintf('Unable to open "%s" in mode "%s".', $path, $mode));
		}

		return $handle;
	}
}
