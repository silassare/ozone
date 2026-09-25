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

namespace OZONE\Tests\Forms;

use OZONE\Core\App\Settings;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Forms\Form;
use PHPUnit\Framework\TestCase;

/**
 * A discovered type carries the rules it takes from the settings, so a client checks a value
 * the way the server will without knowing the settings itself.
 *
 * @covers \OZONE\Core\Columns\Types\TypeGender
 * @covers \OZONE\Core\Columns\Types\TypePassword
 * @covers \OZONE\Core\Columns\Types\TypeUsername
 * @covers \OZONE\Core\Forms\Field
 *
 * @internal
 */
final class FrontendTypeOptionsTest extends TestCase
{
	protected function tearDown(): void
	{
		Settings::unset('oz.users', 'OZ_USER_PASS_MIN_LENGTH');
		Settings::unset('oz.users', 'OZ_USER_NAME_PATTERN');
		Settings::unset('oz.users', 'OZ_USER_ALLOWED_GENDERS');
	}

	/** A password that sets no length checks the settings' one, and says so. */
	public function testAPasswordSendsTheLengthsOfTheSettings(): void
	{
		Settings::set('oz.users', 'OZ_USER_PASS_MIN_LENGTH', 9);

		$form = new Form();
		$form->password('pass');

		$type = self::typeOf($form, 'pass');

		self::assertSame(9, $type['min']);
		self::assertSame((int) Settings::get('oz.users', 'OZ_USER_PASS_MAX_LENGTH'), $type['max']);
	}

	/** Its own lengths win over the settings'. */
	public function testAPasswordSendsItsOwnLengths(): void
	{
		$form = new Form();
		$form->field('pass')->type((new TypePassword())->min(4)->max(12));

		$type = self::typeOf($form, 'pass');

		self::assertSame(4, $type['min']);
		self::assertSame(12, $type['max']);
	}

	public function testAUsernameSendsItsLengthsAndItsPattern(): void
	{
		$form = new Form();
		$form->username('name');

		$type = self::typeOf($form, 'name');

		self::assertSame((int) Settings::get('oz.users', 'OZ_USER_NAME_MIN_LENGTH'), $type['min']);
		self::assertSame((int) Settings::get('oz.users', 'OZ_USER_NAME_MAX_LENGTH'), $type['max']);
		self::assertSame(Settings::get('oz.users', 'OZ_USER_NAME_PATTERN'), $type['pattern']);
	}

	/**
	 * A pattern JavaScript would read differently is not sent: a client running it anyway would disagree
	 * with the server, so the characters of a username are left to the server then.
	 */
	public function testAUsernameKeepsANonPortablePatternToItself(): void
	{
		// A possessive quantifier: PCRE reads it, JavaScript does not.
		Settings::set('oz.users', 'OZ_USER_NAME_PATTERN', '~^[a-z]++$~');

		$form = new Form();
		$form->username('name');

		self::assertArrayNotHasKey('pattern', self::typeOf($form, 'name'));
	}

	public function testAGenderSendsTheGendersItAccepts(): void
	{
		Settings::set('oz.users', 'OZ_USER_ALLOWED_GENDERS', ['a', 'b']);

		$form = new Form();
		$form->gender('g');

		self::assertSame(['a', 'b'], self::typeOf($form, 'g')['allowed']);
	}

	/** @return array<string, mixed> */
	private static function typeOf(Form $form, string $ref): array
	{
		/** @var array<string, array<string, array<string, mixed>>> $bundle */
		$bundle = \json_decode((string) \json_encode($form->toArray()), true);

		return $bundle['fields'][$ref]['type'];
	}
}
