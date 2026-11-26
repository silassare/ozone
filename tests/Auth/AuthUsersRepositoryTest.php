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

namespace OZONE\Tests\Auth;

use Gobl\CRUD\Exceptions\CRUDException;
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\AuthUsersRepository;
use OZONE\Core\Auth\UsernameUtils;
use OZONE\Core\Columns\Types\TypeEmail;
use OZONE\Core\Db\OZUsername;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Tests\Support\TestAuthUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class AuthUsersRepositoryTest.
 *
 * @internal
 */
#[CoversClass(UsernameUtils::class)]
#[CoversClass(AuthUsersRepository::class)]
final class AuthUsersRepositoryTest extends TestCase
{
	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		db()->executeMulti(db()->getGenerator()->buildDatabase());
	}

	public function testOnlyUserTablesAreSupported(): void
	{
		self::assertTrue(AuthUsersRepository::isTableSupported(db()->getTableOrFail('oz_users')));
		self::assertFalse(AuthUsersRepository::isTableSupported(db()->getTableOrFail('oz_sessions')));
	}

	#[Override]
	protected function tearDown(): void
	{
		Settings::unset('oz.auth.users', 'OZ_AUTH_USER_DEFAULT_TYPE');
		Settings::unset('oz.auth.users', 'OZ_AUTH_USER_DEFAULT_TABLE');
		Settings::unset('oz.auth.users.repositories', 'admin');

		parent::tearDown();
	}

	public function testTheDefaultTypeIsTheProjectsSettingWhereverNoTypeIsNamed(): void
	{
		self::assertSame('user', AuthUsersRepository::defaultType());

		Settings::set('oz.auth.users.repositories', 'admin', AuthUsersRepository::class);
		Settings::set('oz.auth.users', 'OZ_AUTH_USER_DEFAULT_TYPE', 'admin');

		self::assertSame('admin', AuthUsersRepository::defaultType());
		// The email column's "already registered" is checked against that type's auth users.
		self::assertSame('admin', new TypeEmail()->configure(['registered' => false])->getOption('registered_as'));
	}

	public function testADefaultTypeNoRepositoryServesIsRefused(): void
	{
		Settings::set('oz.auth.users', 'OZ_AUTH_USER_DEFAULT_TYPE', 'nobody');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('"nobody" is served by no repository');

		AuthUsersRepository::defaultType();
	}

	public function testWithoutTheDefaultTableTheUserTypeIsServedByNoRepository(): void
	{
		Settings::set('oz.auth.users', 'OZ_AUTH_USER_DEFAULT_TABLE', false);

		self::assertNull(AuthUsers::repositoryClass('user'));

		try {
			AuthUsersRepository::defaultType();
			self::fail('The default type was answered with no table to serve it.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('OZ_AUTH_USER_DEFAULT_TABLE', $e->getMessage());
		}

		// A project naming one of its own types is served.
		Settings::set('oz.auth.users.repositories', 'admin', AuthUsersRepository::class);
		Settings::set('oz.auth.users', 'OZ_AUTH_USER_DEFAULT_TYPE', 'admin');

		self::assertSame('admin', AuthUsersRepository::defaultType());
	}

	public function testRepositoryIsResolvedByUserType(): void
	{
		self::assertInstanceOf(AuthUsersRepository::class, AuthUsersRepository::get('user'));
	}

	public function testUnknownUsernameDoesNotExist(): void
	{
		self::assertFalse(UsernameUtils::exists('nobody_' . \bin2hex(\random_bytes(4))));
		self::assertNull(UsernameUtils::get('nobody_' . \bin2hex(\random_bytes(4))));
	}

	public function testAUsernameIsClaimedForTheUserThatAsked(): void
	{
		$name = 'claimed_' . \bin2hex(\random_bytes(4));
		$user = new TestAuthUser('customer', '42');

		self::assertTrue(UsernameUtils::claim($name, $user));
		self::assertTrue(UsernameUtils::exists($name));

		$entry = UsernameUtils::get($name);

		// The holder is recorded as a morph, so an entity of any repository can be one.
		self::assertInstanceOf(OZUsername::class, $entry);
		self::assertSame('customer', $entry->getUserType());
		self::assertSame('42', $entry->getUserID());
	}

	public function testTheNameIsOnlyWritableWhileAClaimIsBeingMade(): void
	{
		// Claiming registers the handler that allows the name -- a primary key, refused by default --
		// to be written, and the handler stays registered for the process. What it answers for is the
		// claim running, so a row written here by anything else is refused as it was before claiming
		// existed at all.
		self::assertTrue(UsernameUtils::claim('scoped_' . \bin2hex(\random_bytes(4)), new TestAuthUser()));

		$this->expectException(CRUDException::class);

		new OZUsername()
			->setName('bypass_' . \bin2hex(\random_bytes(4)))
			->save();
	}

	public function testAUsernameAlreadyHeldIsRefusedToEveryoneElse(): void
	{
		$name = 'taken_' . \bin2hex(\random_bytes(4));

		self::assertTrue(UsernameUtils::claim($name, new TestAuthUser('customer', '1')));

		// Including the user that holds it: claiming is taking a free name, not an idempotent write.
		self::assertFalse(UsernameUtils::claim($name, new TestAuthUser('staff', '2')));
		self::assertFalse(UsernameUtils::claim($name, new TestAuthUser('customer', '1')));

		self::assertSame('1', UsernameUtils::get($name)?->getUserID());
	}
}
