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
use OZONE\Core\App\SettingsSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SettingsSchema::class)]
final class SettingsSchemaTest extends TestCase
{
	public function testEveryKeyOZoneDeclaresIsReadWithItsTypeAndItsDocumentation(): void
	{
		$found = self::setting('oz.users', 'OZ_USER_PASS_MIN_LENGTH');

		self::assertSame('int', $found['type']);
		self::assertSame(6, $found['default']);
		self::assertFalse($found['from_env']);
		self::assertFalse($found['client']);
		self::assertStringContainsString('Minimum length for user password', $found['doc']);
	}

	/**
	 * A doc comment lives inside the returned array, so it belongs to no declaration and no
	 * reflection reaches it: this is what says the file is read as text.
	 */
	public function testTheDocumentationIsTheCommentAboveTheKeyAndTagsAreNotPartOfIt(): void
	{
		$found = self::setting('oz.auth', 'OZ_AUTH_API_KEY_HEADER_NAME');

		self::assertSame('Authentication by api key header name.', $found['doc']);
		self::assertTrue($found['client']);
	}

	public function testADefaultWrittenAsAnExpressionIsAnsweredResolved(): void
	{
		$found = self::setting('oz.files', 'OZ_UPLOAD_CHUNK_MAX_SIZE');

		self::assertSame(1000 * 1000, $found['default']);
		self::assertSame('1000 * 1000', $found['source']);
	}

	/**
	 * Running a settings file resolves `env()` as well, and on a configured machine that is the
	 * deployment's secret: the key is answered with its source text and nothing else.
	 */
	public function testAKeyReadFromTheEnvironmentIsAnsweredWithoutItsValue(): void
	{
		$found = self::setting('oz.cron', 'OZ_CRON_WEB_KEY');

		self::assertTrue($found['from_env']);
		self::assertNull($found['default']);
		self::assertSame('unknown', $found['type']);
		self::assertStringContainsString('env(', $found['source']);
	}

	public function testAGroupWhoseKeysAreExpressionsIsAMapAndNeverASchema(): void
	{
		$schema = SettingsSchema::describe();
		$groups = \array_column($schema['settings'], 'group');

		self::assertContains('oz.auth.methods', $schema['maps']);
		self::assertNotContains('oz.auth.methods', $groups);
	}

	/**
	 * A group of OZone's own, written for the test: the framework's files carry no merge strategy,
	 * so asserting against them would assert nothing.
	 *
	 * The directory is created under OZone's own, because that is what makes it an **internal**
	 * source and so one the schema reads -- a project's is deliberately not read ({@see
	 * SettingsSchema::describe()}).
	 */
	public function testTheMergeStrategyIsNeverASettingAndADocumentedKeyBesideItIsOne(): void
	{
		$dir = \rtrim(OZ_OZONE_DIR, '/\\') . \DIRECTORY_SEPARATOR . 'oz_settings_test_' . \bin2hex(\random_bytes(6));
		$file = $dir . \DIRECTORY_SEPARATOR . 'oztest.merge.php';

		\mkdir($dir, 0o775, true);
		\file_put_contents($file, <<<'PHP'
			<?php return [
				'@merge' => ['OZTEST_KEPT' => 'replace'],
				/**
				 * A key documented where it is declared.
				 *
				 * @client for the test
				 */
				'OZTEST_KEPT' => 'yes',
			];
			PHP);

		try {
			Settings::addSource($dir);

			$keys = [];

			foreach (SettingsSchema::describe()['settings'] as $setting) {
				if ('oztest.merge' === $setting['group']) {
					$keys[$setting['key']] = $setting;
				}
			}

			self::assertArrayNotHasKey(Settings::MERGE_KEY, $keys);
			self::assertSame(['OZTEST_KEPT'], \array_keys($keys));
			self::assertSame('yes', $keys['OZTEST_KEPT']['default']);
			self::assertSame('A key documented where it is declared.', $keys['OZTEST_KEPT']['doc']);
			self::assertTrue($keys['OZTEST_KEPT']['client']);
		} finally {
			\unlink($file);
			\rmdir($dir);
		}
	}

