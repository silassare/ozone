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
use OZONE\Core\FS\FS;
use Throwable;

/**
 * What a new image file keeps in its data (`image`: width, height, dominant color), read once when
 * it is stored, so a page reserves its place and shows its color before it loads.
 */
final class ImageProbe
{
	/** The key of a file's data that holds it. */
	public const DATA_KEY = 'image';

	/**
	 * Reads an image file's facts from its storage; null for a file that is not an image, larger
	 * than `OZ_IMAGE_PROBE_MAX_SIZE`, or that does not decode (logged: an upload never fails on it).
	 */
	public static function of(OZFile $file): ?ImageInfo
	{
		if (FileKind::IMAGE !== FileKind::fromMime($file->getMime())) {
			return null;
		}

		$max = (int) Settings::get('oz.files', 'OZ_IMAGE_PROBE_MAX_SIZE', 30 * 1000 * 1000);

		if ($max > 0 && $file->getSize() > $max) {
			return null;
		}

		try {
			$bytes = FS::getStorage($file->getStorage())->getStream($file)->getContents();

			return Images::processor()->probe($bytes);
		} catch (Throwable $t) {
			oz_logger()->warning('An image could not be probed: its size and color are not stored.', [
				'_file_ref'  => $file->getRef(),
				'_exception' => $t->getMessage(),
			]);

			return null;
		}
	}
}
