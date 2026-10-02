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

namespace OZONE\Core\FS\Images\Interfaces;

use OZONE\Core\FS\Images\ImageRecipe;

/**
 * What OZone asks of an image library: the library stays behind it.
 */
interface ImageProcessorInterface
{
	/**
	 * The name of the driver it renders with (`vips`, `imagick`, `gd`).
	 */
	public function driver(): string;

	/**
	 * An image rendered as a recipe says, in its own format: upright (its orientation applied),
	 * never enlarged, and without its metadata (location, camera, comments).
	 *
	 * @param string      $bytes  the image as stored
	 * @param string      $mime   its media type, which the rendition keeps
	 * @param ImageRecipe $recipe what to do
	 *
	 * @return string the rendition's bytes
	 */
	public function render(string $bytes, string $mime, ImageRecipe $recipe): string;
}
