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

/**
 * Class ImageFilterTokens.
 *
 * Reduces the filter tokens of an image URL to one canonical list, before anything is rendered or
 * cached: a URL cannot make the server render, or keep on disk, an unbounded number of renditions.
 *
 * - a size (`w{N}`, `h{N}`, `thumb{N}`) is snapped to the nearest of `OZ_IMAGE_FILTERS_SIZES`, the
 *   larger on a tie;
 * - a quality (`q{N}`) is snapped to a step of 5, from 5 to 100;
 * - a blur (`blur{N}`) is clamped to `OZ_IMAGE_FILTERS_MAX_BLUR` passes;
 * - an unknown token is dropped, a size or a quality given twice keeps its last value, an effect
 *   given twice counts once;
 * - at most `OZ_IMAGE_FILTERS_MAX_TOKENS` tokens are kept, sizes first.
 *
 * So `w100`, `w96` and `w96~junk` are one rendition, and the renditions of an image are bounded by
 * the sizes, qualities and effects there are.
 */
final class ImageFilterTokens
{
	/** Effects applied in the order given, each once. */
	private const EFFECTS = ['grayscale', 'sepia', 'sharpen'];

	/**
	 * The canonical tokens: `w`, `h`, `thumb` / `thumb{N}`, `crop` / `nocrop`, `q`, then the effects in
	 * the order first given.
	 *
	 * @param string[] $tokens
	 *
	 * @return list<string>
	 */
	public static function normalize(array $tokens): array
	{
		$sizes   = [];
		$effects = [];

		foreach ($tokens as $token) {
			if (\preg_match('~^(w|h|thumb)(\d{1,6})$~', $token, $m)) {
				$sizes[$m[1]] = $m[1] . self::snapSize((int) $m[2]);
			} elseif ('thumb' === $token) {
				$sizes['thumb'] = 'thumb';
			} elseif ('crop' === $token || 'nocrop' === $token) {
				$sizes['crop'] = $token;
			} elseif (\preg_match('~^q(\d{1,3})$~', $token, $m)) {
				$sizes['q'] = 'q' . self::snapQuality((int) $m[1]);
			} elseif ('blur' === $token || \preg_match('~^blur(\d{1,6})$~', $token)) {
				$passes = 'blur' === $token ? 1 : (int) \substr($token, 4);
				$max    = \max(1, (int) Settings::get('oz.files', 'OZ_IMAGE_FILTERS_MAX_BLUR', 10));
				$blur   = 'blur' . \max(1, \min($max, $passes));

				if (!\in_array($blur, $effects, true)) {
					$effects[] = $blur;
				}
			} elseif (\in_array($token, self::EFFECTS, true) && !\in_array($token, $effects, true)) {
				$effects[] = $token;
			}
			// Anything else is dropped: it would only make another cache key for the same image.
		}

		$ordered = [];

		foreach (['w', 'h', 'thumb', 'crop', 'q'] as $name) {
			if (isset($sizes[$name])) {
				$ordered[] = $sizes[$name];
			}
		}

		$max = \max(1, (int) Settings::get('oz.files', 'OZ_IMAGE_FILTERS_MAX_TOKENS', 8));

		return \array_slice([...$ordered, ...$effects], 0, $max);
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
		return (int) \max(5, \min(100, 5 * \round($quality / 5)));
	}
}
