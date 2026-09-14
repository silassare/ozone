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

use OZONE\Core\Users\UsernameUtils;
use OZONE\Core\Users\UsersRepository;
use PHPUnit\Framework\TestCase;

/**
 * Class UsersRepositoryTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Users\UsernameUtils
 * @covers \OZONE\Core\Users\UsersRepository
 */
final class UsersRepositoryTest extends TestCase
{
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
		self::assertFalse(UsernameUtils::exists('nobody-' . \bin2hex(\random_bytes(4))));
		self::assertNull(UsernameUtils::get('nobody-' . \bin2hex(\random_bytes(4))));
	}
}
