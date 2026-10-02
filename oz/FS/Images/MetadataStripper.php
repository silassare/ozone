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
 * Removes an image's metadata (location, camera, comments) without touching its pixels: the blocks
 * that hold it are dropped byte by byte, so the image keeps its quality and its size. JPEG and PNG
 * only; anything else, and a JPEG whose EXIF turns it (dropping that would show it sideways), is
 * left to a re-encode.
 */
final class MetadataStripper
{
	/** The PNG chunks that hold text, EXIF or a timestamp. */
	private const PNG_DROPPED = ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'];

	/**
	 * The image without its metadata; null when it cannot be done losslessly (another format, a
	 * turned JPEG, an image this does not read whole).
	 */
	public static function strip(string $bytes, string $mime): ?string
	{
		return match ($mime) {
			'image/jpeg' => self::jpeg($bytes),
			'image/png'  => self::png($bytes),
			default      => null,
		};
	}

	/**
	 * Keeps the JFIF header (APP0), the color profile (APP2 `ICC_PROFILE`) and Adobe's color
	 * transform (APP14), and the image itself from its start of scan on; drops EXIF and XMP (APP1),
	 * IPTC (APP13), the other application blocks and comments.
	 */
	private static function jpeg(string $bytes): ?string
	{
		$length = \strlen($bytes);

		if ($length < 4 || "\xFF\xD8" !== \substr($bytes, 0, 2)) {
			return null;
		}

		$out = "\xFF\xD8";
		$at  = 2;

		while ($at + 4 <= $length) {
			if ("\xFF" !== $bytes[$at]) {
				return null;
			}

			$marker = \ord($bytes[$at + 1]);

			// A fill byte before a marker.
			if (0xFF === $marker) {
				++$at;

				continue;
			}

			// Start of scan: the compressed image, and everything after it, kept as it is.
			if (0xDA === $marker) {
				return $out . \substr($bytes, $at);
			}

			$size = \unpack('n', \substr($bytes, $at + 2, 2))[1] ?? 0;

			if ($size < 2 || $at + 2 + $size > $length) {
				return null;
			}

			$segment = \substr($bytes, $at, 2 + $size);
			$payload = \substr($segment, 4);

			if (0xE1 === $marker && \str_starts_with($payload, "Exif\0\0") && 1 !== self::orientation($payload)) {
				return null;
			}

			$keep = match (true) {
				0xE0 === $marker                                        => true,
				0xE2 === $marker                                        => \str_starts_with($payload, "ICC_PROFILE\0"),
				0xEE === $marker                                        => true,
				($marker >= 0xE0 && $marker <= 0xEF) || 0xFE === $marker => false,
				default                                                 => true,
			};

			if ($keep) {
				$out .= $segment;
			}

			$at += 2 + $size;
		}

		return null;
	}

	/**
	 * The orientation an EXIF block gives (1: as stored), 1 when it gives none.
	 */
	private static function orientation(string $exif): int
	{
		$tiff = \substr($exif, 6);
		$le   = \str_starts_with($tiff, 'II');

		if (!$le && !\str_starts_with($tiff, 'MM')) {
			return 1;
		}

		$u16 = static fn (int $at): int => (int) (\unpack($le ? 'v' : 'n', \substr($tiff, $at, 2))[1] ?? 0);
		$u32 = static fn (int $at): int => (int) (\unpack($le ? 'V' : 'N', \substr($tiff, $at, 4))[1] ?? 0);

		$ifd   = $u32(4);
		$count = $u16($ifd);

		for ($i = 0; $i < $count; ++$i) {
			$entry = $ifd + 2 + 12 * $i;

			if ($entry + 12 > \strlen($tiff)) {
				break;
			}

			if (0x0112 === $u16($entry)) {
				return $u16($entry + 8);
			}
		}

		return 1;
	}

	/**
	 * Keeps every chunk but text, EXIF and the timestamp.
	 */
	private static function png(string $bytes): ?string
	{
		$signature = "\x89PNG\r\n\x1A\n";

		if (!\str_starts_with($bytes, $signature)) {
			return null;
		}

		$length = \strlen($bytes);
		$out    = $signature;
		$at     = 8;

		while ($at + 12 <= $length) {
			$size = \unpack('N', \substr($bytes, $at, 4))[1] ?? 0;
			$type = \substr($bytes, $at + 4, 4);

			if ($at + 12 + $size > $length) {
				return null;
			}

			if (!\in_array($type, self::PNG_DROPPED, true)) {
				$out .= \substr($bytes, $at, 12 + $size);
			}

			$at += 12 + $size;

			if ('IEND' === $type) {
				return $out;
			}
		}

		return null;
	}
}
