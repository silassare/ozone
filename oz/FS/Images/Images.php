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
use OZONE\Core\FS\Enums\ImageDriverName;
use OZONE\Core\FS\Images\Interfaces\ImageProcessorInterface;

/**
 * The image processor of this process: built once, with the driver `oz.files` `OZ_IMAGE_DRIVER`
 * names (the first the server has, by default). A process-wide value, not a request's: what a
 * server has does not change between requests.
 */
final class Images
{
	private static ?ImageProcessorInterface $processor = null;

	public static function processor(): ImageProcessorInterface
	{
		if (null === self::$processor) {
			$name = (string) Settings::get('oz.files', 'OZ_IMAGE_DRIVER', ImageDriverName::AUTO->value);

			self::$processor = new InterventionImageProcessor(
				ImageDriverName::tryFrom($name) ?? ImageDriverName::AUTO
			);
		}

		return self::$processor;
	}

	/**
	 * Uses this processor from now on: another driver, or another library behind the interface.
	 */
	public static function use(ImageProcessorInterface $processor): void
	{
		self::$processor = $processor;
	}
}