	public function testTheClientExportCarriesTheMarkedKeysAndFollowsAnOverride(): void
	{
		$group = 'oz.auth';
		$key   = 'OZ_AUTH_API_KEY_HEADER_NAME';

		self::assertSame('x-ozone-api-key', SettingsSchema::forClient()['settings'][$group][$key] ?? null);

		try {
			Settings::set($group, $key, 'x-project-key');

			self::assertSame('x-project-key', SettingsSchema::forClient()['settings'][$group][$key] ?? null);
		} finally {
			Settings::unset($group, $key);
		}
	}

	public function testTheClientExportLeavesOutWhatIsNotMarked(): void
	{
		$settings = SettingsSchema::forClient()['settings'];

		self::assertArrayHasKey('OZ_UPLOAD_FILE_MAX_SIZE', $settings['oz.files'] ?? []);
		self::assertArrayNotHasKey('OZ_DB_PASS', $settings['oz.db'] ?? []);
		self::assertArrayNotHasKey('oz.db', $settings);
	}

	/**
	 * The environment is where a deployment's secrets live, so one marked for the client by accident
	 * would reach every browser: it is an error naming the key, never an exported value.
	 */
	public function testASettingMarkedForTheClientAndReadFromTheEnvironmentIsRefused(): void
	{
		$dir = self::tempSource('oztest.client.php', <<<'PHP'
			<?php return [
				/**
				 * A value a browser may read.
				 *
				 * @client
				 */
				'OZTEST_PUBLIC' => 'yes',
				/**
				 * A secret marked by mistake.
				 *
				 * @client
				 */
				'OZTEST_SECRET' => env('OZTEST_SECRET', 'shh'),
			];
			PHP);

		try {
			Settings::addSource($dir);

			$export = SettingsSchema::forClient();

			self::assertSame('yes', $export['settings']['oztest.client']['OZTEST_PUBLIC'] ?? null);
			self::assertArrayNotHasKey('OZTEST_SECRET', $export['settings']['oztest.client'] ?? []);

			$reasons = \array_column($export['errors'], 'reason');

			self::assertNotEmpty($reasons);
			self::assertStringContainsString('OZTEST_SECRET', \implode("\n", $reasons));
		} finally {
			\unlink($dir . \DIRECTORY_SEPARATOR . 'oztest.client.php');
			\rmdir($dir);
		}
	}

	/**
	 * A project's file may be written in the long array syntax, which closes with `)` where the
	 * short one closes with `]`: the reader remembers what opened, so a group written that way is
	 * read and not silently answered as empty.
	 */
	public function testAGroupWrittenInTheLongArraySyntaxIsReadToo(): void
	{
		$dir = self::tempSource('oztest.long.php', <<<'PHP'
			<?php return array(
				/**
				 * A key in the long syntax.
				 *
				 * @client for the test
				 */
				'OZTEST_LONG' => array('a', 'b'),
				'OZTEST_PLAIN' => 1,
			);
			PHP);

		try {
			Settings::addSource($dir);

			self::assertSame(
				['a', 'b'],
				SettingsSchema::forClient()['settings']['oztest.long']['OZTEST_LONG'] ?? null
			);
			self::assertArrayNotHasKey(
				'OZTEST_PLAIN',
				SettingsSchema::forClient()['settings']['oztest.long'] ?? []
			);
		} finally {
			\unlink($dir . \DIRECTORY_SEPARATOR . 'oztest.long.php');
			\rmdir($dir);
		}
	}

	/**
	 * One entry of the schema, by group and key.
	 *
	 * @return array{
	 *     group: string,
	 *     key: string,
	 *     type: string,
	 *     default: mixed,
	 *     source: string,
	 *     from_env: bool,
	 *     doc: string,
	 *     client: bool,
	 * }
	 */
	private static function setting(string $group, string $key): array
	{
		foreach (SettingsSchema::describe()['settings'] as $setting) {
			if ($group === $setting['group'] && $key === $setting['key']) {
				return $setting;
			}
		}

		self::fail(\sprintf('No setting "%s" in "%s".', $key, $group));
	}

	/** A settings source directory of its own, holding one file. */
	private static function tempSource(string $name, string $content): string
	{
		$dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz_schema_' . \bin2hex(\random_bytes(6));

		\mkdir($dir, 0o775, true);
		\file_put_contents($dir . \DIRECTORY_SEPARATOR . $name, $content);

		return $dir;
	}
}
