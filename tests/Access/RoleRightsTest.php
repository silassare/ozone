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

namespace OZONE\Tests\Access;

use Gobl\CRUD\Enums\ActionType;
use InvalidArgumentException;
use Override;
use OZONE\Core\Access\AccessRights;
use OZONE\Core\Access\AtomicActionsRegistry;
use OZONE\Core\Access\RoleRights;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use OZONE\Core\CRUD\AllowRuleBuilder;
use OZONE\Core\CRUD\BaseHandler;
use OZONE\Core\CRUD\TableActions;
use OZONE\Core\REST\RESTFulService;
use OZONE\Core\Roles\Enums\Role;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Roles\RolesUtils;
use OZONE\Tests\Support\TestAuthUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * What a role is given, joined over a user's roles, then the user's own rights over them; a super
 * admin able to do every action; and the table actions a right names, `create` and `create_bulk`
 * among them.
 *
 * @internal
 */
#[CoversClass(RoleRights::class)]
#[CoversClass(AccessRights::class)]
#[CoversClass(TableActions::class)]
final class RoleRightsTest extends TestCase
{
	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		db()->executeMulti(db()->getGenerator()->buildDatabase());
	}

	public function testAUserHoldsWhatTheirRolesAreGivenJoined(): void
	{
		RoleRights::set(Role::EDITOR, ['posts.create', 'posts.update'], ['posts.delete']);
		RoleRights::set(Role::ADMIN, ['posts.delete'], []);

		$editor = self::userWith(Role::EDITOR);

		self::assertTrue(AccessRights::of($editor)->can('posts.create', 'posts.update'));
		self::assertFalse(AccessRights::of($editor)->can('posts.delete'));
		// Nothing of a lighter or heavier role's: what the role was given, no more.
		self::assertFalse(AccessRights::of($editor)->can('posts.read'));

		// Both roles: what either allows is allowed, the editor's refusal notwithstanding.
		$both = self::userWith(Role::EDITOR);

		Roles::assign($both, Role::ADMIN);
		RolesUtils::roles($both, true);

		self::assertTrue(AccessRights::of($both)->can('posts.delete'));
	}

	public function testTheUsersOwnRightsWinOverTheirRoles(): void
	{
		RoleRights::set(Role::EDITOR, ['notes.read'], ['notes.delete']);

		$user  = self::userWith(Role::EDITOR);
		$store = $user->getAuthUserDataStore();

		$store->setAuthUserAccessRights(new AccessRights(['notes.read' => 0, 'notes.delete' => 1], false));
		$user->setAuthUserDataStore($store);

		$rights = AccessRights::of($user);

		self::assertFalse($rights->can('notes.read'));
		self::assertTrue($rights->can('notes.delete'));
	}

	public function testASuperAdminMayDoEveryAction(): void
	{
		$super = self::userWith(Role::SUPER_ADMIN);

		self::assertTrue(AccessRights::of($super)->can('anything.at_all', 'posts.*'));
	}

	public function testTheRootWildcardStandsForEveryActionAndASpecificRuleStillWins(): void
	{
		$rights = new AccessRights(['*' => 1, 'posts.delete' => 0], false);

		self::assertTrue($rights->can('users.create'));
		self::assertFalse($rights->can('posts.delete'));
		self::assertFalse(new AccessRights([], false)->can('users.create'));
	}

	public function testAChangeIsReadAtOnce(): void
	{
		$user = self::userWith(Role::EDITOR);

		RoleRights::set(Role::EDITOR, ['tags.create'], []);
		self::assertTrue(AccessRights::of($user)->can('tags.create'));

		RoleRights::set(Role::EDITOR, [], ['tags.create']);
		self::assertFalse(AccessRights::of($user)->can('tags.create'));
		self::assertFalse(RoleRights::all()[Role::EDITOR->value]['tags.create']);
	}

	public function testEveryTableActionIsARightCreateAndBulkIncluded(): void
	{
		self::assertSame(
			['create', 'create_bulk', 'read', 'read_all', 'update', 'update_all', 'delete', 'delete_all'],
			TableActions::names()
		);

		$register = new ReflectionMethod(RESTFulService::class, 'registerAccessRightsActions');
		$register->invoke(null, db()->getTableOrFail('oz_files'));

		$prefix = db()->getTableOrFail('oz_files')->getMorphType();

		self::assertTrue(AtomicActionsRegistry::isRegistered($prefix . '.create'));
		self::assertTrue(AtomicActionsRegistry::isRegistered($prefix . '.create_bulk'));
		self::assertFalse(AtomicActionsRegistry::isRegistered($prefix . '.create_all'));
		self::assertSame('create_bulk', TableActions::nameOf(ActionType::CREATE_BULK));
	}

	public function testAHandlerRefusesAnActionNoTableHas(): void
	{
		$handler = new RoleRightsTestHandler(db()->getTableOrFail('oz_files'));

		$this->expectException(InvalidArgumentException::class);

		$handler->allow('create_all');
	}

	public function testAHandlersOwnBulkRuleIsKeptAndTheDefaultIsAdminsOnly(): void
	{
		$rules = new ReflectionProperty(BaseHandler::class, 'allow_rules');
		$table = db()->getTableOrFail('oz_files');

		$default = new RoleRightsTestHandler($table);
		$opened  = new RoleRightsTestOpenedHandler($table);

		self::assertArrayHasKey('create_bulk', $rules->getValue($default));
		self::assertSame($opened->own, $rules->getValue($opened)['create_bulk'] ?? null);
	}

	/**
	 * An auth user of a role, nobody else's: its roles read fresh.
	 */
	private static function userWith(Role $role): TestAuthUser
	{
		$user = new TestAuthUser('user', (string) \random_int(100_000_000, 900_000_000));

		Roles::assign($user, $role);
		RolesUtils::roles($user, true);

		return $user;
	}
}

/**
 * A handler with OZone's defaults only.
 *
 * @internal
 *
 * @extends BaseHandler<ORMEntity>
 */
final class RoleRightsTestHandler extends BaseHandler
{
	/**
	 * Attaches nothing: the rules are what is tested, and a listener added to a real table would
	 * stay for every test after this one.
	 */
	#[Override]
	protected function listen(): void {}

	#[Override]
	public static function register(): void {}
}

/**
 * A handler opening its bulk create before OZone's defaults are set.
 *
 * @internal
 *
 * @extends BaseHandler<ORMEntity>
 */
final class RoleRightsTestOpenedHandler extends BaseHandler
{
	/**
	 * Attaches nothing: the rules are what is tested, and a listener added to a real table would
	 * stay for every test after this one.
	 */
	#[Override]
	protected function listen(): void {}

	public ?AllowRuleBuilder $own = null;

	public function __construct(Table $table)
	{
		$this->own = $this->allow('create_bulk');

		parent::__construct($table);
	}

	#[Override]
	public static function register(): void {}
}
