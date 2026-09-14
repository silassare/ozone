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

use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\Scan\FileScanResult;
use OZONE\Core\FS\Scan\Interfaces\FileScannerInterface;

/**
 * Class FakeFileScanner.
 *
 * A scanner whose verdict the test sets, and which records what it scanned.
 */
final class FakeFileScanner implements FileScannerInterface
{
	/**
	 * The threat found in every content; null: every content is clean.
	 */
	public static ?string $signature = null;

	/**
	 * Whether every scan fails.
	 */
	public static bool $fail = false;

	/**
	 * @var string[] the contents scanned
	 */
	public static array $scanned = [];

	/**
	 * Restores a clean verdict and forgets the contents scanned.
	 */
	public static function reset(): void
	{
		self::$signature = null;
		self::$fail      = false;
		self::$scanned   = [];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function fromSettings(): static
	{
		return new self();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function scan(FileStream $content): FileScanResult
	{
		self::$scanned[] = (string) $content;

		if (self::$fail) {
			throw new RuntimeException('The scanner is down.');
		}

		return null === self::$signature ? FileScanResult::clean() : FileScanResult::infected(self::$signature);
	}
}
