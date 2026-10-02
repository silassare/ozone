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
 * ({@see \OZONE\Core\FS\Filters\ImageFilterTokens::normalize()}), so it is bounded already. It is
 * applied in this order: turned and mirrored, the area kept, resized (around the focal point when
 * cropped to a size), the effects, the watermark, then encoded.
 */
final class ImageRecipe
{
	/**
	 * @param null|int                       $width     the width asked for, never beyond the image's own
	 * @param null|int                       $height    the height asked for, never beyond the image's own
	 * @param bool                           $cover     with both: cropped to fill them, else fitted in them
	 * @param int                            $quality   1 to 100, where the format has one
	 * @param list<string>                   $effects   the effect tokens, in order (a project's own among them)
	 * @param int                            $rotate    0, 90, 180 or 270, clockwise
	 * @param null|string                    $flip      `h` or `v`
	 * @param null|array{int, int, int, int} $area      x, y, width, height, in thousandths of the image
	 * @param null|array{int, int}           $focal     x, y, in thousandths: what a crop keeps in view
	 * @param null|string                    $format    `webp` or `avif`; null: the image's own
	 * @param null|string                    $watermark a watermark's name (`ImageWatermarks`)
	 */
	public function __construct(
		public readonly ?int $width = null,
		public readonly ?int $height = null,
		public readonly bool $cover = false,
		public readonly int $quality = 100,
		public readonly array $effects = [],
		public readonly int $rotate = 0,
		public readonly ?string $flip = null,
		public readonly ?array $area = null,
		public readonly ?array $focal = null,
		public readonly ?string $format = null,
		public readonly ?string $watermark = null,
	) {}

	/**
	 * The recipe of canonical tokens; `auto` has been resolved to a format, or dropped, before.
	 *
	 * @param list<string> $tokens
	 * @param int          $thumbSize the size of a `thumb` without one (`OZ_THUMBNAIL_MAX_SIZE`)
	 */
	public static function fromTokens(array $tokens, int $thumbSize): self
	{
		$width     = null;
		$height    = null;
		$cover     = false;
		$crop      = null;
		$quality   = 100;
		$effects   = [];
		$rotate    = 0;
		$flip      = null;
		$area      = null;
		$focal     = null;
		$format    = null;
		$watermark = null;

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
			} elseif (\preg_match('~^r(90|180|270)$~', $token, $m)) {
				$rotate = (int) $m[1];
			} elseif ('fliph' === $token || 'flipv' === $token) {
				$flip = \substr($token, 4);
			} elseif (\preg_match('~^area(\d+)x(\d+)x(\d+)x(\d+)$~', $token, $m)) {
				$area = [(int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]];
			} elseif (\preg_match('~^focal(\d+)x(\d+)$~', $token, $m)) {
				$focal = [(int) $m[1], (int) $m[2]];
			} elseif ('webp' === $token || 'avif' === $token) {
				$format = $token;
			} elseif (\preg_match('~^wm([a-z0-9]+)$~', $token, $m)) {
				$watermark = $m[1];
			} elseif ('auto' !== $token) {
				$effects[] = $token;
			}
		}

		return new self(
			$width,
			$height,
			$crop ?? $cover,
			$quality,
			$effects,
			$rotate,
			$flip,
			$area,
			$focal,
			$format,
			$watermark
		);
	}
}
