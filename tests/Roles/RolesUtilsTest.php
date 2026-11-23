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

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Roles\Enums\Role;
use OZONE\Core\Roles\RolesUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Class RolesUtilsTest.
 *
 * @internal
 */
#[CoversClass(RolesUtils::class)]
final class RolesUtilsTest extends TestCase
{
	public function testTheRoleEnumIsARoleEnumClass(): void
	{
		self::assertSame(Role::class, RolesUtils::checkRoleEnumClass(Role::class));
		// what the setting holds by default
		self::assertSame(Role::class, RolesUtils::getRoleEnumClass());
	}

	#[DataProvider('provideARoleEnumClassThatCannotBeUsedIsRefusedNamingTheSettingCases')]
	public function testARoleEnumClassThatCannotBeUsedIsRefusedNamingTheSetting(mixed $class, string $message): void
	{
		try {
			RolesUtils::checkRoleEnumClass($class);
			self::fail('An unusable role enum class was accepted.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString($message, $e->getMessage());
			self::assertSame(
				['type' => 'config', 'group' => 'oz.roles', 'key' => 'OZ_ROLE_ENUM_CLASS'],
				$e->getData(true)['_suspect'] ?? null
			);
		}
	}

	/**
	 * @return iterable<string, array{mixed, string}>
	 */
	public static function provideARoleEnumClassThatCannotBeUsedIsRefusedNamingTheSettingCases(): iterable
	{
		yield 'no such class' => ['Acme\NoSuchRole', 'Class "Acme\NoSuchRole" does not exist'];

		yield 'not a class name' => [null, 'Class "null" does not exist'];

		yield 'not a role enum' => [stdClass::class, 'Class "stdClass" is not a subclass of'];
	}
}
