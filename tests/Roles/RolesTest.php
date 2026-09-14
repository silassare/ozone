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

namespace OZONE\Tests\Roles;

use Override;
use OZONE\Core\Auth\AuthUserDataStore;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Roles\Enums\Role;
use OZONE\Core\Roles\Enums\RoleCheckMode;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Roles\RolesUtils;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class RolesTest.
 *
 * Checks run in {@see RoleCheckMode::READ}: the default GRANT_ACCESS mode also asks the
 * current request whether its auth is scoped, and unit tests have no request auth.
 *
 * @internal
 *
 * @covers \OZONE\Core\Roles\Roles
 */
final class RolesTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		db()->executeMulti(db()->getGenerator()->buildDatabase());
	}

	public function testAssignedRoleIsFound(): void
	{
		$user = self::user('101');
		Roles::assign($user, Role::EDITOR);
		RolesUtils::roles($user, true);

		self::assertTrue(Roles::hasRole($user, Role::EDITOR, true, RoleCheckMode::READ));
		self::assertFalse(Roles::hasRole($user, Role::ADMIN, true, RoleCheckMode::READ));
	}

	public function testHigherRoleSatisfiesANonStrictCheck(): void
	{
		$user = self::user('102');
		Roles::assign($user, Role::ADMIN);
		RolesUtils::roles($user, true);

		self::assertTrue(Roles::isEditor($user, false, RoleCheckMode::READ));
		self::assertFalse(Roles::isEditor($user, true, RoleCheckMode::READ));
		self::assertFalse(Roles::hasRole($user, Role::SUPER_ADMIN, false, RoleCheckMode::READ));
	}

	public function testHasOneOfRoles(): void
	{
		$user = self::user('103');
		Roles::assign($user, Role::EDITOR);
		RolesUtils::roles($user, true);

		self::assertTrue(Roles::hasOneOfRoles($user, [Role::ADMIN, Role::EDITOR], null, RoleCheckMode::READ));
		self::assertFalse(Roles::hasOneOfRoles($user, [Role::ADMIN], null, RoleCheckMode::READ));
		self::assertTrue(Roles::hasOneOfRoles($user, [Role::ADMIN], Role::EDITOR, RoleCheckMode::READ));
	}

	public function testRevokedRoleCanBeRestored(): void
	{
		$user = self::user('104');
		Roles::assign($user, Role::EDITOR);
		Roles::revoke($user, Role::EDITOR);
		RolesUtils::roles($user, true);

		self::assertFalse(Roles::hasRole($user, Role::EDITOR, true, RoleCheckMode::READ));

		Roles::assign($user, Role::EDITOR, true);
		RolesUtils::roles($user, true);

		self::assertTrue(Roles::hasRole($user, Role::EDITOR, true, RoleCheckMode::READ));
	}

	private static function user(string $id): AuthUserInterface
	{
		return new class($id) implements AuthUserInterface {
			private AuthUserDataStore $data_store;

			public function __construct(private readonly string $id) {}

			#[Override]
			public function getAuthUserType(): string
			{
				return 'user';
			}

			#[Override]
			public function getAuthIdentifier(): string
			{
				return $this->id;
			}

			#[Override]
			public function getAuthIdentifiers(): array
			{
				return [];
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
				return $this->data_store ?? throw new RuntimeException('No data store on this stub.');
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
				return ['type' => 'user', 'id' => $this->id];
			}

			#[Override]
			public function jsonSerialize(): mixed
			{
				return $this->toArray();
			}
		};
	}
}
