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

namespace OZONE\Core\FS\Filters;

use OZONE\Core\App\Settings;
use OZONE\Core\FS\Images\ImageTokens;
use OZONE\Core\FS\Images\ImageWatermarks;

/**
 * Class ImageFilterTokens.
 *
 * Reduces the filter tokens of an image URL to one canonical list, before anything is rendered or
 * cached: a URL cannot make the server render, or keep on disk, an unbounded number of renditions.
 *
 * | Token                          | Bounds                                                       |
 * | ------------------------------ | ------------------------------------------------------------ |
 * | `w{N}`, `h{N}`, `thumb{N}`     | snapped to the nearest of `OZ_IMAGE_FILTERS_SIZES`           |
 * | `thumb`, `crop`, `nocrop`      |                                                              |
 * | `r90`, `r180`, `r270`          | a quarter turn clockwise; nothing else                        |
 * | `fliph`, `flipv`               | mirrored horizontally or vertically                          |
 * | `area{x}x{y}x{w}x{h}`          | a part of the image in thousandths, snapped to 5, inside it  |
 * | `focal{x}x{y}`                 | the point a crop keeps in view, in thousandths, snapped to 10 |
 * | `q{N}`                         | a quality snapped to a step of 5, from 5 to 100              |
 * | `webp`, `avif`, `auto`         | the format; `auto`: the best the browser accepts             |
 * | `grayscale`, `sepia`, `sharpen`, `invert` | effects                                           |
 * | `blur{N}`                      | at most `OZ_IMAGE_FILTERS_MAX_BLUR` passes                   |
 * | `bright{N}`, `brightn{N}`      | brightness up or down (`n`), 5 to 100, snapped to 5          |
 * | `contrast{N}`, `contrastn{N}`  | contrast up or down, as brightness                           |
 * | `pixel{N}`                     | pixelated in blocks of 4, 8, 16, 32 or 64 pixels             |
 * | `wm{name}`                     | a watermark `OZ_IMAGE_WATERMARKS` declares                   |
 * | a project's own                | as its `ImageTokenInterface::canonical()` says               |
 *
 * An unknown token is dropped; a size, a geometry, a format or a watermark given twice keeps its
 * last value; an effect given twice counts once and effects keep the order given. At most
 * `OZ_IMAGE_FILTERS_MAX_TOKENS` tokens are kept, in this order: sizes, geometry, quality, format,
 * effects, watermark.
 */
final class ImageFilterTokens
{
	/** Effects without a value, applied in the order given, each once. */
	private const EFFECTS = ['grayscale', 'sepia', 'sharpen', 'invert'];

	/** The block sizes `pixel{N}` snaps to. */
	private const PIXEL_SIZES = [4, 8, 16, 32, 64];

	/** The canonical groups, in the order a rendition applies them. */
	private const ORDER = ['w', 'h', 'thumb', 'crop', 'r', 'flip', 'area', 'focal', 'q', 'format'];

