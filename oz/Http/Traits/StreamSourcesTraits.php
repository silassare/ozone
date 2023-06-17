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

/**
 * Trait StreamSourcesTraits.
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
		return new self(\fopen('php://temp', $mode));
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
		$self = new self(\fopen('php://temp', $mode));
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
		return new self(\fopen($path, $mode));
	}
}
