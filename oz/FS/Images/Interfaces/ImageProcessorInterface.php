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

use OZONE\Core\FS\Images\ImageInfo;
use OZONE\Core\FS\Images\ImageRecipe;
use OZONE\Core\FS\Images\RenderedImage;

/**
 * What OZone asks of an image library: the library stays behind it (a project's own tokens, which
 * draw with Intervention Image, aside).
 */
interface ImageProcessorInterface
{
	/**
	 * The name of the driver it renders with (`vips`, `imagick`, `gd`).
	 */
	public function driver(): string;

	/**
	 * Whether it can write images of a media type (`image/avif`): what `auto` chooses among.
	 */
	public function supports(string $mime): bool;

	/**
	 * An image rendered as a recipe says: upright (its orientation applied), never enlarged, and
	 * without its metadata (location, camera, comments).
	 *
	 * @param string      $bytes  the image as stored
	 * @param string      $mime   its media type, which the rendition keeps unless the recipe names a format
	 * @param ImageRecipe $recipe what to do
	 */
	public function render(string $bytes, string $mime, ImageRecipe $recipe): RenderedImage;

	/**
	 * An image's size as shown (its orientation applied) and its dominant color.
	 *
	 * @param string $bytes the image as stored
	 */
	public function probe(string $bytes): ImageInfo;
}
