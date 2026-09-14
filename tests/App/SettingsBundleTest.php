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
use stdClass;

/**
 * Source settings directories read from their compiled bundles, as production does.
 *
 * @covers \OZONE\Core\App\Settings
 *
 * @internal
 */
final class SettingsBundleTest extends TestCase
{
	private const GROUPS = ['oz.config', 'oz.db', 'oz.routes', 'oz.gc', 'lang/oz.en'];

	protected function tearDown(): void
	{
		Settings::useBundles(null);

		foreach (self::GROUPS as $group) {
			Settings::load($group, true);
		}

		parent::tearDown();
	}

	public function testBundledSourcesGiveTheSameSettings(): void
	{
		Settings::useBundles(false);

		$plain = [];

		foreach (self::GROUPS as $group) {
			$plain[$group] = Settings::load($group, true);
		}

		Settings::useBundles(true);

		foreach (self::GROUPS as $group) {
			self::assertSame($plain[$group], Settings::load($group, true), $group);
		}

		self::assertNotEmpty(\glob(self::cacheDir() . \DIRECTORY_SEPARATOR . '*.php'));
	}

	public function testAStatefulSettingStillWins(): void
	{
		Settings::useBundles(true);

		try {
			Settings::set('oz.gc', 'OZ_GC_PROBABILITY', 5);

			self::assertSame(5, Settings::get('oz.gc', 'OZ_GC_PROBABILITY', null, true));
		} finally {
			Settings::unset('oz.gc', 'OZ_GC_PROBABILITY');
		}
	}

	public function testADirectoryHoldingAnObjectIsReadFileByFile(): void
	{
		$dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz_settings_' . \bin2hex(\random_bytes(6));

		\mkdir($dir, 0o775, true);
		\file_put_contents($dir . \DIRECTORY_SEPARATOR . 'oztest.object.php', '<?php return ["k" => new \stdClass()];');

		try {
			Settings::addSource($dir);
			Settings::useBundles(true);

			self::assertInstanceOf(stdClass::class, Settings::load('oztest.object', true)['k']);
			self::assertSame([], \glob(self::cacheDir() . \DIRECTORY_SEPARATOR . \hash('xxh128', $dir) . '.*.php'));
		} finally {
			\unlink($dir . \DIRECTORY_SEPARATOR . 'oztest.object.php');
			\rmdir($dir);
		}
	}

	private static function cacheDir(): string
	{
		return \rtrim(app()->getProjectDir()->getRoot(), '/\\')
			. \DIRECTORY_SEPARATOR . '.ozone' . \DIRECTORY_SEPARATOR . 'cache' . \DIRECTORY_SEPARATOR . 'settings';
	}
}
