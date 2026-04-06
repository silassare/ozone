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

namespace OZONE\Tests\Services;

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Services\QRCode\BuiltinQRCodeEncoderDecoder;
use PHPUnit\Framework\TestCase;

/**
 * Class QRCodeEncoderDecoderTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class QRCodeEncoderDecoderTest extends TestCase
{
	private BuiltinQRCodeEncoderDecoder $qr;

	protected function setUp(): void
	{
		$this->qr = BuiltinQRCodeEncoderDecoder::instance();
	}

	public function testInstanceReturnsInstance(): void
	{
		$a = BuiltinQRCodeEncoderDecoder::instance();

		self::assertInstanceOf(BuiltinQRCodeEncoderDecoder::class, $a);
	}

	public function testEncodeReturnsValidPng(): void
	{
		$png = $this->qr->encode('Hello, OZone!');

		// PNG signature: \x89PNG\r\n\x1a\n
		self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
	}

	public function testEncodeProducesConsistentOutput(): void
	{
		$data = 'deterministic test';
		$png1 = $this->qr->encode($data);
		$png2 = $this->qr->encode($data);

		self::assertSame($png1, $png2);
	}

	/**
	 * @dataProvider roundTripProvider
	 */
	public function testRoundTrip(string $data): void
	{
		$png     = $this->qr->encode($data);
		$decoded = $this->qr->decode($png);

		self::assertSame($data, $decoded);
	}

	public function testSizeParamAffectsImageDimensions(): void
	{
		$data   = 'size-test';
		$small  = $this->qr->encode($data, 3);
		$large  = $this->qr->encode($data, 8);

		$imgSmall = \imagecreatefromstring($small);
		$imgLarge = \imagecreatefromstring($large);

		self::assertNotFalse($imgSmall);
		self::assertNotFalse($imgLarge);

		self::assertLessThan(\imagesx($imgLarge), \imagesx($imgSmall));
	}

	public function testMarginParamAffectsImageDimensions(): void
	{
		$data      = 'margin-test';
		$noMargin  = $this->qr->encode($data, 6, 0);
		$bigMargin = $this->qr->encode($data, 6, 10);

		$imgNoMargin  = \imagecreatefromstring($noMargin);
		$imgBigMargin = \imagecreatefromstring($bigMargin);

		self::assertNotFalse($imgNoMargin);
		self::assertNotFalse($imgBigMargin);

		self::assertLessThan(\imagesx($imgBigMargin), \imagesx($imgNoMargin));
	}

	public function testDecodeAcceptsJpeg(): void
	{
		$data = 'Hello, OZone!';
		// Encode to PNG first, then convert to JPEG via GD
		$png = $this->qr->encode($data, 8);
		$img = \imagecreatefromstring($png);
		self::assertNotFalse($img);

		\ob_start();
		\imagejpeg($img, null, 95);
		$jpeg = \ob_get_clean();
		self::assertNotFalse($jpeg);
		// JPEG SOI marker
		self::assertStringStartsWith("\xFF\xD8\xFF", (string) $jpeg);

		$decoded = $this->qr->decode((string) $jpeg);
		self::assertSame($data, $decoded);
	}

	/**
	 * @dataProvider roundTripProvider
	 */
	public function testRoundTripViaJpeg(string $data): void
	{
		$png = $this->qr->encode($data, 8);
		$img = \imagecreatefromstring($png);
		self::assertNotFalse($img);

		\ob_start();
		\imagejpeg($img, null, 95);
		$jpeg = (string) \ob_get_clean();

		$decoded = $this->qr->decode($jpeg);
		self::assertSame($data, $decoded);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function roundTripProvider(): iterable
	{
		return [
			'short ascii'      => ['Hi'],
			'single char'      => ['A'],
			'digit'            => ['0'],
			'hello world'      => ['Hello, World!'],
			'ozone framework'  => ['Hello, OZone!'],
			'url'              => ['https://example.com/path?foo=bar&baz=qux'],
			'email'            => ['user@example.com'],
			'alphanumeric mix' => ['abc123XYZ!@#'],
			'spaces inside'    => ['foo bar baz'],
			'long string'      => [\str_repeat('abcdefghij', 10)],
		];
	}

	public function testDecodeThrowsOnEmptyInput(): void
	{
		$this->expectException(RuntimeException::class);

		$this->qr->decode('');
	}

	public function testDecodeThrowsOnGarbageInput(): void
	{
		$this->expectException(RuntimeException::class);

		$this->qr->decode('not-a-png-at-all');
	}

	public function testDecodeThrowsOnValidPngThatIsNotQrCode(): void
	{
		// A minimal 1x1 white PNG (not a QR symbol)
		$onePixelPng = \base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==',
			true
		);

		$this->expectException(RuntimeException::class);

		$this->qr->decode((string) $onePixelPng);
	}

	public function testDecodeThrowsOnValidJpegThatIsNotQrCode(): void
	{
		// A 1x1 white JPEG (not a QR symbol)
		$img = \imagecreatetruecolor(1, 1);
		\imagefilledrectangle($img, 0, 0, 0, 0, \imagecolorallocate($img, 255, 255, 255));

		\ob_start();
		\imagejpeg($img);
		$jpeg = (string) \ob_get_clean();

		$this->expectException(RuntimeException::class);

		$this->qr->decode($jpeg);
	}

	public function testRoundTripVersionOne(): void
	{
		// Version 1 supports up to 14 bytes in byte mode with EC level M
		$data    = 'Hello, OZone!';  // 13 bytes
		$png     = $this->qr->encode($data);
		$decoded = $this->qr->decode($png);

		self::assertSame($data, $decoded);
	}

	public function testRoundTripVersionTwo(): void
	{
		// Version 2 supports up to 26 bytes with EC level M
		$data    = 'This is a v2 QR code test!';  // 26 bytes
		$png     = $this->qr->encode($data);
		$decoded = $this->qr->decode($png);

		self::assertSame($data, $decoded);
	}

	public function testRoundTripVersionThree(): void
	{
		// Version 3 supports up to 42 bytes with EC level M
		$data    = \str_repeat('abcde', 8); // 40 bytes
		$png     = $this->qr->encode($data);
		$decoded = $this->qr->decode($png);

		self::assertSame($data, $decoded);
	}
}
