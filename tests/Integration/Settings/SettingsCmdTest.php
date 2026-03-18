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

use OZONE\Tests\Integration\Support\OZTestProject;
use PHPUnit\Framework\TestCase;

/**
 * Verifies `oz settings set` and `oz settings unset` commands.
 *
 * Tests cover:
 *  - Setting a string value
 *  - Setting boolean, integer, and null values (auto-parsed)
 *  - Setting a JSON array and object value
 *  - Unsetting a key removes it from the settings file
 *  - Setting with a scope name writes to the correct scope's settings dir
 *
 * @internal
 *
 * @coversNothing
 */
final class SettingsCmdTest extends TestCase
{
	private static OZTestProject $proj;

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

	public static function tearDownAfterClass(): void
	{
		self::$proj->destroy();
		parent::tearDownAfterClass();
	}

	// -------------------------------------------------------------------------
	// oz settings set -- value parsing
	// -------------------------------------------------------------------------

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

	// -------------------------------------------------------------------------
	// oz settings unset
	// -------------------------------------------------------------------------

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
		$after = (static function ($file) {
			return require $file;
		})(self::settingsPath('app.unset'));
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

		$settings = (static function ($file) {
			return require $file;
		})(self::settingsPath('app.unset2'));
		self::assertArrayHasKey('KEEP_ME', $settings);
		self::assertArrayNotHasKey('REMOVE_ME', $settings);
	}

	// -------------------------------------------------------------------------
	// oz settings with --scope
	// -------------------------------------------------------------------------

	public function testSetWithScopeWritesToScopeDir(): void
	{
		self::$proj->oz('settings', 'set', '-s=myweb', '-g=oz.request', '-k=OZ_DEFAULT_ORIGIN', '-v=http://scoped.example.com')
			->mustRun();

		$scope_file = self::$proj->getPath()
			. \DIRECTORY_SEPARATOR . 'scopes'
			. \DIRECTORY_SEPARATOR . 'myweb'
			. \DIRECTORY_SEPARATOR . 'settings'
			. \DIRECTORY_SEPARATOR . 'oz.request.php';

		self::assertFileExists($scope_file);
		$settings = (static function ($file) {
			return require $file;
		})($scope_file);
		self::assertSame('http://scoped.example.com', $settings['OZ_DEFAULT_ORIGIN']);
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	private static function settingsPath(string $group): string
	{
		return self::$proj->getPath()
			. \DIRECTORY_SEPARATOR . 'app'
			. \DIRECTORY_SEPARATOR . 'settings'
			. \DIRECTORY_SEPARATOR . $group . '.php';
	}
}
