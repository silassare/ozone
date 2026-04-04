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

namespace OZONE\Core\Services\QRCode;

use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Services\QRCode\Interfaces\QRCodeEncoderDecoderInterface;

/**
 * Class BuiltinQRCodeEncoderDecoder.
 *
 * Pure-PHP QR Code encoder (no external dependencies, no GD tricks beyond pixel drawing).
 *
 * Supports:
 *  - Mode: Byte (UTF-8 / arbitrary binary data)
 *  - Error correction: M (15 % recovery capacity, a good default)
 *  - Versions 1-40 auto-selected by data length
 *  - All 8 mask patterns evaluated; best (lowest penalty) applied
 *  - Output: raw PNG binary via GD (ext-gd, already required by OZone)
 *
 * The QR Code specification is ISO/IEC 18004:2015.
 */
final class BuiltinQRCodeEncoderDecoder implements QRCodeEncoderDecoderInterface
{
	// Error-correction level M codewords per version (1..40):
	// [data codewords, ec codewords per block, blocks in group 1, codewords in group 1 blocks,
	//  blocks in group 2, codewords in group 2 blocks]
	// Source: ISO 18004 Table 9
	private const EC_M = [
		1  => [16, 10, 1, 16, 0, 0],
		2  => [28, 16, 1, 28, 0, 0],
		3  => [44, 26, 2, 22, 0, 0],
		4  => [64, 18, 4, 16, 0, 0],
		5  => [86, 24, 2, 21, 2, 22],
		6  => [108, 16, 4, 27, 0, 0],
		7  => [124, 18, 4, 31, 0, 0],
		8  => [154, 22, 2, 38, 2, 39],
		9  => [182, 22, 3, 36, 2, 37],
		10 => [216, 26, 4, 43, 1, 44],
		11 => [254, 30, 1, 50, 4, 51],
		12 => [290, 22, 6, 36, 2, 37],
		13 => [334, 22, 8, 37, 1, 38],
		14 => [365, 24, 4, 40, 5, 41],
		15 => [415, 24, 5, 41, 5, 42],
		16 => [453, 28, 7, 45, 3, 46],
		17 => [507, 28, 10, 46, 1, 47],
		18 => [563, 26, 9, 43, 4, 44],
		19 => [627, 26, 3, 44, 11, 45],
		20 => [669, 26, 3, 41, 13, 42],
		21 => [714, 26, 17, 42, 0, 0],
		22 => [782, 28, 17, 46, 0, 0],
		23 => [860, 28, 4, 47, 14, 48],
		24 => [914, 28, 6, 45, 14, 46],
		25 => [1000, 28, 8, 45, 13, 46],
		26 => [1062, 28, 19, 46, 4, 47],
		27 => [1128, 28, 22, 45, 3, 46],
		28 => [1193, 28, 3, 45, 23, 46],
		29 => [1267, 28, 21, 45, 7, 46],
		30 => [1373, 28, 19, 45, 10, 46],
		31 => [1455, 28, 2, 45, 29, 46],
		32 => [1541, 28, 10, 45, 23, 46],
		33 => [1631, 28, 14, 45, 21, 46],
		34 => [1725, 28, 14, 46, 23, 47],
		35 => [1812, 28, 12, 45, 26, 46],
		36 => [1914, 28, 6, 45, 34, 46],
		37 => [1992, 28, 29, 45, 14, 46],
		38 => [2102, 28, 13, 45, 32, 46],
		39 => [2216, 28, 40, 45, 7, 46],
		40 => [2334, 28, 18, 45, 31, 46],
	];

	// GF(256) log table (generator polynomial x^8 + x^4 + x^3 + x^2 + 1, 0x11d)
	private const GF_LOG = [
		0,
		0,
		1,
		25,
		2,
		50,
		26,
		198,
		3,
		223,
		51,
		238,
		27,
		104,
		199,
		75,
		4,
		100,
		224,
		14,
		52,
		141,
		239,
		129,
		28,
		193,
		105,
		248,
		200,
		8,
		76,
		113,
		5,
		138,
		101,
		47,
		225,
		36,
		15,
		33,
		53,
		147,
		142,
		218,
		240,
		18,
		130,
		69,
		29,
		181,
		194,
		125,
		106,
		39,
		249,
		185,
		201,
		154,
		9,
		120,
		77,
		228,
		114,
		166,
		6,
		191,
		139,
		98,
		102,
		221,
		48,
		253,
		226,
		152,
		37,
		179,
		16,
		145,
		34,
		136,
		54,
		208,
		148,
		206,
		143,
		150,
		219,
		189,
		241,
		210,
		19,
		92,
		131,
		56,
		70,
		64,
		30,
		66,
		182,
		163,
		195,
		72,
		126,
		110,
		107,
		58,
		40,
		84,
		250,
		133,
		186,
		61,
		202,
		94,
		155,
		159,
		10,
		21,
		121,
		43,
		78,
		212,
		229,
		172,
		115,
		243,
		167,
		87,
		7,
		112,
		192,
		247,
		140,
		128,
		99,
		13,
		103,
		74,
		222,
		237,
		49,
		197,
		254,
		24,
		227,
		165,
		153,
		119,
		38,
		184,
		180,
		124,
		17,
		68,
		146,
		217,
		35,
		32,
		137,
		46,
		55,
		63,
		209,
		91,
		149,
		188,
		207,
		205,
		144,
		135,
		151,
		178,
		220,
		252,
		190,
		97,
		242,
		86,
		211,
		171,
		20,
		42,
		93,
		158,
		132,
		60,
		57,
		83,
		71,
		109,
		65,
		162,
		31,
		45,
		67,
		216,
		183,
		123,
		164,
		118,
		196,
		23,
		73,
		236,
		127,
		12,
		111,
		246,
		108,
		161,
		59,
		82,
		41,
		157,
		85,
		170,
		251,
		96,
		134,
		177,
		187,
		204,
		62,
		90,
		203,
		89,
		95,
		176,
		156,
		169,
		160,
		81,
		11,
		245,
		22,
		235,
		122,
		117,
		44,
		215,
		79,
		174,
		213,
		233,
		230,
		231,
		173,
		232,
		116,
		214,
		244,
		234,
		168,
		80,
		88,
		175,
	];

