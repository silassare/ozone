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

use OZONE\Core\App\SettingsGroup;
use PHPUnit\Framework\TestCase;

/**
 * Class SettingsGroupTest.
 *
 * Verifies {@see SettingsGroup} key semantics:
 *
 * - **Plain keys** (`[a-zA-Z0-9_]`): single segment, no special handling.
 * - **Deep keys** (dot-separated plain segments): full DotPath traversal into nested arrays.
 *   Consuming projects rely on this — it MUST NOT be silently broken by any "flat bypass".
 * - **Special-char single keys** (hyphens, backslashes, colons, etc.): wrapped by
 *   normalizeKey() into bracket notation and resolved via direct array access to work
 *   around a known StoreTrait::parentOf() limitation for single bracket-quoted segments.
 *
 * @internal
 *
 * @coversNothing
 */
final class SettingsGroupTest extends TestCase
{
	// -------------------------------------------------------------------------
	// Plain single keys
	// -------------------------------------------------------------------------

	public function testHasPlainKey(): void
	{
		$sg = new SettingsGroup(['OZ_DB_HOST' => 'localhost', 'OZ_DB_PORT' => 3306]);

		self::assertTrue($sg->has('OZ_DB_HOST'));
		self::assertTrue($sg->has('OZ_DB_PORT'));
		self::assertFalse($sg->has('OZ_DB_PASS'));
	}

	public function testGetPlainKey(): void
	{
		$sg = new SettingsGroup(['OZ_DB_HOST' => 'localhost', 'OZ_DB_PORT' => 3306]);

		self::assertSame('localhost', $sg->get('OZ_DB_HOST'));
		self::assertSame(3306, $sg->get('OZ_DB_PORT'));
		self::assertNull($sg->get('OZ_DB_PASS'));
		self::assertSame('127.0.0.1', $sg->get('OZ_DB_PASS', '127.0.0.1'));
	}

	// -------------------------------------------------------------------------
	// Deep dot-path keys — consuming projects rely on this
	// -------------------------------------------------------------------------

	public function testHasDeepKey(): void
	{
		$sg = new SettingsGroup(['database' => ['host' => 'db.local', 'port' => 5432]]);

		self::assertTrue($sg->has('database.host'));
		self::assertTrue($sg->has('database.port'));
		self::assertFalse($sg->has('database.pass'));
	}

	public function testGetDeepKey(): void
	{
		$sg = new SettingsGroup(['database' => ['host' => 'db.local', 'port' => 5432]]);

		self::assertSame('db.local', $sg->get('database.host'));
		self::assertSame(5432, $sg->get('database.port'));
		self::assertNull($sg->get('database.pass'));
		self::assertSame('secret', $sg->get('database.pass', 'secret'));
	}

	public function testHasThreeLevelDeepKey(): void
	{
		$sg = new SettingsGroup(['a' => ['b' => ['c' => 'deep']]]);

		self::assertTrue($sg->has('a.b.c'));
		self::assertFalse($sg->has('a.b.d'));
		self::assertFalse($sg->has('a.x.c'));
	}

	public function testGetThreeLevelDeepKey(): void
	{
		$sg = new SettingsGroup(['a' => ['b' => ['c' => 'deep']]]);

		self::assertSame('deep', $sg->get('a.b.c'));
		self::assertNull($sg->get('a.b.d'));
	}

	// -------------------------------------------------------------------------
	// Special-char single keys (hyphens, backslashes, colons, etc.)
	// -------------------------------------------------------------------------

	public function testHasHyphenatedKey(): void
	{
		$sg = new SettingsGroup(['test-wizard' => 'WizardClass', 'normal' => 'NormalClass']);

		self::assertTrue($sg->has('test-wizard'), 'Hyphenated key must be found.');
		self::assertTrue($sg->has('normal'));
		self::assertFalse($sg->has('missing-key'));
	}

	public function testGetHyphenatedKey(): void
	{
		$sg = new SettingsGroup(['test-wizard' => 'WizardClass', 'normal' => 'NormalClass']);

		self::assertSame('WizardClass', $sg->get('test-wizard'), 'Hyphenated key must be retrieved correctly.');
		self::assertSame('NormalClass', $sg->get('normal'));
		self::assertNull($sg->get('missing-key'));
		self::assertSame('default', $sg->get('missing-key', 'default'));
	}

	public function testHasFqcnKey(): void
	{
		$sg = new SettingsGroup([
			'Some\Vendor\Class'  => true,
			'Other\Class'        => false,
		]);

		self::assertTrue($sg->has('Some\Vendor\Class'), 'FQCN key (backslash) must be found.');
		self::assertTrue($sg->has('Other\Class'));
		self::assertFalse($sg->has('Missing\Class'));
	}

	public function testGetFqcnKey(): void
	{
		$sg = new SettingsGroup([
			'Some\Vendor\Class'  => true,
			'Other\Class'        => false,
		]);

		self::assertTrue($sg->get('Some\Vendor\Class'));
		self::assertFalse($sg->get('Other\Class'));
		self::assertNull($sg->get('Missing\Class'));
	}

	public function testHasColonKey(): void
	{
		// Provider slugs like 'auth:provider:email' use colons.
		$sg = new SettingsGroup(['auth:provider:email' => 'EmailProvider']);

		self::assertTrue($sg->has('auth:provider:email'), 'Colon key must be found.');
		self::assertFalse($sg->has('auth:provider:sms'));
	}

	public function testGetColonKey(): void
	{
		$sg = new SettingsGroup(['auth:provider:email' => 'EmailProvider']);

		self::assertSame('EmailProvider', $sg->get('auth:provider:email'));
		self::assertNull($sg->get('auth:provider:sms'));
	}

	// -------------------------------------------------------------------------
	// set() / remove() round-trip for special-char keys
	// -------------------------------------------------------------------------

	public function testSetAndHasSpecialCharKey(): void
	{
		$sg = new SettingsGroup([]);
		$sg->set('auth:provider:email', 'EmailProvider');
		$sg->set('test-wizard', 'WizardClass');

		self::assertTrue($sg->has('auth:provider:email'));
		self::assertTrue($sg->has('test-wizard'));
		self::assertSame('EmailProvider', $sg->get('auth:provider:email'));
		self::assertSame('WizardClass', $sg->get('test-wizard'));
	}

	public function testRemoveSpecialCharKey(): void
	{
		$sg = new SettingsGroup(['test-wizard' => 'WizardClass', 'keep' => 'KeepClass']);
		$sg->remove('test-wizard');

		self::assertFalse($sg->has('test-wizard'));
		self::assertTrue($sg->has('keep'));
	}

	// -------------------------------------------------------------------------
	// Mixed: both special-char and deep keys in the same group
	// -------------------------------------------------------------------------

	public function testMixedKeyTypes(): void
	{
		$sg = new SettingsGroup([
			'simple'      => 'plain',
			'nested'      => ['value' => 42],
			'my-provider' => 'ProviderClass',
			'Ns\FQN'      => true,
		]);

		self::assertSame('plain', $sg->get('simple'));
		self::assertSame(42, $sg->get('nested.value'), 'Deep key must still traverse nested array.');
		self::assertSame('ProviderClass', $sg->get('my-provider'), 'Hyphenated key must resolve.');
		self::assertTrue($sg->get('Ns\FQN'), 'FQCN key must resolve.');
	}
}
