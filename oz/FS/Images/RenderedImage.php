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

namespace OZONE\Core\FS\Images;

/**
 * A rendition: its bytes, and its media type, the image's own or the format the recipe asked for.
 */
final class RenderedImage
{
	public function __construct(
		public readonly string $bytes,
		public readonly string $mime,
	) {}
}