	private const GF_EXP = [
		1,
		2,
		4,
		8,
		16,
		32,
		64,
		128,
		29,
		58,
		116,
		232,
		205,
		135,
		19,
		38,
		76,
		152,
		45,
		90,
		180,
		117,
		234,
		201,
		143,
		3,
		6,
		12,
		24,
		48,
		96,
		192,
		157,
		39,
		78,
		156,
		37,
		74,
		148,
		53,
		106,
		212,
		181,
		119,
		238,
		193,
		159,
		35,
		70,
		140,
		5,
		10,
		20,
		40,
		80,
		160,
		93,
		186,
		105,
		210,
		185,
		111,
		222,
		161,
		95,
		190,
		97,
		194,
		153,
		47,
		94,
		188,
		101,
		202,
		137,
		15,
		30,
		60,
		120,
		240,
		253,
		231,
		211,
		187,
		107,
		214,
		177,
		127,
		254,
		225,
		223,
		163,
		91,
		182,
		113,
		226,
		217,
		175,
		67,
		134,
		17,
		34,
		68,
		136,
		13,
		26,
		52,
		104,
		208,
		189,
		103,
		206,
		129,
		31,
		62,
		124,
		248,
		237,
		199,
		147,
		59,
		118,
		236,
		197,
		151,
		51,
		102,
		204,
		133,
		23,
		46,
		92,
		184,
		109,
		218,
		169,
		79,
		158,
		33,
		66,
		132,
		21,
		42,
		84,
		168,
		77,
		154,
		41,
		82,
		164,
		85,
		170,
		73,
		146,
		57,
		114,
		228,
		213,
		183,
		115,
		230,
		209,
		191,
		99,
		198,
		145,
		63,
		126,
		252,
		229,
		215,
		179,
		123,
		246,
		241,
		255,
		227,
		219,
		171,
		75,
		150,
		49,
		98,
		196,
		149,
		55,
		110,
		220,
		165,
		87,
		174,
		65,
		130,
		25,
		50,
		100,
		200,
		141,
		7,
		14,
		28,
		56,
		112,
		224,
		221,
		167,
		83,
		166,
		81,
		162,
		89,
		178,
		121,
		242,
		249,
		239,
		195,
		155,
		43,
		86,
		172,
		69,
		138,
		9,
		18,
		36,
		72,
		144,
		61,
		122,
		244,
		245,
		247,
		243,
		251,
		235,
		203,
		139,
		11,
		22,
		44,
		88,
		176,
		125,
		250,
		233,
		207,
		131,
		27,
		54,
		108,
		216,
		173,
		71,
		142,
		1,
	];

	// EC level M format information strings (ISO 18004 Table C.1), XOR'd with 101010000010010
	// Indexed by mask pattern 0..7
	private const FORMAT_INFO_M = [
		0b101000111011111,  // L mask 0 -> for M: 0b100000011101100 ^ mask_xor... precomputed below
		0,
		0,
		0,
		0,
		0,
		0,
		0, // placeholder - see buildFormatInfo()
	];

	// EC level indicator bits for M: 00 (L), 01 (M), 10 (Q), 11 (H)
	// For M: indicator = 00 (spec uses 00 for M? No: L=01, M=00, Q=11, H=10)
	// Actually: L=01, M=00, Q=11, H=10 (ISO 18004 Table 12)
	private const EC_LEVEL_M_BITS = 0b00; // M

	// Format info mask constant (ISO 18004): 101010000010010
	private const FORMAT_MASK = 0b101010000010010;

	// Generator polynomial for format info: 10100110111 (x^10+x^8+x^5+x^4+x^2+x+1)
	private const FORMAT_POLY = 0b10100110111;

	// Finder pattern
	private const FINDER = [
		[1, 1, 1, 1, 1, 1, 1],
		[1, 0, 0, 0, 0, 0, 1],
		[1, 0, 1, 1, 1, 0, 1],
		[1, 0, 1, 1, 1, 0, 1],
		[1, 0, 1, 1, 1, 0, 1],
		[1, 0, 0, 0, 0, 0, 1],
		[1, 1, 1, 1, 1, 1, 1],
	];

