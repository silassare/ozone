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

namespace OZONE\Tests\App;

use OZONE\Core\App\Settings;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OZONE\Core\App\Settings
 *
 * @internal
 */
final class SettingsTest extends TestCase
{
	public function testEveryStatefulEditMovesTheDirectoryMtime(): void
	{
		$root = app()->getStatefulSettingsDir()->getRoot();

		try {
			$mtimes = [self::mtime($root)];

			// Within one second: the mtime is read in seconds, and route tables are keyed by it.
			Settings::set('oz.gc', 'OZ_GC_PROBABILITY', 3);
			$mtimes[] = self::mtime($root);

			Settings::set('oz.gc', 'OZ_GC_PROBABILITY', 4);
			$mtimes[] = self::mtime($root);

			self::assertGreaterThan($mtimes[0], $mtimes[1]);
			self::assertGreaterThan($mtimes[1], $mtimes[2]);
		} finally {
			Settings::unset('oz.gc', 'OZ_GC_PROBABILITY');
		}
	}

	private static function mtime(string $dir): int
	{
		\clearstatcache(true, $dir);

		return (int) \filemtime($dir);
	}
}
