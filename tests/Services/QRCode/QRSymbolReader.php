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

use RuntimeException;

/**
 * Reads a QR symbol the way a standard decoder does, written from ISO/IEC 18004 alone.
 *
 * Nothing here calls the encoder or borrows a table from it, which is the point: the suite that
 * shipped the broken encoder asserted `encode()` then `decode()` through the same class, so an
 * encoder and a decoder that agreed on something wrong passed every test. Every symbol OZone draws
 * is read back through this instead, and the error-correction codewords are recomputed here from
 * the Galois field up.
 *
 * @internal
 */
final class QRSymbolReader
{
	/** @var int[] */
	private array $exp = [];

	/** @var int[] */
	private array $log = [];

	public function __construct()
	{
		// GF(256) with the QR primitive polynomial, x^8 + x^4 + x^3 + x^2 + 1.
		$x = 1;

		for ($i = 0; $i < 255; ++$i) {
			$this->exp[$i] = $x;
			$this->log[$x] = $i;
			$x <<= 1;

			if ($x & 0x100) {
				$x ^= 0x11D;
			}
		}
	}

	/**
	 * The module grid of a PNG drawn at one pixel per module with no quiet zone.
	 *
	 * @return int[][]
	 */
	public function matrixFromPng(string $png): array
	{
		$img = \imagecreatefromstring($png);

		if (false === $img) {
			throw new RuntimeException('The encoder did not return a readable PNG.');
		}

		if (!\imageistruecolor($img)) {
			\imagepalettetotruecolor($img);
		}

		$w = \imagesx($img);
		$h = \imagesy($img);

		if ($w !== $h) {
			throw new RuntimeException(\sprintf('A QR symbol is square, this one is %dx%d.', $w, $h));
		}

		$matrix = [];

		for ($y = 0; $y < $h; ++$y) {
			$row = [];

			for ($x = 0; $x < $w; ++$x) {
				$c     = \imagecolorat($img, $x, $y);
				$sum   = (($c >> 16) & 0xFF) + (($c >> 8) & 0xFF) + ($c & 0xFF);
				$row[] = $sum < 384 ? 1 : 0;
			}

			$matrix[] = $row;
		}

		return $matrix;
	}