	// Alignment pattern center coordinates by version (version 2..40)
	// ISO 18004 Annex E
	private const ALIGN_COORDS = [
		2  => [6, 18],
		3  => [6, 22],
		4  => [6, 26],
		5  => [6, 30],
		6  => [6, 34],
		7  => [6, 22, 38],
		8  => [6, 24, 42],
		9  => [6, 26, 46],
		10 => [6, 28, 50],
		11 => [6, 30, 54],
		12 => [6, 32, 58],
		13 => [6, 34, 62],
		14 => [6, 26, 46, 66],
		15 => [6, 26, 48, 70],
		16 => [6, 26, 50, 74],
		17 => [6, 30, 54, 78],
		18 => [6, 30, 56, 82],
		19 => [6, 30, 58, 86],
		20 => [6, 34, 62, 90],
		21 => [6, 28, 50, 72, 94],
		22 => [6, 26, 50, 74, 98],
		23 => [6, 30, 54, 78, 102],
		24 => [6, 28, 54, 80, 106],
		25 => [6, 32, 58, 84, 110],
		26 => [6, 30, 58, 86, 114],
		27 => [6, 34, 62, 90, 118],
		28 => [6, 26, 50, 74, 98, 122],
		29 => [6, 30, 54, 78, 102, 126],
		30 => [6, 26, 52, 78, 104, 130],
		31 => [6, 30, 56, 82, 108, 134],
		32 => [6, 34, 60, 86, 112, 138],
		33 => [6, 30, 58, 86, 114, 142],
		34 => [6, 34, 62, 90, 118, 146],
		35 => [6, 30, 54, 78, 102, 126, 150],
		36 => [6, 24, 50, 76, 102, 128, 154],
		37 => [6, 28, 54, 80, 106, 132, 158],
		38 => [6, 32, 58, 84, 110, 136, 162],
		39 => [6, 26, 54, 82, 110, 138, 166],
		40 => [6, 30, 58, 86, 114, 142, 170],
	];

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function get(): static
	{
		return new self();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function encode(string $data, int $size = 6, int $margin = 2): string
	{
		// 1. Choose version
		$bytes    = \array_values(\unpack('C*', $data));
		$data_len = \count($bytes);
		$version  = $this->chooseVersion($data_len);

		// 2. Build data codewords
		$codewords = $this->buildDataCodewords($bytes, $version);

		// 3. Add error correction codewords
		$allCodewords = $this->addErrorCorrection($codewords, $version);

		// 4. Build empty matrix
		$size_m = 17 + 4 * $version;
		$matrix = $this->buildMatrix($size_m, $version, $allCodewords);

		// 5. Apply best mask
		$matrix = $this->applyBestMask($matrix, $size_m, $version);

		// 6. Render PNG
		return $this->renderPng($matrix, $size_m, $size, $margin);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function decode(string $image): string
	{
		// 1. Load image (PNG or JPEG) and extract binary module grid
		$matrix  = $this->imageToMatrix($image);
		$n       = \count($matrix);

		if ($n < 21 || ($n - 17) % 4 !== 0) {
			throw new RuntimeException(\sprintf(
				'Invalid QR code image: derived matrix size %d is not a valid QR symbol size (21-177).',
				$n
			));
		}

		$version = ($n - 17) / 4;

		// 2. Read format information (try copy 1, fall back to copy 2)
		$format = $this->readFormatInfo($matrix, $n);

		if (null === $format) {
			throw new RuntimeException(
				'Failed to decode QR code: format information BCH check failed on both copies.'
			);
		}

		[$maskPattern] = $format;

		// 3. Identify function modules (same placement logic as the encoder)
		$funcMask = $this->buildFunctionMask($n, $version);

		// 4. Extract and unmask data bits in the zigzag reading order
		$bits = $this->extractDataBits($matrix, $n, $funcMask, $maskPattern);

		// 5. Convert bit stream to codeword bytes
		$codewords = $this->bitsToBytes($bits);

		// 6. De-interleave: recover per-block data codewords and strip EC bytes
		$dataCodewords = $this->deinterleave($codewords, $version);

		// 7. Parse byte-mode segment -> original string
		return $this->parseByteMode($dataCodewords, $version);
	}

	/**
	 * Loads a PNG or JPEG binary and samples each module at its center pixel,
	 * returning a 2-D array of 0 (light) / 1 (dark) values.
	 *
	 * @return int[][]
	 */
	private function imageToMatrix(string $image): array
	{
		/**
		 * imagecreatefromstring() emits E_WARNING on unrecognized data.
		 * Temporarily install a noop handler so the warning does not leak into
		 * the application log - we validate the return value immediately.
		 *
		 * @psalm-suppress InvalidArgument - imagecreatefromstring() may emit a warning on invalid data, but we handle that case
		 */
		\set_error_handler(static function (): void {}, \E_WARNING);
		$img = \imagecreatefromstring($image);
		\restore_error_handler();

		if (!$img) {
			throw new RuntimeException('Failed to decode QR code: imagecreatefromstring returned false.');
		}

		// Palette images (e.g. palette PNG) return a colour index from imagecolorat()
		// instead of a packed RGB value. Convert to truecolor so the $isDark threshold works.
		if (!\imageistruecolor($img)) {
			\imagepalettetotruecolor($img);
		}

		$w = \imagesx($img);
		$h = \imagesy($img);

		// Threshold: pixel is dark when its mean channel value < 128.
		$isDark = static function (int $color): bool {
			$r = ($color >> 16) & 0xFF;
			$g = ($color >> 8) & 0xFF;
			$b = $color & 0xFF;

			return ($r + $g + $b) < 384;
		};

		// Find the first dark pixel (top-left of the quiet zone border + finder).
		$marginY = null;
		$marginX = null;

		for ($y = 0; $y < $h && null === $marginY; ++$y) {
			for ($x = 0; $x < $w; ++$x) {
				if ($isDark(\imagecolorat($img, $x, $y))) {
					$marginY = $y;
					$marginX = $x;

					break;
				}
			}
		}

		if (null === $marginY) {
			throw new RuntimeException('Failed to decode QR code: no dark pixels found in image.');
		}

		// The first row of the finder pattern is 7 consecutive dark modules.
		// Measure the run length to derive the module pixel size.
		$runLen = 0;

		for ($x = $marginX; $x < $w; ++$x) {
			if ($isDark(\imagecolorat($img, $x, $marginY))) {
				++$runLen;
			} else {
				break;
			}
		}

		if ($runLen < 7) {
			throw new RuntimeException(
				'Failed to decode QR code: finder pattern run too short, cannot detect module size.'
			);
		}

		$moduleSize = (int) \round($runLen / 7);

		if ($moduleSize < 1) {
			throw new RuntimeException('Failed to decode QR code: computed module size is 0.');
		}

		// Derive the symbol width in modules.
		$n = (int) \round(($w - 2 * $marginX) / $moduleSize);

		// Sample each module at its center pixel.
		$matrix = [];

		for ($r = 0; $r < $n; ++$r) {
			$matrix[$r] = [];
			$py         = $marginY + $r * $moduleSize + (int) ($moduleSize / 2);

			for ($c = 0; $c < $n; ++$c) {
				$px             = $marginX + $c * $moduleSize + (int) ($moduleSize / 2);
				$matrix[$r][$c] = $isDark(\imagecolorat($img, $px, $py)) ? 1 : 0;
			}
		}

		return $matrix;
	}

	/**
	 * Reads the 15-bit format information word from the matrix (tries copy 1 then copy 2),
	 * verifies the BCH(15,5) check, and returns [maskPattern, ecLevel], or null on failure.
	 *
	 * Bit positions match exactly those written by {@see writeFormatInfo}.
	 *
	 * @param int[][] $matrix
	 *
	 * @return null|int[] [maskPattern, ecLevel] or null if both copies fail BCH
	 */
	private function readFormatInfo(array $matrix, int $n): ?array
	{
		// Two copies of format information - bit positions as written by writeFormatInfo().
		$copies = [
			// Copy 1: adjacent to the top-left finder
			[
				[8, 0],
				[8, 1],
				[8, 2],
				[8, 3],
				[8, 4],
				[8, 5],
				[8, 7],
				[8, 8],
				[7, 8],
				[5, 8],
				[4, 8],
				[3, 8],
				[2, 8],
				[1, 8],
				[0, 8],
			],
			// Copy 2: top-right + bottom-left finders
			[
				[$n - 1, 8],
				[$n - 2, 8],
				[$n - 3, 8],
				[$n - 4, 8],
				[$n - 5, 8],
				[$n - 6, 8],
				[$n - 7, 8],
				[8, $n - 8],
				[8, $n - 7],
				[8, $n - 6],
				[8, $n - 5],
				[8, $n - 4],
				[8, $n - 3],
				[8, $n - 2],
				[8, $n - 1],
			],
		];

		foreach ($copies as $positions) {
			$raw = 0;

			foreach ($positions as $i => [$r, $c]) {
				$raw |= ($matrix[$r][$c] << (14 - $i));
			}

			// Remove the XOR mask applied at write time.
			$unmasked = $raw ^ self::FORMAT_MASK;

			// BCH(15,5) remainder check: reduce mod FORMAT_POLY; must be 0 for a clean codeword.
			$rem = $unmasked;

			for ($i = 14; $i >= 10; --$i) {
				if (($rem >> $i) & 1) {
					$rem ^= self::FORMAT_POLY << ($i - 10);
				}
			}

			if (0 === ($rem & 0x3FF)) {
				$ecLevel     = ($unmasked >> 13) & 0x3;
				$maskPattern = ($unmasked >> 10) & 0x7;

				return [$maskPattern, $ecLevel];
			}
		}

		return null;
	}

	/**
	 * Builds a boolean map of function modules by replaying the encoder's pattern-placement
	 * steps on a scratch matrix (without placing any data bits).
	 *
	 * Every position that ends up != -1 after placement is a function module and must NOT
	 * be treated as data during decoding.
	 *
	 * @return bool[][]
	 */
	private function buildFunctionMask(int $n, int $version): array
	{
		$m = \array_fill(0, $n, \array_fill(0, $n, -1));

		$this->placeFinders($m, $n, $version);
		$this->placeAlignment($m, $version);
		$this->placeTimingPatterns($m, $n);

		if ($version >= 7) {
			$this->placeVersionInfo($m, $n, $version);
		}

		$this->reserveFormatAreas($m, $n);

		$mask = [];

		for ($r = 0; $r < $n; ++$r) {
			$mask[$r] = [];

			for ($c = 0; $c < $n; ++$c) {
				$mask[$r][$c] = (-1 !== $m[$r][$c]);
			}
		}

		return $mask;
	}

	/**
	 * Reads bits from all non-function module positions in the standard zigzag order
	 * and unmasks each bit using the given mask pattern.
	 *
	 * @param int[][]  $matrix
	 * @param bool[][] $funcMask
	 *
	 * @return int[]
	 */
	private function extractDataBits(array $matrix, int $n, array $funcMask, int $maskPattern): array
	{
		$bits = [];
		$col  = $n - 1;
		$goUp = true;

		while ($col > 0) {
			if (6 === $col) {
				--$col; // skip the vertical timing strip
			}

			$rows = $goUp ? \range($n - 1, 0, -1) : \range(0, $n - 1);

			foreach ($rows as $row) {
				for ($dc = 0; $dc < 2; ++$dc) {
					$c = $col - $dc;

					if (!$funcMask[$row][$c]) {
						$bits[] = $matrix[$row][$c] ^ ($this->maskCondition($maskPattern, $row, $c) ? 1 : 0);
					}
				}
			}

			$col -= 2;
			$goUp = !$goUp;
		}

		return $bits;
	}

	/**
	 * Reverses the interleaving performed by {@see addErrorCorrection} and returns
	 * the concatenated data codewords for all blocks (EC codewords are discarded).
	 *
	 * @param int[] $codewords full interleaved codeword stream (data + EC)
	 * @param int   $version
	 *
	 * @return int[] data codewords only
	 */
	private function deinterleave(array $codewords, int $version): array
	{
		[,, $b1, $cw1, $b2, $cw2] = self::EC_M[$version];

		$totalBlocks = $b1 + $b2;
		$blockSizes  = \array_merge(\array_fill(0, $b1, $cw1), \array_fill(0, $b2, $cw2));
		$maxData     = \max($blockSizes);
		$blocks      = \array_fill(0, $totalBlocks, []);
		$idx         = 0;

		// The encoder interleaved by taking codeword $i from each block in turn.
		for ($i = 0; $i < $maxData; ++$i) {
			for ($b = 0; $b < $totalBlocks; ++$b) {
				if ($i < $blockSizes[$b]) {
					$blocks[$b][] = $codewords[$idx++];
				}
			}
		}

		$data = [];

		foreach ($blocks as $block) {
			foreach ($block as $cw) {
				$data[] = $cw;
			}
		}

		return $data;
	}

	/**
	 * Parses a byte-mode segment from the data codeword stream and returns the original string.
	 *
	 * @param int[] $dataCodewords
	 * @param int   $version
	 *
	 * @return string
	 */
	private function parseByteMode(array $dataCodewords, int $version): string
	{
		$bits = [];

		foreach ($dataCodewords as $cw) {
			$this->appendBits($bits, $cw, 8);
		}

		$pos = 0;

		// Mode indicator: byte mode = 0100
		$mode = 0;

		for ($i = 0; $i < 4; ++$i) {
			$mode = ($mode << 1) | ($bits[$pos++] ?? 0);
		}

		if (0b0100 !== $mode) {
			throw new RuntimeException(\sprintf(
				'Unsupported QR code encoding mode: 0x%X (only byte mode 0x4 is supported).',
				$mode
			));
		}

		// Character count indicator: 8 bits for versions 1-9, 16 bits for 10-40
		$ccBits = ($version <= 9) ? 8 : 16;
		$count  = 0;

		for ($i = 0; $i < $ccBits; ++$i) {
			$count = ($count << 1) | ($bits[$pos++] ?? 0);
		}

		$result = '';

		for ($i = 0; $i < $count; ++$i) {
			$byte = 0;

			for ($j = 0; $j < 8; ++$j) {
				$byte = ($byte << 1) | ($bits[$pos++] ?? 0);
			}

			$result .= \chr($byte);
		}

		return $result;
	}

	/**
	 * Selects the smallest version (1-40) able to hold $bytes data bytes at EC level M.
	 */
	private function chooseVersion(int $bytes): int
	{
		// Byte-mode character count indicator bits by version range:
		// versions 1-9: 8 bits, 10-26: 16 bits, 27-40: 16 bits
		foreach (self::EC_M as $v => $info) {
			$dataCw   = $info[0];
			$ccBits   = ($v <= 9) ? 8 : 16;
			// mode (4) + cc (ccBits) + data (8*bytes) + terminator (4)
			$bitsNeeded = 4 + $ccBits + 8 * $bytes + 4;
			$bitsAvail  = $dataCw * 8;

			if ($bitsNeeded <= $bitsAvail) {
				return $v;
			}
		}

		throw new RuntimeException(\sprintf(
			'QR code data too large: %d bytes exceeds maximum capacity for EC level M.',
			$bytes
		));
	}

	/**
	 * Build data codeword byte array (with padding) for byte-mode encoding.
	 *
	 * @param int[] $bytes   raw data bytes
	 * @param int   $version QR version
	 *
	 * @return int[]
	 */
	private function buildDataCodewords(array $bytes, int $version): array
	{
		$dataCw  = self::EC_M[$version][0];
		$ccBits  = ($version <= 9) ? 8 : 16;
		$ccMask  = (1 << $ccBits) - 1;

		// Bit stream
		$bits   = [];
		// Mode indicator: byte mode = 0100
		$this->appendBits($bits, 0b0100, 4);
		// Character count
		$this->appendBits($bits, \count($bytes) & $ccMask, $ccBits);
		// Data bytes
		foreach ($bytes as $b) {
			$this->appendBits($bits, $b, 8);
		}
		// Terminator (up to 4 zero bits)
		$totalBits = $dataCw * 8;
		$pad       = \min(4, $totalBits - \count($bits));

		for ($i = 0; $i < $pad; ++$i) {
			$bits[] = 0;
		}
		// Pad to byte boundary
		while (0 !== \count($bits) % 8) {
			$bits[] = 0;
		}
		// Pad codewords
		$cw       = $this->bitsToBytes($bits);
		$padBytes = [0xEC, 0x11];
		$pi       = 0;

		while (\count($cw) < $dataCw) {
			$cw[] = $padBytes[$pi % 2];
			++$pi;
		}

		return $cw;
	}

	/**
	 * Appends $n bits of $value (MSB first) to $bits array.
	 *
	 * @param int[] $bits
	 */
	private function appendBits(array &$bits, int $value, int $n): void
	{
		for ($i = $n - 1; $i >= 0; --$i) {
			$bits[] = ($value >> $i) & 1;
		}
	}

	/**
	 * Converts a flat 0/1 bit array to a byte array (MSB first per byte).
	 *
	 * @param int[] $bits
	 *
	 * @return int[]
	 */
	private function bitsToBytes(array $bits): array
	{
		$out = [];

		for ($i = 0; $i < \count($bits); $i += 8) {
			$b = 0;

			for ($j = 0; $j < 8; ++$j) {
				$b = ($b << 1) | ($bits[$i + $j] ?? 0);
			}
			$out[] = $b;
		}

		return $out;
	}

	/**
	 * Computes Reed-Solomon EC codewords and interleaves data + EC into the final stream.
	 *
	 * @param int[] $dataCodewords
	 * @param int   $version
	 *
	 * @return int[]
	 */
	private function addErrorCorrection(array $dataCodewords, int $version): array
	{
		[, $ecPerBlock, $b1, $cw1, $b2, $cw2] = self::EC_M[$version];

		// Split data into blocks
		$blocks   = [];
		$offset   = 0;

		for ($i = 0; $i < $b1; ++$i) {
			$blocks[] = \array_slice($dataCodewords, $offset, $cw1);
			$offset += $cw1;
		}

		for ($i = 0; $i < $b2; ++$i) {
			$blocks[] = \array_slice($dataCodewords, $offset, $cw2);
			$offset += $cw2;
		}

		// Compute EC for each block
		$ecBlocks = [];

		foreach ($blocks as $block) {
			$ecBlocks[] = $this->reedSolomon($block, $ecPerBlock);
		}

		// Interleave data codewords
		$maxData = \max(\array_map('count', $blocks));
		$out     = [];

		for ($i = 0; $i < $maxData; ++$i) {
			foreach ($blocks as $block) {
				if (isset($block[$i])) {
					$out[] = $block[$i];
				}
			}
		}

		// Interleave EC codewords
		for ($i = 0; $i < $ecPerBlock; ++$i) {
			foreach ($ecBlocks as $ecBlock) {
				$out[] = $ecBlock[$i];
			}
		}

		return $out;
	}

	/**
	 * Reed-Solomon codeword generation for one block.
	 *
	 * @param int[] $data    data codewords for this block
	 * @param int   $ecCount number of EC codewords to produce
	 *
	 * @return int[]
	 */
	private function reedSolomon(array $data, int $ecCount): array
	{
		$gen = $this->rsGenerator($ecCount);
		$msg = \array_merge($data, \array_fill(0, $ecCount, 0));

		for ($i = 0; $i < \count($data); ++$i) {
			$coef = $msg[$i];

			if (0 !== $coef) {
				$logCoef = self::GF_LOG[$coef];

				for ($j = 0; $j < \count($gen); ++$j) {
					$msg[$i + $j] ^= self::GF_EXP[(($logCoef + $gen[$j]) % 255 + 255) % 255];
				}
			}
		}

		return \array_slice($msg, \count($data));
	}

	/**
	 * Returns the Reed-Solomon generator polynomial coefficients in log form.
	 *
	 * @return int[]
	 */
	private function rsGenerator(int $ecCount): array
	{
		$g = [0]; // g(x) = 1, stored as log(1) = 0

		for ($i = 0; $i < $ecCount; ++$i) {
			$g2 = [];

			foreach ($g as $coef) {
				$g2[] = $coef; // multiply by x -> shift
			}
			$g2[] = 0;

			// multiply by (x - alpha^i): XOR each coefficient
			for ($j = \count($g2) - 1; $j > 0; --$j) {
				if (-1 !== $g2[$j] && isset($g2[$j - 1]) && -1 !== $g2[$j - 1]) {
					$g2[$j] = ($g2[$j] + $i) % 255;
				} elseif (-1 === $g2[$j]) {
					$g2[$j] = $i % 255;
				}
			}
			$g2[0]  = ($g2[0] + $i) % 255;
			$g      = $g2;
		}

		return $g;
	}

	/**
	 * Builds the QR matrix: places all fixed patterns + data bits.
	 * Returns a 2-D array where 1 = dark, 0 = light, -1 = unplaced(data area).
	 *
	 * @param int[] $codewords
	 *
	 * @return int[][]
	 */
	private function buildMatrix(int $n, int $version, array $codewords): array
	{
		$m = \array_fill(0, $n, \array_fill(0, $n, -1));

		$this->placeFinders($m, $n, $version);
		$this->placeAlignment($m, $version);
		$this->placeTimingPatterns($m, $n);
		if ($version >= 7) {
			$this->placeVersionInfo($m, $n, $version);
		}
		// Reserve format info areas (filled after mask)
		$this->reserveFormatAreas($m, $n);
		$this->placeData($m, $n, $codewords);

		return $m;
	}

	/**
	 * Places the three finder patterns (with separators) and dark module.
	 *
	 * @param int[][] $m
	 */
	private function placeFinders(array &$m, int $n, int $version = 1): void
	{
		// top-left, top-right, bottom-left origins
		foreach ([[0, 0], [0, $n - 7], [$n - 7, 0]] as [$r, $c]) {
			foreach (self::FINDER as $dr => $row) {
				foreach ($row as $dc => $v) {
					$m[$r + $dr][$c + $dc] = $v;
				}
			}
		}

		// Separators (light modules around each finder)
		// top-left: row 7 cols 0-7, col 7 rows 0-7
		for ($i = 0; $i <= 7; ++$i) {
			$this->setModule($m, $n, 7, $i, 0);
			$this->setModule($m, $n, $i, 7, 0);
		}
		// top-right: row 7 cols n-8..n-1, col n-8 rows 0-7
		for ($i = 0; $i <= 7; ++$i) {
			$this->setModule($m, $n, 7, $n - 8 + $i, 0);
			$this->setModule($m, $n, $i, $n - 8, 0);
		}
		// bottom-left: col 7 rows n-8..n-1, row n-8 cols 0-7
		for ($i = 0; $i <= 7; ++$i) {
			$this->setModule($m, $n, $n - 8 + $i, 7, 0);
			$this->setModule($m, $n, $n - 8, $i, 0);
		}

		// Dark module (ISO 18004 section 7.9): always dark
		$m[($version ?? 1) * 4 + 9][$m === $m ? 8 : 8] = 1; // positioned at (4V+9, 8)
	}

	/**
	 * Places alignment patterns (version >= 2).
	 *
	 * @param int[][] $m
	 */
	private function placeAlignment(array &$m, int $version): void
	{
		if ($version < 2 || !isset(self::ALIGN_COORDS[$version])) {
			return;
		}

		$coords = self::ALIGN_COORDS[$version];

		foreach ($coords as $r) {
			foreach ($coords as $c) {
				// Skip positions that overlap finder patterns
				if (-1 !== $m[$r][$c]) {
					continue;
				}

				// 5x5 alignment pattern centered at (r,c)
				for ($dr = -2; $dr <= 2; ++$dr) {
					for ($dc = -2; $dc <= 2; ++$dc) {
						$v                     = (0 === \max(\abs($dr), \abs($dc)) % 2) ? 1 : 0;
						$m[$r + $dr][$c + $dc] = $v;
					}
				}
			}
		}
	}

	/**
	 * Places timing patterns (alternating dark/light starting with dark).
	 *
	 * @param int[][] $m
	 */
	private function placeTimingPatterns(array &$m, int $n): void
	{
		for ($i = 8; $i < $n - 8; ++$i) {
			$v = (0 === $i % 2) ? 1 : 0;

			if (-1 === $m[6][$i]) {
				$m[6][$i] = $v;
			}

			if (-1 === $m[$i][6]) {
				$m[$i][6] = $v;
			}
		}
	}

	/**
	 * Places 18-bit version information for versions >= 7.
	 *
	 * @param int[][] $m
	 */
	private function placeVersionInfo(array &$m, int $n, int $version): void
	{
		$info = $this->encodeVersion($version);

		for ($i = 0; $i < 18; ++$i) {
			$bit                        = ($info >> $i) & 1;
			$r                          = (int) ($i / 3);
			$c                          = $i % 3;
			$m[$r][$n - 11 + $c]        = $bit;
			$m[$n - 11 + $c][$r]        = $bit;
		}
	}

	/**
	 * Computes the 18-bit version information word (version + 12 EC bits).
	 */
	private function encodeVersion(int $version): int
	{
		$g = 0b1111100100101; // Generator polynomial for version info

		$rem = $version << 12;

		for ($i = 17; $i >= 12; --$i) {
			if (($rem >> $i) & 1) {
				$rem ^= $g << ($i - 12);
			}
		}

		return ($version << 12) | $rem;
	}

	/**
	 * Marks format info modules as 0 (will be overwritten after mask selection).
	 *
	 * @param int[][] $m
	 */
	private function reserveFormatAreas(array &$m, int $n): void
	{
		// Horizontal strip around top-left finder: row 8, cols 0-8
		for ($i = 0; $i <= 8; ++$i) {
			if (-1 === $m[8][$i]) {
				$m[8][$i] = 0;
			}
		}
		// Vertical strip: col 8, rows 0-8
		for ($i = 0; $i <= 8; ++$i) {
			if (-1 === $m[$i][8]) {
				$m[$i][8] = 0;
			}
		}
		// Top-right: row 8, cols n-8..n-1
		for ($i = $n - 8; $i < $n; ++$i) {
			if (-1 === $m[8][$i]) {
				$m[8][$i] = 0;
			}
		}
		// Bottom-left: col 8, rows n-7..n-1
		for ($i = $n - 7; $i < $n; ++$i) {
			if (-1 === $m[$i][8]) {
				$m[$i][8] = 0;
			}
		}
	}

	/**
	 * Places data codeword bits into the matrix using the upward/downward column pairs.
	 *
	 * @param int[][] $m
	 * @param int[]   $codewords
	 */
	private function placeData(array &$m, int $n, array $codewords): void
	{
		// Flatten codewords to bit array
		$bits = [];

		foreach ($codewords as $cw) {
			$this->appendBits($bits, $cw, 8);
		}

		$bi     = 0;
		$goUp   = true;
		$col    = $n - 1;

		while ($col > 0) {
			if (6 === $col) {
				--$col; // skip timing column
			}

			$rows = $goUp ? \range($n - 1, 0, -1) : \range(0, $n - 1);

			foreach ($rows as $row) {
				for ($dc = 0; $dc < 2; ++$dc) {
					$c = $col - $dc;

					if (-1 === $m[$row][$c]) {
						$m[$row][$c] = ($bits[$bi] ?? 0);
						++$bi;
					}
				}
			}

			$col -= 2;
			$goUp = !$goUp;
		}
	}

	/**
	 * Evaluates all 8 mask patterns and returns the matrix with the lowest penalty.
	 *
	 * @param int[][] $m
	 *
	 * @return int[][]
	 */
	private function applyBestMask(array $m, int $n, int $version): array
	{
		$funcMask    = $this->buildFunctionMask($n, $version);
		$bestMatrix  = null;
		$bestPenalty = \PHP_INT_MAX;

		for ($mask = 0; $mask < 8; ++$mask) {
			$masked = $this->applyMask($m, $n, $mask, $funcMask);
			$this->writeFormatInfo($masked, $n, $mask);
			$penalty = $this->penalty($masked, $n);

			if ($penalty < $bestPenalty) {
				$bestPenalty = $penalty;
				$bestMatrix  = $masked;
			}
		}

		return $bestMatrix ?? throw new RuntimeException('applyBestMask: no matrix could be produced.');
	}

	/**
	 * Applies a mask pattern to data modules only (ISO 18004: function modules are never masked).
	 *
	 * @param int[][]  $src
	 * @param bool[][] $funcMask positions that are function modules (finder, timing, alignment, format info)
	 *
	 * @return int[][]
	 */
	private function applyMask(array $src, int $n, int $mask, array $funcMask): array
	{
		$m = $src;

		for ($r = 0; $r < $n; ++$r) {
			for ($c = 0; $c < $n; ++$c) {
				if ($funcMask[$r][$c]) {
					continue; // function module: must not be masked
				}

				if ($this->maskCondition($mask, $r, $c)) {
					$m[$r][$c] ^= 1;
				}
			}
		}

		return $m;
	}

	/**
	 * Returns true if the mask condition for pattern $mask is satisfied at ($r, $c).
	 */
	private function maskCondition(int $mask, int $r, int $c): bool
	{
		return match ($mask) {
			0       => ($r + $c) % 2 === 0,
			1       => 0 === $r % 2,
			2       => 0 === $c % 3,
			3       => ($r + $c) % 3 === 0,
			4       => ((int) ($r / 2) + (int) ($c / 3)) % 2 === 0,
			5       => ($r * $c) % 2 + ($r * $c) % 3 === 0,
			6       => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0,
			7       => (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0,
			default => false,
		};
	}

	/**
	 * Writes the format information bits for EC level M and the given mask pattern.
	 *
	 * @param int[][] $m
	 */
	private function writeFormatInfo(array &$m, int $n, int $mask): void
	{
		$info = $this->buildFormatInfo($mask);

		// 15 bits placed in two copies
		$positions = [
			// Copy 1: around top-left finder
			[
				[8, 0],
				[8, 1],
				[8, 2],
				[8, 3],
				[8, 4],
				[8, 5],
				[8, 7],
				[8, 8],
				[7, 8],
				[5, 8],
				[4, 8],
				[3, 8],
				[2, 8],
				[1, 8],
				[0, 8],
			],
			// Copy 2: top-right + bottom-left
			[
				[$n - 1, 8],
				[$n - 2, 8],
				[$n - 3, 8],
				[$n - 4, 8],
				[$n - 5, 8],
				[$n - 6, 8],
				[$n - 7, 8],
				[8, $n - 8],
				[8, $n - 7],
				[8, $n - 6],
				[8, $n - 5],
				[8, $n - 4],
				[8, $n - 3],
				[8, $n - 2],
				[8, $n - 1],
			],
		];

		foreach ($positions as $copy) {
			foreach ($copy as $i => [$r, $c]) {
				$m[$r][$c] = ($info >> (14 - $i)) & 1;
			}
		}
	}

	/**
	 * Builds the 15-bit format information word for EC level M and the given mask.
	 * Format = EC(2 bits) | mask(3 bits), BCH protected, XOR'd with 101010000010010.
	 */
	private function buildFormatInfo(int $mask): int
	{
		// EC level M = 00, mask = 3 bits
		$data = (self::EC_LEVEL_M_BITS << 3) | $mask;
		// BCH(15,5): generator = 10100110111
		$gen  = self::FORMAT_POLY;
		$rem  = $data << 10;

		for ($i = 14; $i >= 10; --$i) {
			if (($rem >> $i) & 1) {
				$rem ^= $gen << ($i - 10);
			}
		}

		return (($data << 10) | $rem) ^ self::FORMAT_MASK;
	}

	/**
	 * Computes the penalty score for a masked matrix (ISO 18004 Section 7.8.3).
	 *
	 * @param int[][] $m
	 */
	private function penalty(array $m, int $n): int
	{
		$p = 0;

		// N1: 5+ consecutive same-color modules in row/col
		for ($r = 0; $r < $n; ++$r) {
			for ($isRow = 0; $isRow < 2; ++$isRow) {
				$run  = 1;
				$prev = $isRow ? $m[$r][0] : $m[0][$r];

				for ($c = 1; $c < $n; ++$c) {
					$cur = $isRow ? $m[$r][$c] : $m[$c][$r];

					if ($cur === $prev) {
						++$run;
					} else {
						if ($run >= 5) {
							$p += 3 + ($run - 5);
						}
						$run  = 1;
						$prev = $cur;
					}
				}

				if ($run >= 5) {
					$p += 3 + ($run - 5);
				}
			}
		}

		// N2: 2x2 blocks of same color
		for ($r = 0; $r < $n - 1; ++$r) {
			for ($c = 0; $c < $n - 1; ++$c) {
				$v = $m[$r][$c];

				if ($v === $m[$r][$c + 1] && $v === $m[$r + 1][$c] && $v === $m[$r + 1][$c + 1]) {
					$p += 3;
				}
			}
		}

		// N3: finder-like patterns 1:1:3:1:1 in rows/cols
		$pat1 = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
		$pat2 = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];

		for ($r = 0; $r < $n; ++$r) {
			for ($c = 0; $c <= $n - 11; ++$c) {
				$rowMatch1 = true;
				$rowMatch2 = true;
				$colMatch1 = true;
				$colMatch2 = true;

				for ($i = 0; $i < 11; ++$i) {
					if ($m[$r][$c + $i] !== $pat1[$i]) {
						$rowMatch1 = false;
					}

					if ($m[$r][$c + $i] !== $pat2[$i]) {
						$rowMatch2 = false;
					}

					if ($m[$c + $i][$r] !== $pat1[$i]) {
						$colMatch1 = false;
					}

					if ($m[$c + $i][$r] !== $pat2[$i]) {
						$colMatch2 = false;
					}
				}

				if ($rowMatch1 || $rowMatch2) {
					$p += 40;
				}

				if ($colMatch1 || $colMatch2) {
					$p += 40;
				}
			}
		}

		// N4: proportion of dark modules
		$dark  = 0;
		$total = $n * $n;

		for ($r = 0; $r < $n; ++$r) {
			foreach ($m[$r] as $v) {
				$dark += $v;
			}
		}

		$pct  = (int) (100 * $dark / $total);
		$prev = (int) ($pct / 5) * 5;
		$next = $prev + 5;
		$p += 10 * \min(\intdiv(\abs($prev - 50), 5), \intdiv(\abs($next - 50), 5));

		return $p;
	}

	/**
	 * Sets a module only if it is within bounds.
	 *
	 * @param int[][] $m
	 */
	private function setModule(array &$m, int $n, int $r, int $c, int $v): void
	{
		if ($r >= 0 && $r < $n && $c >= 0 && $c < $n) {
			$m[$r][$c] = $v;
		}
	}

	/**
	 * Renders the matrix as a raw PNG binary using ext-gd.
	 *
	 * @param int[][] $m
	 */
	private function renderPng(array $m, int $n, int $moduleSize, int $margin): string
	{
		$quietZone  = $margin * $moduleSize;
		$imgSize    = $n * $moduleSize + 2 * $quietZone;
		$img        = \imagecreatetruecolor($imgSize, $imgSize);
		$white      = \imagecolorallocate($img, 255, 255, 255);
		$black      = \imagecolorallocate($img, 0, 0, 0);

		\imagefill($img, 0, 0, $white);

		for ($r = 0; $r < $n; ++$r) {
			for ($c = 0; $c < $n; ++$c) {
				if (1 === $m[$r][$c]) {
					$x1 = $quietZone + $c * $moduleSize;
					$y1 = $quietZone + $r * $moduleSize;
					$x2 = $x1 + $moduleSize - 1;
					$y2 = $y1 + $moduleSize - 1;
					\imagefilledrectangle($img, $x1, $y1, $x2, $y2, $black);
				}
			}
		}

		\ob_start();
		\imagepng($img);
		$png = \ob_get_clean();

		if (false === $png) {
			throw new RuntimeException('Failed to capture PNG output buffer.');
		}

		return $png;
	}
}
