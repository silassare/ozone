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

namespace OZONE\Core\FS\Enums;

/**
 * Class FileKind.
 */
enum FileKind: string
{
	case IMAGE    = 'image';
	case AUDIO    = 'audio';
	case VIDEO    = 'video';
	case DOCUMENT = 'document';
	case JS       = 'js';
	case CSS      = 'css';
	case FONT     = 'font';
	case OTHER    = 'other';

	/**
	 * Gets the file kind from a given mime type.
	 *
	 * @param string $mime the mime type
	 *
	 * @return FileKind
	 */
	public static function fromMime(string $mime): FileKind
	{
		$mime = \strtolower($mime);

		if (\str_starts_with($mime, 'image/')) {
			return self::IMAGE;
		}

		if (\str_starts_with($mime, 'audio/')) {
			return self::AUDIO;
		}

		if (\str_starts_with($mime, 'video/')) {
			return self::VIDEO;
		}

		if (\str_starts_with($mime, 'text/')) {
			return self::DOCUMENT;
		}
		if (
			\str_starts_with($mime, 'application/pdf')
			|| \str_starts_with($mime, 'application/msword')
			|| \str_starts_with($mime, 'application/vnd.ms-excel')
			|| \str_starts_with($mime, 'application/vnd.ms-powerpoint')
			|| \str_starts_with($mime, 'application/vnd.openxmlformats-officedocument')
			|| \str_starts_with($mime, 'application/vnd.oasis.opendocument')
		) {
			return self::DOCUMENT;
		}

		if (\str_starts_with($mime, 'text/javascript') || \str_starts_with($mime, 'application/javascript')) {
			return self::JS;
		}

		if (\str_starts_with($mime, 'text/css')) {
			return self::CSS;
		}

		if (\str_starts_with($mime, 'font/') || \str_starts_with($mime, 'application/font-')) {
			return self::FONT;
		}

		return self::OTHER;
	}
}
