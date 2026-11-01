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

namespace OZONE\Tests\Users;

use Gobl\CRUD\Exceptions\CRUDException;
use Override;
use OZONE\Core\Db\OZUsername;
use OZONE\Core\Users\UsernameUtils;
use OZONE\Core\Users\UsersRepository;
use OZONE\Tests\Support\TestAuthUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class UsersRepositoryTest.
 *
 * @internal
 */
#[CoversClass(UsernameUtils::class)]
#[CoversClass(UsersRepository::class)]
final class UsersRepositoryTest extends TestCase
{
	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		db()->executeMulti(db()->getGenerator()->buildDatabase());
	}

	public function testOnlyUserTablesAreSupported(): void
	{
		self::assertTrue(UsersRepository::isTableSupported(db()->getTableOrFail('oz_users')));
		self::assertFalse(UsersRepository::isTableSupported(db()->getTableOrFail('oz_sessions')));
	}

	public function testRepositoryIsResolvedByUserType(): void
	{
		self::assertInstanceOf(UsersRepository::class, UsersRepository::get('user'));
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
