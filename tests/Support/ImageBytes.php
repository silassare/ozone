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

namespace OZONE\Tests\Support;

use RuntimeException;

/**
 * Images made for tests, with GD: plain ones, ones with metadata (EXIF with a camera and an
 * orientation, PNG text), and the readings a test compares.
 */
final class ImageBytes
{
	public static function png(int $width, int $height): string
	{
		$img = \imagecreatetruecolor($width, $height);
		\ob_start();
		\imagepng($img);

		return (string) \ob_get_clean();
	}

	public static function jpeg(int $width, int $height): string
	{
		$img = \imagecreatetruecolor($width, $height);
		\ob_start();
		\imagejpeg($img);

		return (string) \ob_get_clean();
	}

	/**
	 * A TIFF block of EXIF: the camera's make (`OZoneCamera`) and an orientation.
	 */
	public static function tiff(int $orientation): string
	{
		$make = "OZoneCamera\0";

		return 'II' . \pack('vV', 42, 8)
			. \pack('v', 2)
			// Make: ASCII, at the data area after the IFD (8 + 2 + 2 * 12 + 4 = 38).
			. \pack('vvVV', 0x010F, 2, \strlen($make), 38)
			// Orientation: one SHORT, in the value field.
			. \pack('vvVvv', 0x0112, 3, 1, $orientation, 0)
			. \pack('V', 0)
			. $make;
	}

	/** A JPEG with an EXIF segment (APP1) right after its start. */
	public static function withExif(string $jpeg, int $orientation): string
	{
		$payload = "Exif\0\0" . self::tiff($orientation);

		return \substr($jpeg, 0, 2) . "\xFF\xE1" . \pack('n', \strlen($payload) + 2) . $payload . \substr($jpeg, 2);
	}

	/** A PNG with an EXIF chunk and a comment, before its end. */
	public static function pngWithMetadata(int $width, int $height): string
	{
		$png   = self::png($width, $height);
		$chunk = static fn (string $type, string $data): string => \pack('N', \strlen($data)) . $type . $data
			. \pack('N', \crc32($type . $data));
		$end   = \strrpos($png, 'IEND') - 4;

		return \substr($png, 0, $end)
			. $chunk('eXIf', self::tiff(1))
			. $chunk('tEXt', "Comment\0OZoneSecret")
			. \substr($png, $end);
	}

	/** An image of one color. */
	public static function filled(int $width, int $height, int $r, int $g, int $b): string
	{
		$img = \imagecreatetruecolor($width, $height);
		\imagefill($img, 0, 0, (int) \imagecolorallocate($img, $r, $g, $b));
		\ob_start();
		\imagepng($img);

		return (string) \ob_get_clean();
	}

	/** Its left half red, its right half blue. */
	public static function halves(int $width, int $height): string
	{
		$img = \imagecreatetruecolor($width, $height);
		\imagefilledrectangle($img, 0, 0, \intdiv($width, 2) - 1, $height - 1, (int) \imagecolorallocate($img, 255, 0, 0));
		\imagefilledrectangle($img, \intdiv($width, 2), 0, $width - 1, $height - 1, (int) \imagecolorallocate($img, 0, 0, 255));
		\ob_start();
		\imagepng($img);

		return (string) \ob_get_clean();
	}

	/**
	 * A pixel's red, green and blue.
	 *
	 * @return array{int, int, int}
	 */
	public static function rgbAt(string $bytes, int $x, int $y): array
	{
		$img = \imagecreatefromstring($bytes);

		if (false === $img) {
			throw new RuntimeException('A valid image is expected.');
		}

		$rgb = \imagecolorsforindex($img, (int) \imagecolorat($img, $x, $y));

		return [$rgb['red'], $rgb['green'], $rgb['blue']];
	}

	/** Which of red and blue a pixel is closest to. */
	public static function colorAt(string $bytes, int $x, int $y): string
	{
		[$r, , $b] = self::rgbAt($bytes, $x, $y);

		return $r > $b ? 'red' : 'blue';
	}

	/**
	 * [width, height] of an image's bytes.
	 *
	 * @return array{int, int}
	 */
	public static function size(string $bytes): array
	{
		$info = \getimagesizefromstring($bytes);

		if (false === $info) {
			throw new RuntimeException('A valid image is expected.');
		}

		return [$info[0], $info[1]];
	}
}
