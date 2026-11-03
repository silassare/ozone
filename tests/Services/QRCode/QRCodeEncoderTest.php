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

namespace OZONE\Tests\Services\QRCode;

use Override;
use OZONE\Core\Services\QRCode\BuiltinQRCodeEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * What the encoder draws, read back by something that is not the encoder.
 *
 * The suite this replaces asserted `encode()` and then `decode()` through the same class, so a
 * symbol whose error-correction codewords were wrong in fifteen of sixteen positions passed every
 * test for the encoder's whole life while no phone, no browser and no online decoder could read it.
 * A standard decoder recomputes the error correction before it reads the data, so that is what
 * {@see QRSymbolReader} does here, from the Galois field up and with no table taken from the class
 * under test.
 *
 * @internal
 */
#[CoversClass(BuiltinQRCodeEncoder::class)]
final class QRCodeEncoderTest extends TestCase
{
	private const string BADGE = 'MEB1.2.ABCDEFGH.AB12CD34EF';

	private BuiltinQRCodeEncoder $encoder;

	private QRSymbolReader $reader;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->encoder = BuiltinQRCodeEncoder::instance();
		$this->reader  = new QRSymbolReader();
	}

	public function testTheErrorCorrectionCodewordsAreValid(): void
	{
		// The whole bug: the symbol is structurally perfect and carries error-correction codewords
		// no decoder can verify, so every decoder refuses it before reading the data.
		[$version, $level, $mask, $blocks, $ec, $payload] = $this->read(self::BADGE);

		self::assertSame('M', $level);
		self::assertSame(self::BADGE, $payload);

		foreach ($blocks as $i => $block) {
			self::assertSame(
				$this->reader->errorCorrection($block, \count($ec[$i])),
				$ec[$i],
				\sprintf('Block %d of a version %d symbol carries wrong error correction.', $i, $version)
			);
		}

		self::assertGreaterThanOrEqual(0, $mask);
	}

	/**
	 * Every version, because the block layout was wrong in fourteen of the forty rows.
	 */
	#[DataProvider('versions')]
	public function testEveryVersionIsReadBackWithValidErrorCorrection(int $version): void
	{
		$payload = $this->payloadFillingVersion($version);

		[$read_version, $level, , $blocks, $ec, $decoded] = $this->read($payload);

		self::assertSame($version, $read_version, 'The encoder chose another version for this length.');
		self::assertSame('M', $level);
		self::assertSame($payload, $decoded, 'The data did not read back, so the blocks are split wrong.');

		foreach ($blocks as $i => $block) {
			self::assertSame(
				$this->reader->errorCorrection($block, \count($ec[$i])),
				$ec[$i],
				\sprintf('Version %d block %d carries wrong error correction.', $version, $i)
			);
		}
	}

	/**
	 * @return iterable<string, array{0: int}>
	 */
	public static function versions(): iterable
	{
		for ($v = 1; $v <= 40; ++$v) {
			yield 'version ' . $v => [$v];
		}
	}

	public function testTheSymbolIsTheOneRecorded(): void
	{
		// A fixture, so a change in what the encoder draws has to be deliberate. Recorded from a
		// symbol the test above accepts: valid format information, error correction that recomputes,
		// and a payload that reads back. It is not compared against another encoder's symbol -- two
		// conforming encoders draw different modules for the same text, because the segment mode and
		// the mask each one picks are its own choice.
		$fixture = __DIR__ . \DIRECTORY_SEPARATOR . 'fixtures' . \DIRECTORY_SEPARATOR . 'badge-v2-m.matrix';

		self::assertFileExists($fixture, 'The recorded symbol is missing.');

		self::assertSame(
			\trim((string) \file_get_contents($fixture)),
			\trim($this->matrixText(self::BADGE)),
			'The encoder no longer draws the recorded symbol.'
		);
	}

	public function testAQuietZoneAndAModuleSizeAreHonoured(): void
	{
		$png = $this->encoder->encode(self::BADGE, 4, 3);
		$img = \imagecreatefromstring($png);

		self::assertNotFalse($img);

		// Version 2 is 25 modules, plus three modules of quiet zone each side, at four pixels.
		self::assertSame((25 + 6) * 4, \imagesx($img));
		self::assertSame((25 + 6) * 4, \imagesy($img));
	}

	/**
	 * The payload, the version, and the blocks as a decoder recovers them.
	 *
	 * @return array{0: int, 1: string, 2: int, 3: int[][], 4: int[][], 5: null|string}
	 */
	private function read(string $data): array
	{
		$matrix = $this->reader->matrixFromPng($this->encoder->encode($data, 1, 0));
		$n      = \count($matrix);
		$version = \intdiv($n - 17, 4);

		$format = $this->reader->formatInfo($matrix);

		self::assertNotNull($format, 'The format information is not one of the valid values.');

		[$level, $mask] = $format;

		$codewords = $this->reader->codewords($matrix, $version, $mask);
		[$sizes, $ec_per_block] = $this->blockLayout($version);
		[$blocks, $ec] = $this->reader->deinterleave($codewords, $sizes, $ec_per_block);

		$flat = [];

		foreach ($blocks as $block) {
			foreach ($block as $cw) {
				$flat[] = $cw;
			}
		}

		return [$version, $level, $mask, $blocks, $ec, $this->reader->byteModePayload($flat, $version)];
	}

	/**
	 * The block layout the encoder claims for a version. Read from the class because it is what is
	 * under test: a wrong layout fails to deinterleave, so the error correction does not verify and
	 * the payload does not read back.
	 *
	 * @return array{0: int[], 1: int}
	 */
	private function blockLayout(int $version): array
	{
		/** @var array<int, int[]> $table */
		$table = new ReflectionClass(BuiltinQRCodeEncoder::class)->getConstant('EC_M');
		[, $ec_per_block, $b1, $cw1, $b2, $cw2] = $table[$version];

		$sizes = \array_merge(\array_fill(0, $b1, $cw1), 0 === $b2 ? [] : \array_fill(0, $b2, $cw2));

		return [$sizes, $ec_per_block];
	}

	/**
	 * The longest payload that still fits a version, so the encoder cannot pick a smaller one.
	 */
	private function payloadFillingVersion(int $version): string
	{
		/** @var array<int, int[]> $table */
		$table    = new ReflectionClass(BuiltinQRCodeEncoder::class)->getConstant('EC_M');
		$cc_bits  = $version <= 9 ? 8 : 16;
		$capacity = \intdiv(($table[$version][0] * 8) - 8 - $cc_bits, 8);

		return \substr(\str_repeat('OZONE-QR-0123456789-', 200), 0, $capacity);
	}

	private function matrixText(string $data): string
	{
		$out = '';

		foreach ($this->reader->matrixFromPng($this->encoder->encode($data, 1, 0)) as $row) {
			$out .= \implode('', $row) . "\n";
		}

		return $out;
	}
}
