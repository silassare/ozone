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
 * What a page needs of an image before it loads: its size as shown (upright), which reserves its
 * place, and its dominant color, shown in that place meanwhile.
 */
final class ImageInfo
{
	/**
	 * @param int    $width  in pixels, upright
	 * @param int    $height in pixels, upright
	 * @param string $color  `#rrggbb`
	 */
	public function __construct(
		public readonly int $width,
		public readonly int $height,
		public readonly string $color,
	) {}

	/**
	 * As a file's data keeps it (`image`).
	 *
	 * @return array{width: int, height: int, color: string}
	 */
	public function toArray(): array
	{
		return ['width' => $this->width, 'height' => $this->height, 'color' => $this->color];
	}
}
