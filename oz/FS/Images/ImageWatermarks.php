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

use OZONE\Core\App\Settings;
use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\Enums\FileKind;

/**
 * Watermarks, declared by name in `oz.files` `OZ_IMAGE_WATERMARKS`, drawn by the token `wm{name}`;
 * and the ones a project forces on some images (`OZ_IMAGE_WATERMARK_POLICY`, or the file's own
 * `watermark` data), on every rendition and on the image's plain URL, so dropping the token from a
 * URL does not give the image without it.
 */
final class ImageWatermarks
{
	/** Where a watermark may sit, as `Intervention\Image\Alignment` names the places. */
	public const POSITIONS = [
		'top-left', 'top', 'top-right', 'left', 'center', 'right', 'bottom-left', 'bottom', 'bottom-right',
	];

	/**
	 * A declared watermark, its values bounded; null when there is none by that name.
	 *
	 * @return null|array{path: string, position: string, opacity: float, width: int, margin: int}
	 */
	public static function get(string $name): ?array
	{
		$all  = (array) Settings::get('oz.files', 'OZ_IMAGE_WATERMARKS', []);
		$spec = $all[$name] ?? null;

		if (!\is_array($spec) || !\is_string($spec['path'] ?? null) || '' === $spec['path']) {
			return null;
		}

		$position = (string) ($spec['position'] ?? 'bottom-right');

		return [
			'path'     => $spec['path'],
			'position' => \in_array($position, self::POSITIONS, true) ? $position : 'bottom-right',
			'opacity'  => \max(0.0, \min(1.0, (float) ($spec['opacity'] ?? 0.5))),
			'width'    => \max(1, \min(100, (int) ($spec['width'] ?? 20))),
			'margin'   => \max(0, \min(50, (int) ($spec['margin'] ?? 2))),
		];
	}

	/**
	 * The watermark an image must carry wherever it is served: its own (`watermark` in its data),
	 * else the policy's for its label; null when it is free to be served without one.
	 */
	public static function enforcedFor(OZFile $file): ?string
	{
		if (FileKind::IMAGE !== FileKind::fromMime($file->getMime())) {
			return null;
		}

		$own = $file->getData()['watermark'] ?? null;

		if (\is_string($own) && null !== self::get($own)) {
			return $own;
		}

		$label = $file->getForLabel();

		foreach ((array) Settings::get('oz.files', 'OZ_IMAGE_WATERMARK_POLICY', []) as $rule) {
			if (
				\is_array($rule)
				&& ($rule['for_label'] ?? null) === $label
				&& \is_string($rule['watermark'] ?? null)
				&& null !== self::get($rule['watermark'])
			) {
				return $rule['watermark'];
			}
		}

		return null;
	}
}
