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

	/**
	 * A list is one value: a source that declares one replaces it, rather than splicing it.
	 *
	 * `array_replace_recursive` overwrote element by element, so a project that shortened a list kept
	 * the tail of the longer one: restricting `OZ_2FA_CHANNEL_PRIORITY` to `['email']` left
	 * `['email', 'email', 'sms']`, and the documented override could not work.
	 */
	public function testAListIsReplacedWhileAMapIsMerged(): void
	{
		self::assertSame(
			['channels' => ['email']],
			Settings::applyMergeStrategy(
				['channels' => ['totp', 'email', 'sms']],
				['channels' => ['email']]
			)
		);

		self::assertSame(
			['a' => 1, 'b' => 2, 'c' => 3],
			Settings::applyMergeStrategy(['a' => 1, 'b' => 0], ['b' => 2, 'c' => 3]),
			'a map still takes what each source adds'
		);

		self::assertSame(
			['rules' => ['user' => ['email'], 'staff' => ['phone']]],
			Settings::applyMergeStrategy(
				['rules' => ['user' => ['email', 'phone']]],
				['rules' => ['user' => ['email'], 'staff' => ['phone']]]
			),
			'a map of lists merges by key, and each list is replaced whole'
		);

		self::assertSame(
			['x', 'y'],
			Settings::applyMergeStrategy(['x'], ['y']),
			'two lists at the top of a group are still appended, as a group of sources is'
		);
	}

	/**
	 * A key may say how it is merged, and `@merge` never reaches the values.
	 */
	public function testAKeyDeclaresHowItIsMerged(): void
	{
		$strategies = [
			'hosts'    => Settings::MERGE_APPEND,
			'priority' => Settings::MERGE_REPLACE,
		];

		self::assertSame(
			[
				'hosts'    => ['a.example.com', 'b.example.com'],
				'priority' => ['email'],
				'map'      => ['x' => 1, 'y' => 2],
			],
			Settings::applyMergeStrategy(
				[
					'hosts'    => ['a.example.com'],
					'priority' => ['totp', 'email', 'sms'],
					'map'      => ['x' => 1],
				],
				[
					'hosts'    => ['b.example.com'],
					'priority' => ['email'],
					'map'      => ['y' => 2],
				],
				$strategies
			),
			'append adds to a list, replace swaps it, and a map still merges'
		);
	}

	/**
	 * A locked setting is what the source that declared it says, for every source after it.
	 *
	 * An allow-list is the reason: a plugin loaded after the application must not be able to widen
	 * `OZ_REDIRECT_ALLOWED_HOSTS`, and a source that tries fails loudly rather than quietly.
	 */
	public function testALockedSettingRefusesWhatComesAfterIt(): void
	{
		$first = self::tempSource('oztest.lock.php', <<<'PHP'
			<?php return [
				'@merge' => ['HOSTS' => 'lock'],
				'HOSTS'  => ['example.com'],
			];
			PHP);
		$later = self::tempSource('oztest.lock.php', <<<'PHP'
			<?php return ['HOSTS' => ['evil.example.com']];
			PHP);

		try {
			Settings::addSource($first);
			Settings::addSource($later);

			$this->expectExceptionMessageMatches('~locked~');

			Settings::load('oztest.lock', true);
		} finally {
			\unlink($first . \DIRECTORY_SEPARATOR . 'oztest.lock.php');
			\unlink($later . \DIRECTORY_SEPARATOR . 'oztest.lock.php');
			\rmdir($first);
			\rmdir($later);
		}
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

	/**
	 * A directory of its own holding one settings file, as a plugin or an application would have.
	 */
	private static function tempSource(string $file, string $content): string
	{
		$dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz_settings_' . \bin2hex(\random_bytes(6));

		\mkdir($dir, 0o775, true);
		\file_put_contents($dir . \DIRECTORY_SEPARATOR . $file, $content);

		return $dir;
	}

	private static function cacheDir(): string
	{
		return \rtrim(app()->getProjectDir()->getRoot(), '/\\')
			. \DIRECTORY_SEPARATOR . '.ozone' . \DIRECTORY_SEPARATOR . 'cache' . \DIRECTORY_SEPARATOR . 'settings';
	}
}
