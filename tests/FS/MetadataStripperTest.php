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

namespace OZONE\Tests\FS;

use OZONE\Core\FS\Images\MetadataStripper;
use OZONE\Tests\Support\ImageBytes;
use PHPUnit\Framework\TestCase;

/**
 * Class MetadataStripperTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\FS\Images\MetadataStripper
 */
final class MetadataStripperTest extends TestCase
{
	public function testAJpegLosesItsMetadataAndKeepsItsPixelsAndColorProfile(): void
	{
		$plain = ImageBytes::jpeg(64, 48);
		$jpeg  = self::insertAfterStart(
			ImageBytes::withExif($plain, 1),
			self::segment(0xE2, "ICC_PROFILE\0\x01\x01profile")
			. self::segment(0xED, "Photoshop 3.0\0IPTC OZoneSecret")
			. self::segment(0xFE, 'OZoneComment')
		);

		$out = MetadataStripper::strip($jpeg, 'image/jpeg');

		self::assertNotNull($out);

		foreach (['OZoneCamera', 'OZoneSecret', 'OZoneComment', "Exif\0\0"] as $gone) {
			self::assertStringNotContainsString($gone, $out);
		}

		self::assertStringContainsString("ICC_PROFILE\0", $out);
		self::assertSame([64, 48], ImageBytes::size($out));
		// The compressed image, from its start of scan, byte for byte.
		self::assertSame(self::scan($plain), self::scan($out));
	}

	public function testATurnedJpegIsLeftToARencodeThatTurnsIt(): void
	{
		self::assertNull(MetadataStripper::strip(ImageBytes::withExif(ImageBytes::jpeg(20, 10), 6), 'image/jpeg'));
	}

	public function testAPngLosesItsTextAndExifChunksAndKeepsItsImage(): void
	{
		$png = ImageBytes::pngWithMetadata(30, 20);
		$out = MetadataStripper::strip($png, 'image/png');

		self::assertNotNull($out);
		self::assertStringNotContainsString('OZoneSecret', $out);
		self::assertStringNotContainsString('OZoneCamera', $out);
		self::assertStringNotContainsString('eXIf', $out);
		self::assertSame([30, 20], ImageBytes::size($out));
		self::assertSame(ImageBytes::png(30, 20), $out);
	}

	public function testWhatItDoesNotReadWholeIsLeftToARencode(): void
	{
		self::assertNull(MetadataStripper::strip(ImageBytes::png(10, 10), 'image/webp'));
		self::assertNull(MetadataStripper::strip(\substr(ImageBytes::jpeg(10, 10), 0, 30), 'image/jpeg'));
		self::assertNull(MetadataStripper::strip('not an image', 'image/png'));
	}

	private static function segment(int $marker, string $payload): string
	{
		return "\xFF" . \chr($marker) . \pack('n', \strlen($payload) + 2) . $payload;
	}

	private static function insertAfterStart(string $jpeg, string $segments): string
	{
		return \substr($jpeg, 0, 2) . $segments . \substr($jpeg, 2);
	}

	/** What a JPEG holds from its start of scan on. */
	private static function scan(string $jpeg): string
	{
		return \substr($jpeg, (int) \strpos($jpeg, "\xFF\xDA"));
	}
}
