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

namespace OZONE\Tests\Router;

use OZONE\Core\Roles\Enums\Role;
use OZONE\Core\Roles\Enums\RoleStrategy;
use OZONE\Core\Router\Guards\AuthUserRoleRouteGuard;
use OZONE\Core\Router\RouteGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class RoleGuardTest.
 *
 * A role guard's rules survive the compiled route table, a table written before strategies still
 * reads as it did, and `withRoles()` says what it guards.
 *
 * @internal
 */
#[CoversClass(AuthUserRoleRouteGuard::class)]
final class RoleGuardTest extends TestCase
{
	public function testAStrategyAndAnAtLeastRoleSurviveTheCompiledTable(): void
	{
		$guard = new AuthUserRoleRouteGuard(['editor', 'admin'], true, RoleStrategy::GTE, 'super-admin');

		self::assertSame($guard->toRules(), AuthUserRoleRouteGuard::fromRules($guard->toRules())->toRules());
		self::assertSame(
			['roles' => ['editor', 'admin'], 'strict' => true, 'strategy' => 'gte', 'at_least' => 'super-admin'],
			$guard->toRules()
		);
	}

	public function testRulesWrittenBeforeStrategiesReadAsTheyDid(): void
	{
		$guard = AuthUserRoleRouteGuard::fromRules(['roles' => ['editor'], 'strict' => false]);

		self::assertSame(
			['roles' => ['editor'], 'strict' => false, 'strategy' => 'any', 'at_least' => null],
			$guard->toRules()
		);
	}

	public function testWithRolesSaysWhatItGuards(): void
	{
		$group = new RouteGroup('/x');

		$group->withRoles(RoleStrategy::ALL, Role::SUPER_ADMIN, Role::EDITOR, Role::ADMIN);

		self::assertSame(
			[[
				'type'     => 'role',
				'roles'    => ['editor', 'admin'],
				'strict'   => false,
				'strategy' => 'all',
				'at_least' => 'super-admin',
			]],
			$group->getGuardDescriptors()
		);
	}
}
