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

use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Forms\FormData;
use PHPUnit\Framework\TestCase;

/**
 * Class LoginFormTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Auth\AuthUsers
 */
final class LoginFormTest extends TestCase
{
	public function testUserCanBeSelectedByIdentifier(): void
	{
		$clean = AuthUsers::logInForm()->validate(new FormData([
			AuthUsers::FIELD_AUTH_USER_TYPE             => 'user',
			AuthUsers::FIELD_AUTH_USER_IDENTIFIER_TYPE  => 'email',
			AuthUsers::FIELD_AUTH_USER_IDENTIFIER_VALUE => 'jane@example.com',
			AuthUsers::FIELD_AUTH_USER_PASSWORD         => 'Jane_Pass_42',
		]));

		self::assertSame('email', $clean->get(AuthUsers::FIELD_AUTH_USER_IDENTIFIER_TYPE));
		self::assertSame('jane@example.com', $clean->get(AuthUsers::FIELD_AUTH_USER_IDENTIFIER_VALUE));
		self::assertNull($clean->get(AuthUsers::FIELD_AUTH_USER_ID));
	}

	public function testUserCanBeSelectedById(): void
	{
		$clean = AuthUsers::logInForm()->validate(new FormData([
			AuthUsers::FIELD_AUTH_USER_TYPE     => 'user',
			AuthUsers::FIELD_AUTH_USER_ID       => '42',
			AuthUsers::FIELD_AUTH_USER_PASSWORD => 'Jane_Pass_42',
		]));

		self::assertSame('42', $clean->get(AuthUsers::FIELD_AUTH_USER_ID));
	}

	public function testIdOrIdentifierIsRequired(): void
	{
		$this->expectException(InvalidFormException::class);

		AuthUsers::logInForm()->validate(new FormData([
			AuthUsers::FIELD_AUTH_USER_TYPE     => 'user',
			AuthUsers::FIELD_AUTH_USER_PASSWORD => 'Jane_Pass_42',
		]));
	}
}
