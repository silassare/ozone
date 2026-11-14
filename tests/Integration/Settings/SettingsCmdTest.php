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

namespace OZONE\Tests\Integration\Settings;

use Override;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Verifies `oz settings set` and `oz settings unset` commands.
 *
 * Tests cover:
 *  - Setting a string value (stateful: data/{scope}/settings)
 *  - Setting boolean, integer, and null values (auto-parsed)
 *  - Setting a JSON array and object value
 *  - Unsetting a key removes it from the settings file
 *  - Setting with --source writes to the version-controlled source dir (app/settings/)
 *  - Setting with a scope name writes to the correct scope stateful settings dir
 *  - `oz settings export` answering the declarations, and `--client` the keys a browser may read
 *
 * @internal
 */
#[CoversNothing]
final class SettingsCmdTest extends TestCase
{
	private static OZTestProject $proj;

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::$proj = OZTestProject::create('settings-cmd-test');

		// Pre-seed the app-level settings groups that the tests will modify.
		// Settings::set/unset require the group to already be declared somewhere;
		// writing the files directly here satisfies that contract.
		self::$proj->setSetting('app.custom', 'SEED_KEY', 'seed');
		self::$proj->setSetting('app.unset', 'SEED_KEY', 'seed');
		self::$proj->setSetting('app.unset2', 'SEED_KEY', 'seed');

