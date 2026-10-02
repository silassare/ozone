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
 * What a rendition of an image is: read from the canonical filter tokens
 * ({@see \OZONE\Core\FS\Filters\ImageFilterTokens::normalize()}), so it is bounded already.
 */
final class ImageRecipe
{
	/**
	 * @param null|int     $width   the width asked for, never beyond the image's own
	 * @param null|int     $height  the height asked for, never beyond the image's own
	 * @param bool         $cover   with both: cropped to fill them (centered), else fitted in them
	 * @param int          $quality 1 to 100, where the format has one
	 * @param list<string> $effects `grayscale`, `sepia`, `sharpen`, `blur{N}`, in order
	 */
	public function __construct(
		public readonly ?int $width = null,
		public readonly ?int $height = null,
		public readonly bool $cover = false,
		public readonly int $quality = 100,
		public readonly array $effects = [],
	) {}

	/**
	 * The recipe of canonical tokens.
	 *
	 * @param list<string> $tokens
	 * @param int          $thumbSize the size of a `thumb` without one (`OZ_THUMBNAIL_MAX_SIZE`)
	 */
	public static function fromTokens(array $tokens, int $thumbSize): self
	{
		$width   = null;
		$height  = null;
		$cover   = false;
		$crop    = null;
		$quality = 100;
		$effects = [];

		foreach ($tokens as $token) {
			if ('thumb' === $token) {
				$width ??= $thumbSize;
				$height ??= $thumbSize;
				$cover = true;
			} elseif (\preg_match('~^thumb(\d+)$~', $token, $m)) {
				$width  = (int) $m[1];
				$height = (int) $m[1];
				$cover  = true;
			} elseif (\preg_match('~^w(\d+)$~', $token, $m)) {
				$width = (int) $m[1];
			} elseif (\preg_match('~^h(\d+)$~', $token, $m)) {
				$height = (int) $m[1];
			} elseif (\preg_match('~^q(\d+)$~', $token, $m)) {
				$quality = \max(1, \min(100, (int) $m[1]));
			} elseif ('crop' === $token || 'nocrop' === $token) {
				$crop = 'crop' === $token;
			} else {
				$effects[] = $token;
			}
		}

		return new self($width, $height, $crop ?? $cover, $quality, $effects);
	}
}
