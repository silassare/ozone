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

namespace OZONE\Tests\Lang;

use OZONE\Core\App\Settings;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Class CatalogTest.
 *
 * Every message code the framework uses (an `'OZ_...'` literal in `oz/` that is not a
 * setting key, a constant or an environment variable name) must be translated in every
 * shipped catalog, with the same placeholders.
 *
 * @internal
 *
 * @coversNothing
 */
final class CatalogTest extends TestCase
{
	/**
	 * Constants and environment variable names that look like message codes.
	 */
	private const NOT_MESSAGES = [
		'OZ_APP_DIR',
		'OZ_APP_SALT',
		'OZ_APP_SECRET',
		'OZ_OZONE_DIR',
		'OZ_OZONE_IS_CLI',
		'OZ_OZONE_IS_WEB_CONTEXT',
		'OZ_OZONE_START_TIME',
		'OZ_PROJECT_DIR',
		'OZ_RUNTIME',
		'OZ_SCOPE_NAME',
		'OZ_SSH_COMMAND',
	];

	/**
	 * @dataProvider provideEveryUsedCodeIsTranslatedCases
	 */
	public function testEveryUsedCodeIsTranslated(string $lang): void
	{
		$catalog = Settings::load('lang/oz.' . $lang);
		$missing = \array_values(\array_diff(self::usedCodes(), \array_keys($catalog)));

		self::assertSame([], $missing, \sprintf('Missing in lang/oz.%s.php.', $lang));
	}

	public static function provideEveryUsedCodeIsTranslatedCases(): iterable
	{
		yield 'en' => ['en'];

		yield 'fr' => ['fr'];
	}

	public function testTranslationsUseTheSamePlaceholders(): void
	{
		$en = Settings::load('lang/oz.en');
		$fr = Settings::load('lang/oz.fr');

		foreach ($en as $code => $text) {
			self::assertArrayHasKey($code, $fr);
			self::assertSame(self::placeholders($text), self::placeholders($fr[$code]), $code);
		}
	}

	public function testDefaultLanguageIsShipped(): void
	{
		$default = Settings::get('lang/oz.lang.list', 'default');

		self::assertNotEmpty(Settings::load('lang/oz.' . $default));
	}

	/**
	 * @return list<string>
	 */
	private static function usedCodes(): array
	{
		$settings_dir = OZ_OZONE_DIR . 'oz_settings';
		$db_dir       = OZ_OZONE_DIR . 'Db';
		// the test kit names environment variables (OZ_TEST_*, service addresses), never messages
		$testing_dir  = OZ_OZONE_DIR . 'Testing';
		$excluded     = \array_flip(self::NOT_MESSAGES);
		$codes        = [];

		foreach (\glob($settings_dir . DS . '*.php') ?: [] as $file) {
			\preg_match_all("~^\\s*'(OZ_[A-Z0-9_]+)'\\s*=>~m", (string) \file_get_contents($file), $m);
			$excluded += \array_flip($m[1]);
		}

		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(OZ_OZONE_DIR));

		/** @var SplFileInfo $file */
		foreach ($files as $file) {
			$path = $file->getPathname();

			if (
				!\preg_match('~\.(php|blate)$~', $path)
				|| \str_starts_with($path, $settings_dir)
				|| \str_starts_with($path, $db_dir)
				|| \str_starts_with($path, $testing_dir)
			) {
				continue;
			}

			\preg_match_all("~'(OZ_[A-Z0-9_]+)'~", (string) \file_get_contents($path), $m);

			foreach ($m[1] as $code) {
				if (!isset($excluded[$code])) {
					$codes[$code] = true;
				}
			}
		}

		$codes = \array_keys($codes);
		\sort($codes);

		return $codes;
	}

	/**
	 * @return list<string>
	 */
	private static function placeholders(string $text): array
	{
		\preg_match_all('~\{(\w+)~', $text, $m);

		$names = \array_values(\array_unique($m[1]));
		\sort($names);

		return $names;
	}
}