		// Add a scope so we can test scope-scoped settings
		self::$proj->oz('scopes', 'add', '--name=myweb', '--origin=http://localhost:3000', '--api=false')
			->mustRun();
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		self::$proj->destroy();
		parent::tearDownAfterClass();
	}

	public function testSetStringValue(): void
	{
		self::$proj->oz('settings', 'set', '-g=app.custom', '-k=MY_KEY', '-v=hello world')
			->mustRun();
		$settings = require self::settingsPath('app.custom');
		self::assertSame('hello world', $settings['MY_KEY']);
	}

	public function testSetBoolTrueValue(): void
	{
		self::$proj->oz('settings', 'set', '-g=app.custom', '-k=MY_BOOL_TRUE', '-v=true')
			->mustRun();
		$settings = require self::settingsPath('app.custom');
		self::assertTrue($settings['MY_BOOL_TRUE']);
	}

	public function testSetBoolFalseValue(): void
	{
		self::$proj->oz('settings', 'set', '-g=app.custom', '-k=MY_BOOL_FALSE', '-v=false')
			->mustRun();
		$settings = require self::settingsPath('app.custom');
		self::assertFalse($settings['MY_BOOL_FALSE']);
	}

	public function testSetNullValue(): void
	{
		self::$proj->oz('settings', 'set', '-g=app.custom', '-k=MY_NULL', '-v=null')
			->mustRun();
		$settings = require self::settingsPath('app.custom');
		self::assertNull($settings['MY_NULL']);
	}

	public function testSetIntegerValue(): void
	{
		self::$proj->oz('settings', 'set', '-g=app.custom', '-k=MY_INT', '-v=42')
			->mustRun();
		$settings = require self::settingsPath('app.custom');
		self::assertSame(42, $settings['MY_INT']);
	}

	public function testSetFloatValue(): void
	{
		self::$proj->oz('settings', 'set', '-g=app.custom', '-k=MY_FLOAT', '-v=3.14')
			->mustRun();
		$settings = require self::settingsPath('app.custom');
		self::assertSame(3.14, $settings['MY_FLOAT']);
	}

	public function testSetJsonArrayValue(): void
	{
		self::$proj->oz('settings', 'set', '-g=app.custom', '-k=MY_ARRAY', '-v=["a","b","c"]')
			->mustRun();
		$settings = require self::settingsPath('app.custom');
		self::assertSame(['a', 'b', 'c'], $settings['MY_ARRAY']);
	}

	public function testSetJsonObjectValue(): void
	{
		self::$proj->oz('settings', 'set', '-g=app.custom', '-k=MY_OBJ', '-v={"x":1,"y":2}')
			->mustRun();
		$settings = require self::settingsPath('app.custom');
		self::assertSame(['x' => 1, 'y' => 2], $settings['MY_OBJ']);
	}

	public function testUnsetRemovesKey(): void
	{
		// First write a key
		self::$proj->oz('settings', 'set', '-g=app.unset', '-k=TO_REMOVE', '-v=temporary')
			->mustRun();
		$before = require self::settingsPath('app.unset');
		self::assertArrayHasKey('TO_REMOVE', $before);

		// Then unset it
		self::$proj->oz('settings', 'unset', '-g=app.unset', '-k=TO_REMOVE')
			->mustRun();

		// Reload the file (require_once caches, so use require with a fresh import)
		$after = (static fn ($file) => require $file)(self::settingsPath('app.unset'));
		self::assertArrayNotHasKey('TO_REMOVE', $after);
	}

	public function testUnsetLeavesOtherKeysIntact(): void
	{
		self::$proj->oz('settings', 'set', '-g=app.unset2', '-k=KEEP_ME', '-v=keep')
			->mustRun();
		self::$proj->oz('settings', 'set', '-g=app.unset2', '-k=REMOVE_ME', '-v=remove')
			->mustRun();
		self::$proj->oz('settings', 'unset', '-g=app.unset2', '-k=REMOVE_ME')
			->mustRun();

		$settings = (static fn ($file) => require $file)(self::settingsPath('app.unset2'));
		self::assertArrayHasKey('KEEP_ME', $settings);
		self::assertArrayNotHasKey('REMOVE_ME', $settings);
	}

	public function testSetWithScopeWritesToScopeDir(): void
	{
		self::$proj->oz('settings', 'set', '-s=myweb', '-g=oz.request', '-k=OZ_DEFAULT_ORIGIN', '-v=http://scoped.example.com')
			->mustRun();

		// Runtime writes always go to the stateful directory (data/), not the source tree, and the
		// layout there is scope first: data/{scope}/settings.
		$scope_file = self::$proj->getPath()
			. \DIRECTORY_SEPARATOR . 'data'
			. \DIRECTORY_SEPARATOR . 'myweb'
			. \DIRECTORY_SEPARATOR . 'settings'
			. \DIRECTORY_SEPARATOR . 'oz.request.php';

		self::assertFileExists($scope_file);
		$settings = (static fn ($file) => require $file)($scope_file);
		self::assertSame('http://scoped.example.com', $settings['OZ_DEFAULT_ORIGIN']);
	}

	public function testSetWithSourceFlagWritesToSourceDir(): void
	{
		self::$proj->oz('settings', 'set', '--source', '-g=app.custom', '-k=SOURCE_KEY', '-v=source-value')
			->mustRun();

		// --source writes to app/settings/, not data/{scope}/settings.
		$source_file = self::$proj->getPath()
			. \DIRECTORY_SEPARATOR . 'app'
			. \DIRECTORY_SEPARATOR . 'settings'
			. \DIRECTORY_SEPARATOR . 'app.custom.php';

		self::assertFileExists($source_file);
		$settings = (static fn ($file) => require $file)($source_file);
		self::assertSame('source-value', $settings['SOURCE_KEY']);
	}

	/**
	 * The export is the declarations, read from the settings files themselves.
	 */
	public function testExportAnswersTheDeclarationsWithTheirDocumentation(): void
	{
		$proc = self::$proj->oz('settings', 'export', '--json');

		$proc->mustRun();

		$data  = \json_decode($proc->getOutput(), true, 512, \JSON_THROW_ON_ERROR)['data'];
		$found = null;

		foreach ($data['settings'] as $setting) {
			if ('oz.users' === $setting['group'] && 'OZ_USER_PASS_MIN_LENGTH' === $setting['key']) {
				$found = $setting;
			}
		}

		self::assertNotNull($found);
		self::assertSame(6, $found['default']);
		self::assertSame('int', $found['type']);
		self::assertStringContainsString('Minimum length for user password', $found['doc']);
		self::assertContains('oz.auth.methods', $data['maps']);
	}

	/**
	 * Without `--json` a person reads it, which is a path of its own and crashes silently if left
	 * untested: a count per group, then what the whole export holds.
	 */
	public function testExportIsReadableWithoutJson(): void
	{
		$proc = self::$proj->oz('settings', 'export');

		$proc->mustRun();

		$out = $proc->getOutput();

		self::assertMatchesRegularExpression('~oz\.users\s+\d+ keys,\s+\d+ documented~', $out);
		self::assertStringContainsString('for the client', $out);
		self::assertStringContainsString('maps, whose keys are expressions', $out);
	}

	/**
	 * A secret is in the same files as everything else, so what the client half answers is the
	 * marked keys and nothing near them.
	 */
	public function testExportForTheClientAnswersTheMarkedKeysAndNoSecret(): void
	{
		$proc = self::$proj->oz('settings', 'export', '--client', '--json');

		$proc->mustRun();

		$settings = \json_decode($proc->getOutput(), true, 512, \JSON_THROW_ON_ERROR)['data']['settings'];

		self::assertSame('x-ozone-api-key', $settings['oz.auth']['OZ_AUTH_API_KEY_HEADER_NAME']);
		self::assertSame(1000 * 1000, $settings['oz.files']['OZ_UPLOAD_CHUNK_MAX_SIZE']);
		self::assertArrayNotHasKey('oz.db', $settings);
		self::assertArrayNotHasKey('oz.cron', $settings);
		self::assertStringNotContainsString('OZ_DB_PASS', $proc->getOutput());
	}

	/**
	 * The whole point: a client reads this instead of holding a copy of the default, so a project's
	 * override has to be what it answers.
	 */
	public function testExportForTheClientFollowsAProjectsOverride(): void
	{
		$proj = self::$proj;

		$proj->oz('settings', 'set', '--group=oz.auth', '--key=OZ_AUTH_API_KEY_HEADER_NAME', '--value=x-project-key')
			->mustRun();

		try {
			$proc = $proj->oz('settings', 'export', '--client', '--json');

			$proc->mustRun();

			$settings = \json_decode($proc->getOutput(), true, 512, \JSON_THROW_ON_ERROR)['data']['settings'];

			self::assertSame('x-project-key', $settings['oz.auth']['OZ_AUTH_API_KEY_HEADER_NAME']);
		} finally {
			$proj->oz('settings', 'unset', '--group=oz.auth', '--key=OZ_AUTH_API_KEY_HEADER_NAME')
				->mustRun();
		}
	}

	private static function settingsPath(string $group): string
	{
		// Runtime writes always go to the stateful directory so that source-controlled defaults in
		// app/settings/ are never modified by oz commands: data/{scope}/settings, root scope here.
		return self::$proj->getPath()
			. \DIRECTORY_SEPARATOR . 'data'
			. \DIRECTORY_SEPARATOR . 'root'
			. \DIRECTORY_SEPARATOR . 'settings'
			. \DIRECTORY_SEPARATOR . $group . '.php';
	}
}
