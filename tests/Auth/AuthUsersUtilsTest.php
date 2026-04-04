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

use Override;
use OZONE\Core\Auth\AuthUserDataStore;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for pure AuthUsers utility methods.
 *
 * No DB or HTTP context is required - all methods under test are stateless.
 *
 * @internal
 *
 * @coversNothing
 */
final class AuthUsersUtilsTest extends TestCase
{
	// -----------------------------------------------------------------------
	// same()
	// -----------------------------------------------------------------------

	public function testSameReturnsTrueWhenTypeAndIdMatch(): void
	{
		$a = self::mockUser('user', '42');
		$b = self::mockUser('user', '42');

		self::assertTrue(AuthUsers::same($a, $b));
	}

	public function testSameReturnsFalseWhenIdDiffers(): void
	{
		$a = self::mockUser('user', '42');
		$b = self::mockUser('user', '99');

		self::assertFalse(AuthUsers::same($a, $b));
	}

	public function testSameReturnsFalseWhenTypeDiffers(): void
	{
		$a = self::mockUser('user', '42');
		$b = self::mockUser('admin', '42');

		self::assertFalse(AuthUsers::same($a, $b));
	}

	public function testSameReturnsFalseWhenBothTypesAndIdsDiffer(): void
	{
		$a = self::mockUser('user', '1');
		$b = self::mockUser('admin', '2');

		self::assertFalse(AuthUsers::same($a, $b));
	}

	// -----------------------------------------------------------------------
	// selector()
	// -----------------------------------------------------------------------

	public function testSelectorReturnsTypeAndId(): void
	{
		$user   = self::mockUser('customer', '7');
		$result = AuthUsers::selector($user);

		self::assertSame([
			AuthUsers::FIELD_AUTH_USER_TYPE => 'customer',
			AuthUsers::FIELD_AUTH_USER_ID   => '7',
		], $result);
	}

	// -----------------------------------------------------------------------
	// ref() - default (type.id)
	// -----------------------------------------------------------------------

	public function testRefReturnsTypeDotId(): void
	{
		$user = self::mockUser('user', '100');
		$ref  = AuthUsers::ref($user);

		self::assertSame('user.100', $ref);
	}

	public function testRefUsesCustomSeparator(): void
	{
		$user = self::mockUser('user', '100');
		$ref  = AuthUsers::ref($user, ':');

		self::assertSame('user:100', $ref);
	}

	// -----------------------------------------------------------------------
	// ref() - with identifier_type
	// -----------------------------------------------------------------------

	public function testRefWithIdentifierTypeReturnsThreeParts(): void
	{
		$user = self::mockUser('user', '5', ['email' => 'test@example.com']);
		$ref  = AuthUsers::ref($user, '.', 'email');

		self::assertSame('user.email.test@example.com', $ref);
	}

	public function testRefWithMissingIdentifierTypeThrowsRuntimeException(): void
	{
		$this->expectException(RuntimeException::class);

		$user = self::mockUser('user', '5', []);
		AuthUsers::ref($user, '.', 'email');
	}

	// -----------------------------------------------------------------------
	// refToSelector()
	// -----------------------------------------------------------------------

	public function testRefToSelectorParsesTypeDotId(): void
	{
		$result = AuthUsers::refToSelector('user.42');

		self::assertSame([
			AuthUsers::FIELD_AUTH_USER_TYPE => 'user',
			AuthUsers::FIELD_AUTH_USER_ID   => '42',
		], $result);
	}

	public function testRefToSelectorParsesThreeParts(): void
	{
		$result = AuthUsers::refToSelector('user.email.john.doe@example.com');

		self::assertSame([
			AuthUsers::FIELD_AUTH_USER_TYPE             => 'user',
			AuthUsers::FIELD_AUTH_USER_IDENTIFIER_TYPE  => 'email',
			AuthUsers::FIELD_AUTH_USER_IDENTIFIER_VALUE => 'john.doe@example.com',
		], $result);
	}

	public function testRefToSelectorReturnsFalseForSinglePart(): void
	{
		self::assertFalse(AuthUsers::refToSelector('user'));
	}

	public function testRefToSelectorReturnsFalseForEmptyString(): void
	{
		self::assertFalse(AuthUsers::refToSelector(''));
	}

	public function testRefToSelectorUsesCustomSeparator(): void
	{
		$result = AuthUsers::refToSelector('admin:99', ':');

		self::assertSame([
			AuthUsers::FIELD_AUTH_USER_TYPE => 'admin',
			AuthUsers::FIELD_AUTH_USER_ID   => '99',
		], $result);
	}

	/**
	 * ref() round-trip: produce a ref then parse it back into the original fields.
	 */
	public function testRefRoundTrip(): void
	{
		$user   = self::mockUser('customer', '77');
		$ref    = AuthUsers::ref($user);
		$parsed = AuthUsers::refToSelector($ref);

		self::assertSame([
			AuthUsers::FIELD_AUTH_USER_TYPE => 'customer',
			AuthUsers::FIELD_AUTH_USER_ID   => '77',
		], $parsed);
	}
	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Returns a minimal AuthUserInterface stub.
	 */
	private static function mockUser(string $type, string $id, array $identifiers = []): AuthUserInterface
	{
		return new class($type, $id, $identifiers) implements AuthUserInterface {
			private AuthUserDataStore $data_store;

			public function __construct(
				private readonly string $type,
				private readonly string $id,
				private readonly array $identifiers
			) {}

			#[Override]
			public function getAuthUserType(): string
			{
				return $this->type;
			}

			#[Override]
			public function getAuthIdentifier(): string
			{
				return $this->id;
			}

			#[Override]
			public function getAuthIdentifiers(): array
			{
				return $this->identifiers;
			}

			#[Override]
			public function getAuthPassword(): string
			{
				return '';
			}

			#[Override]
			public function setAuthPassword(string $password_hash): static
			{
				return $this;
			}

			#[Override]
			public function getAuthUserDataStore(): AuthUserDataStore
			{
				return $this->data_store ??= new AuthUserDataStore([]);
			}

			#[Override]
			public function setAuthUserDataStore(AuthUserDataStore $store): static
			{
				$this->data_store = $store;

				return $this;
			}

			#[Override]
			public function isAuthUserValid(): bool
			{
				return true;
			}

			#[Override]
			public function save(): bool
			{
				return true;
			}

			#[Override]
			public function toArray(): array
			{
				return ['type' => $this->type, 'id' => $this->id];
			}

			#[Override]
			public function jsonSerialize(): mixed
			{
				return $this->toArray();
			}
		};
	}
}