	/**
	 * The canonical tokens.
	 *
	 * @param string[] $tokens
	 *
	 * @return list<string>
	 */
	public static function normalize(array $tokens): array
	{
		$single    = [];
		$effects   = [];
		$watermark = null;

		foreach ($tokens as $token) {
			if (\preg_match('~^(w|h|thumb)(\d{1,6})$~', $token, $m)) {
				$single[$m[1]] = $m[1] . self::snapSize((int) $m[2]);
			} elseif ('thumb' === $token) {
				$single['thumb'] = 'thumb';
			} elseif ('crop' === $token || 'nocrop' === $token) {
				$single['crop'] = $token;
			} elseif (\in_array($token, ['r90', 'r180', 'r270'], true)) {
				$single['r'] = $token;
			} elseif ('fliph' === $token || 'flipv' === $token) {
				$single['flip'] = $token;
			} elseif (\preg_match('~^area(\d{1,4})x(\d{1,4})x(\d{1,4})x(\d{1,4})$~', $token, $m)) {
				$area = self::area((int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]);

				if (null !== $area) {
					$single['area'] = $area;
				}
			} elseif (\preg_match('~^focal(\d{1,4})x(\d{1,4})$~', $token, $m)) {
				$single['focal'] = 'focal' . self::snap((int) $m[1], 10, 0, 1000)
					. 'x' . self::snap((int) $m[2], 10, 0, 1000);
			} elseif (\preg_match('~^q(\d{1,3})$~', $token, $m)) {
				$single['q'] = 'q' . self::snapQuality((int) $m[1]);
			} elseif (\in_array($token, ['webp', 'avif', 'auto'], true)) {
				$single['format'] = $token;
			} elseif (\preg_match('~^wm([a-z0-9]{1,32})$~', $token, $m)) {
				// Only a declared watermark: any other name would be another cache key for nothing.
				if (null !== ImageWatermarks::get($m[1])) {
					$watermark = $token;
				}
			} else {
				$effect = self::effect($token);

				if (null !== $effect && !\in_array($effect, $effects, true)) {
					$effects[] = $effect;
				}
			}
		}

		$ordered = [];

		foreach (self::ORDER as $name) {
			if (isset($single[$name])) {
				$ordered[] = $single[$name];
			}
		}

		$all = [...$ordered, ...$effects, ...(null === $watermark ? [] : [$watermark])];
		$max = \max(1, (int) Settings::get('oz.files', 'OZ_IMAGE_FILTERS_MAX_TOKENS', 12));

		// Over the limit, a watermark is never the one dropped: it is the last asked, and may be due.
		if (\count($all) > $max && null !== $watermark) {
			return [...\array_slice([...$ordered, ...$effects], 0, $max - 1), $watermark];
		}

		return \array_slice($all, 0, $max);
	}

	/**
	 * The allowed size nearest to a size, the larger on a tie.
	 */
	public static function snapSize(int $size): int
	{
		$sizes   = (array) Settings::get('oz.files', 'OZ_IMAGE_FILTERS_SIZES');
		$allowed = \array_values(\array_map('intval', $sizes));
		\sort($allowed);

		$best = $allowed[0] ?? $size;

		foreach ($allowed as $candidate) {
			if (\abs($candidate - $size) <= \abs($best - $size)) {
				$best = $candidate;
			}
		}

		return $best;
	}

	/**
	 * A quality snapped to a step of 5, from 5 to 100.
	 */
	public static function snapQuality(int $quality): int
	{
		return self::snap($quality, 5, 5, 100);
	}

	/**
	 * An effect's canonical form: a known effect, a bounded value, or a project's own token.
	 */
	private static function effect(string $token): ?string
	{
		if (\in_array($token, self::EFFECTS, true)) {
			return $token;
		}

		if ('blur' === $token || \preg_match('~^blur(\d{1,6})$~', $token)) {
			$passes = 'blur' === $token ? 1 : (int) \substr($token, 4);
			$max    = \max(1, (int) Settings::get('oz.files', 'OZ_IMAGE_FILTERS_MAX_BLUR', 10));

			return 'blur' . \max(1, \min($max, $passes));
		}

		if (\preg_match('~^(bright|contrast)(n?)(\d{1,3})$~', $token, $m)) {
			$level = self::snap((int) $m[3], 5, 0, 100);

			// No change at all: no token, so not another rendition.
			return 0 === $level ? null : $m[1] . $m[2] . $level;
		}

		if (\preg_match('~^pixel(\d{1,4})$~', $token, $m)) {
			$size = (int) $m[1];
			$best = self::PIXEL_SIZES[0];

			foreach (self::PIXEL_SIZES as $candidate) {
				if (\abs($candidate - $size) <= \abs($best - $size)) {
					$best = $candidate;
				}
			}

			return 'pixel' . $best;
		}

		return ImageTokens::canonical($token)[0] ?? null;
	}

	/**
	 * A part of the image, in thousandths snapped to 5, kept inside it; null when it has no area.
	 */
	private static function area(int $x, int $y, int $w, int $h): ?string
	{
		$x = self::snap($x, 5, 0, 995);
		$y = self::snap($y, 5, 0, 995);
		$w = self::snap($w, 5, 5, 1000 - $x);
		$h = self::snap($h, 5, 5, 1000 - $y);

		// The whole image: no token.
		if (0 === $x && 0 === $y && 1000 === $w && 1000 === $h) {
			return null;
		}

		return "area{$x}x{$y}x{$w}x{$h}";
	}

	private static function snap(int $value, int $step, int $min, int $max): int
	{
		return (int) \max($min, \min($max, $step * \round($value / $step)));
	}
}