	/**
	 * The error-correction level and mask pattern the symbol declares, or null when the format
	 * information is not one of the thirty-two valid values.
	 *
	 * @param int[][] $m
	 *
	 * @return null|array{0: string, 1: int}
	 */
	public function formatInfo(array $m): ?array
	{
		$positions = [
			[8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
			[7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
		];
		$value = 0;

		foreach ($positions as [$r, $c]) {
			$value = ($value << 1) | $m[$r][$c];
		}

		foreach (['L' => 0b01, 'M' => 0b00, 'Q' => 0b11, 'H' => 0b10] as $level => $bits) {
			for ($mask = 0; $mask < 8; ++$mask) {
				if ($this->formatValue(($bits << 3) | $mask) === $value) {
					return [$level, $mask];
				}
			}
		}

		return null;
	}

	/**
	 * Every codeword of the symbol, unmasked and read in the standard zigzag order.
	 *
	 * @param int[][] $m
	 *
	 * @return int[]
	 */
	public function codewords(array $m, int $version, int $mask): array
	{
		$n    = 17 + 4 * $version;
		$func = $this->functionModules($n, $version);
		$bits = [];
		$col  = $n - 1;
		$up   = true;

		while ($col > 0) {
			if (6 === $col) {
				--$col;
			}

			$rows = $up ? \range($n - 1, 0) : \range(0, $n - 1);

			foreach ($rows as $r) {
				foreach ([$col, $col - 1] as $c) {
					if ($func[$r][$c]) {
						continue;
					}

					$bit = $m[$r][$c];

					if ($this->maskCondition($mask, $r, $c)) {
						$bit ^= 1;
					}

					$bits[] = $bit;
				}
			}

			$up = !$up;
			$col -= 2;
		}

		$codewords = [];
		$count     = \intdiv(\count($bits), 8);

		for ($i = 0; $i < $count; ++$i) {
			$byte = 0;

			for ($j = 0; $j < 8; ++$j) {
				$byte = ($byte << 1) | $bits[$i * 8 + $j];
			}

			$codewords[] = $byte;
		}

		return $codewords;
	}

	/**
	 * Splits an interleaved codeword stream back into its blocks.
	 *
	 * @param int[] $codewords
	 * @param int[] $sizes     data codewords per block
	 *
	 * @return array{0: int[][], 1: int[][]} the data blocks, then the error-correction blocks
	 */
	public function deinterleave(array $codewords, array $sizes, int $ec_per_block): array
	{
		$blocks = \array_fill(0, \count($sizes), []);
		$ec     = \array_fill(0, \count($sizes), []);
		$i      = 0;

		for ($pos = 0, $max = \max($sizes); $pos < $max; ++$pos) {
			foreach ($sizes as $b => $size) {
				if ($pos < $size) {
					$blocks[$b][] = $codewords[$i++];
				}
			}
		}

		for ($pos = 0; $pos < $ec_per_block; ++$pos) {
			foreach (\array_keys($sizes) as $b) {
				$ec[$b][] = $codewords[$i++];
			}
		}

		return [$blocks, $ec];
	}

	/**
	 * The error-correction codewords a block must carry.
	 *
	 * @param int[] $data
	 *
	 * @return int[]
	 */
	public function errorCorrection(array $data, int $count): array
	{
		$gen   = $this->generator($count);
		$msg   = \array_values(\array_merge($data, \array_fill(0, $count, 0)));
		$total = \count($data);

		for ($i = 0; $i < $total; ++$i) {
			$coef = $msg[$i];

			if (0 === $coef) {
				continue;
			}

			for ($j = 0, $degree = \count($gen); $j < $degree; ++$j) {
				$msg[$i + $j] ^= $this->mul($gen[$j], $coef);
			}
		}

		return \array_slice($msg, $total);
	}

	/**
	 * The payload of a byte-mode segment.
	 *
	 * @param int[] $data data codewords, blocks concatenated in order
	 */
	public function byteModePayload(array $data, int $version): ?string
	{
		$bits = '';

		foreach ($data as $byte) {
			$bits .= \str_pad(\decbin($byte), 8, '0', \STR_PAD_LEFT);
		}

		if (0b0100 !== (int) \bindec(\substr($bits, 0, 4))) {
			return null;
		}

		$cc_bits = $version <= 9 ? 8 : 16;
		$length  = (int) \bindec(\substr($bits, 4, $cc_bits));
		$offset  = 4 + $cc_bits;

		if (($length * 8) + $offset > \strlen($bits)) {
			return null;
		}

		$out = '';

		for ($i = 0; $i < $length; ++$i) {
			$out .= \chr((int) \bindec(\substr($bits, $offset + ($i * 8), 8)));
		}

		return $out;
	}

	/**
	 * The generator polynomial, as values and not logs: the shifted and the scaled polynomial are
	 * added, which cannot be done on logs.
	 *
	 * @return int[]
	 */
	private function generator(int $count): array
	{
		$g = [1];

		for ($i = 0; $i < $count; ++$i) {
			$next = \array_fill(0, \count($g) + 1, 0);

			foreach ($g as $j => $coef) {
				$next[$j] ^= $coef;
				$next[$j + 1] ^= $this->mul($coef, $this->exp[$i]);
			}

			$g = $next;
		}

		return $g;
	}

	private function mul(int $a, int $b): int
	{
		if (0 === $a || 0 === $b) {
			return 0;
		}

		return $this->exp[($this->log[$a] + $this->log[$b]) % 255];
	}

	/**
	 * The fifteen bits of format information for a five-bit value, BCH(15,5) and the fixed mask.
	 */
	private function formatValue(int $data): int
	{
		$v = $data << 10;

		for ($i = 14; $i >= 10; --$i) {
			if (($v >> $i) & 1) {
				$v ^= 0b10100110111 << ($i - 10);
			}
		}

		return (($data << 10) | ($v & 0x3FF)) ^ 0b101010000010010;
	}

	/**
	 * Which modules are function patterns, so the data is read from the rest.
	 *
	 * @return bool[][]
	 */
	private function functionModules(int $n, int $version): array
	{
		$f = \array_fill(0, $n, \array_fill(0, $n, false));

		$box = static function (array &$f, int $r0, int $c0, int $h, int $w) use ($n): void {
			for ($r = $r0; $r < $r0 + $h; ++$r) {
				for ($c = $c0; $c < $c0 + $w; ++$c) {
					if ($r >= 0 && $r < $n && $c >= 0 && $c < $n) {
						$f[$r][$c] = true;
					}
				}
			}
		};

		// The three finders with their separators, which also covers the format information.
		$box($f, 0, 0, 9, 9);
		$box($f, 0, $n - 8, 9, 8);
		$box($f, $n - 8, 0, 8, 9);

		for ($i = 0; $i < $n; ++$i) {
			$f[6][$i] = true;
			$f[$i][6] = true;
		}

		$coords = self::ALIGNMENT[$version] ?? [];
		$last   = empty($coords) ? 0 : $coords[\count($coords) - 1];

		foreach ($coords as $r) {
			foreach ($coords as $c) {
				if ((6 === $r && 6 === $c) || (6 === $r && $last === $c) || ($last === $r && 6 === $c)) {
					continue;
				}

				$box($f, $r - 2, $c - 2, 5, 5);
			}
		}

		if ($version >= 7) {
			$box($f, $n - 11, 0, 3, 6);
			$box($f, 0, $n - 11, 6, 3);
		}

		return $f;
	}

	private function maskCondition(int $mask, int $r, int $c): bool
	{
		return match ($mask) {
			0 => 0 === ($r + $c) % 2,
			1 => 0 === $r % 2,
			2 => 0 === $c % 3,
			3 => 0 === ($r + $c) % 3,
			4 => 0 === (\intdiv($r, 2) + \intdiv($c, 3)) % 2,
			5 => 0 === ($r * $c) % 2 + ($r * $c) % 3,
			6 => 0 === (($r * $c) % 2 + ($r * $c) % 3) % 2,
			7 => 0 === (($r + $c) % 2 + ($r * $c) % 3) % 2,
			default => throw new RuntimeException(\sprintf('No mask pattern %d.', $mask)),
		};
	}

	/**
	 * Alignment pattern centres per version, ISO 18004 Annex E.
	 */
	private const array ALIGNMENT = [
		2  => [6, 18],          3  => [6, 22],          4  => [6, 26],
		5  => [6, 30],          6  => [6, 34],          7  => [6, 22, 38],
		8  => [6, 24, 42],      9  => [6, 26, 46],      10 => [6, 28, 50],
		11 => [6, 30, 54],      12 => [6, 32, 58],      13 => [6, 34, 62],
		14 => [6, 26, 46, 66],  15 => [6, 26, 48, 70],  16 => [6, 26, 50, 74],
		17 => [6, 30, 54, 78],  18 => [6, 30, 56, 82],  19 => [6, 30, 58, 86],
		20 => [6, 34, 62, 90],  21 => [6, 28, 50, 72, 94],
		22 => [6, 26, 50, 74, 98],  23 => [6, 30, 54, 78, 102],
		24 => [6, 28, 54, 80, 106], 25 => [6, 32, 58, 84, 110],
		26 => [6, 30, 58, 86, 114], 27 => [6, 34, 62, 90, 118],
		28 => [6, 26, 50, 74, 98, 122],  29 => [6, 30, 54, 78, 102, 126],
		30 => [6, 26, 52, 78, 104, 130], 31 => [6, 30, 56, 82, 108, 134],
		32 => [6, 34, 60, 86, 112, 138], 33 => [6, 30, 58, 86, 114, 142],
		34 => [6, 34, 62, 90, 118, 146], 35 => [6, 30, 54, 78, 102, 126, 150],
		36 => [6, 24, 50, 76, 102, 128, 154], 37 => [6, 28, 54, 80, 106, 132, 158],
		38 => [6, 32, 58, 84, 110, 136, 162], 39 => [6, 26, 54, 82, 110, 138, 166],
		40 => [6, 30, 58, 86, 114, 142, 170],
	];
}
