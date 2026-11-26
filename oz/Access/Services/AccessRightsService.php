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

namespace OZONE\Core\Access\Services;

use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\TypeString;
use Override;
use OZONE\Core\Access\AtomicActionsRegistry;
use OZONE\Core\Access\RoleRights;
use OZONE\Core\App\Service;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\UnauthenticatedException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Http\Response;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Roles\Enums\RoleStrategy;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Roles\RolesUtils;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use Throwable;

/**
 * Class AccessRightsService.
 */
class AccessRightsService extends Service
{
	public const string ROUTE_READ_ME       = 'oz:access-rights.me';

	public const string ROUTE_READ_AS_ADMIN = 'oz:access-rights.read';

	public const string ROUTE_LIST          = 'oz:access-rights.list';

	public const string ROUTE_ALLOW         = 'oz:access-rights.allow';

	public const string ROUTE_DENY          = 'oz:access-rights.deny';

	public const string ROUTE_UPDATE        = 'oz:access-rights.update';

	public const string ROUTE_ROLES         = 'oz:access-rights.roles';

	public const string ROUTE_ROLES_UPDATE  = 'oz:access-rights.roles.update';

	public function readForMe(RouteInfo $ri): Response
	{
		// we only gets user access right with through this auth method as this auth method could be scoped
		$userAccessRights = auth($ri->getContext())->getAccessRights();

		$this->json()->setData([
			'list' => AtomicActionsRegistry::read($userAccessRights),
		]);

		return $this->respond();
	}

	/**
	 * @throws BadRequestException
	 */
	public function readAsAdmin(RouteInfo $ri): Response
	{
		$user = AuthUsers::identifyBySelector($ri->getCleanFormData());

		if (!$user) {
			throw new BadRequestException();
		}

		$userAccessRights = $user->getAuthUserDataStore()->getAuthUserAccessRights();

		$this->json()->setData([
			'list' => AtomicActionsRegistry::read($userAccessRights),
		]);

		return $this->respond();
	}

	/**
	 * The matrix of roles by rights: the roles of the project's enum with their weights, every
	 * registered action, what each role is given or refused, and whether the reader may change it.
	 */
	public function readRoles(RouteInfo $ri): Response
	{
		$roles = [];

		foreach (RolesUtils::getRoleEnumClass()::cases() as $role) {
			$roles[] = ['name' => (string) $role->value, 'weight' => $role->weight()];
		}

		$this->json()->setData([
			'roles'    => $roles,
			'actions'  => \array_values(AtomicActionsRegistry::getAll()),
			'grants'   => RoleRights::all(),
			'editable' => RoleRights::canEdit(user($ri->getContext())),
		]);

		return $this->respond();
	}

	/**
	 * Gives a role `allow_actions` and refuses it `deny_actions`: super admins only.
	 *
	 * @throws BadRequestException
	 */
	public function updateRoles(RouteInfo $ri): Response
	{
		$role  = RolesUtils::normalize((string) $ri->getCleanFormField('role'));
		$allow = \array_values(\array_map('strval', (array) $ri->getCleanFormField('allow_actions', [])));
		$deny  = \array_values(\array_map('strval', (array) $ri->getCleanFormField('deny_actions', [])));

		foreach ([...$allow, ...$deny] as $action) {
			if (!self::isGrantable($action)) {
				throw new BadRequestException('Access right not registered.', ['action' => $action]);
			}
		}

		RoleRights::set($role, $allow, $deny);

		$this->json()->setData(['grants' => RoleRights::all()]);

		return $this->respond();
	}

	public function list(RouteInfo $_): Response
	{
		$this->json()->setData([
			'list' => AtomicActionsRegistry::getAll(),
		]);

		return $this->respond();
	}

	/**
	 * @throws UnauthenticatedException
	 * @throws ForbiddenException
	 * @throws BadRequestException
	 */
	public function update(RouteInfo $ri): Response
	{
		$this->doAllow($ri, $ri->getCleanFormField('allow_actions'));

		$this->doDeny($ri, $ri->getCleanFormField('deny_actions'));

		return $this->respond();
	}

	/**
	 * @throws UnauthenticatedException
	 * @throws ForbiddenException
	 * @throws BadRequestException
	 */
	public function allow(RouteInfo $ri): Response
	{
		$this->doAllow($ri, $ri->getCleanFormField('actions'));

		return $this->respond();
	}

	/**
	 * @throws UnauthenticatedException
	 * @throws BadRequestException
	 * @throws ForbiddenException
	 */
	public function deny(RouteInfo $ri): Response
	{
		$this->doDeny($ri, $ri->getCleanFormField('actions'));

		return $this->respond();
	}

	/**
	 * @throws UnauthenticatedException
	 * @throws BadRequestException
	 * @throws ForbiddenException
	 */
	public function doAllow(RouteInfo $ri, array $actions): bool
	{
		$target_user = AuthUsers::identifyBySelector($ri->getCleanFormData());

		if (!$target_user) {
			throw new BadRequestException();
		}
		$current_user = user($ri->getContext());

		if (AuthUsers::same($current_user, $target_user)) {
			authUsers($ri->getContext())->assertUserIsSuperAdmin('You cannot grant access rights to yourself.', [
				'_reason' => 'Only super admin can grant access rights to themselves.',
			]);
		}

		$target_user_access_rights = $target_user->getAuthUserDataStore()->getAuthUserAccessRights();
		$save                      = false;

		foreach ($actions as $action) {
			if (!AtomicActionsRegistry::isRegistered($action)) {
				throw new BadRequestException('Access right not registered.', [
					'action' => $action,
				]);
			}

			if (!$target_user_access_rights->can($action)) {
				$target_user_access_rights->allow($action);
				$save = true;
			}
		}

		if ($save) {
			$target_user->getAuthUserDataStore()->setAuthUserAccessRights($target_user_access_rights);
			$target_user->save();
		}

		return $save;
	}

	/**
	 * @throws UnauthenticatedException
	 * @throws BadRequestException
	 * @throws ForbiddenException
	 */
	public function doDeny(RouteInfo $ri, array $actions): bool
	{
		$target_user = AuthUsers::identifyBySelector($ri->getCleanFormData());

		if (!$target_user) {
			throw new BadRequestException();
		}

		$current_user = user($ri->getContext());
		if (AuthUsers::same($current_user, $target_user)) {
			authUsers($ri->getContext())->assertUserIsSuperAdmin('You cannot revoke access rights from yourself.', [
				'_reason' => 'Only super admin can revoke access rights from themselves.',
			]);
		}

		if (Roles::isAdmin($target_user)) {
			authUsers($ri->getContext())->assertUserIsSuperAdmin('You cannot revoke access rights from an admin.', [
				'_reason' => 'Only super admin can revoke access rights from an admin.',
			]);
		}

		$target_user_access_rights = $target_user->getAuthUserDataStore()->getAuthUserAccessRights();
		$save                      = false;

		foreach ($actions as $action) {
			if (!AtomicActionsRegistry::isRegistered($action)) {
				throw new BadRequestException('Access right not registered.', [
					'action' => $action,
				]);
			}

			if ($target_user_access_rights->can($action)) {
				$target_user_access_rights->deny($action);
				$save = true;
			}
		}
		if ($save) {
			$target_user->getAuthUserDataStore()->setAuthUserAccessRights($target_user_access_rights);
			$target_user->save();
		}

		return $save;
	}

	/**
	 * @throws Throwable
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router->group('/access-rights', static function (Router $router): void {
			$router->get('/me', static fn (RouteInfo $ri) => new self($ri)->readForMe($ri))->name(self::ROUTE_READ_ME)->withAuthenticatedUser();

			$router->get('/read', static fn (RouteInfo $ri) => new self($ri)->readAsAdmin($ri))
				->name(self::ROUTE_READ_AS_ADMIN)
				->form(AuthUsers::selectorForm(...))
				->withRoles(RoleStrategy::GTE, [RolesUtils::admin()]);

			$router->get('/list', static fn (RouteInfo $ri) => new self($ri)->list($ri))
				->name(self::ROUTE_LIST)
				->withRoles(RoleStrategy::GTE, [RolesUtils::admin()]);

			$router->post('/allow', static fn (RouteInfo $ri) => new self($ri)->allow($ri))
				->name(self::ROUTE_ALLOW)
				->form(self::buildAllowForm(...))
				->withRoles(RoleStrategy::GTE, [RolesUtils::admin()]);

			$router->post('/deny', static fn (RouteInfo $ri) => new self($ri)->deny($ri))
				->name(self::ROUTE_DENY)
				->form(self::buildDenyForm(...))
				->withRoles(RoleStrategy::GTE, [RolesUtils::admin()]);

			$router->post('/update', static fn (RouteInfo $ri) => new self($ri)->update($ri))
				->name(self::ROUTE_UPDATE)
				->form(self::buildUpdateForm(...))
				->withRoles(RoleStrategy::GTE, [RolesUtils::admin()]);

			// An administrator, or anyone heavier: `withRole()` is the exact role, and would leave a
			// super admin out of the matrix they alone may change.
			$router->get('/roles', static fn (RouteInfo $ri) => new self($ri)->readRoles($ri))
				->name(self::ROUTE_ROLES)
				->withRoles(RoleStrategy::GTE, [RolesUtils::admin()]);

			// A role's rights are every holder's: super admins only.
			$router->post('/roles', static fn (RouteInfo $ri) => new self($ri)->updateRoles($ri))
				->name(self::ROUTE_ROLES_UPDATE)
				->form(self::buildRolesForm(...))
				->withRole(RolesUtils::superAdmin());
		});
	}

	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('Access', 'Access rights management endpoints.');

		$doc->addOperationFromRoute(self::ROUTE_READ_ME, 'GET', 'Get My Access Rights', [
			$doc->success(['list' => $doc->array($doc->object([]))]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Access.readMe',
			'description' => 'Get the access rights of the currently authenticated user.',
		]);

		$doc->addOperationFromRoute(self::ROUTE_READ_AS_ADMIN, 'GET', 'Get User Access Rights (Admin)', [
			$doc->success(['list' => $doc->array($doc->object([]))]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Access.readAsAdmin',
			'description' => 'Get the access rights of a specific user (admin only).',
		]);

		$doc->addOperationFromRoute(self::ROUTE_LIST, 'GET', 'List All Access Rights', [
			$doc->success(['list' => $doc->array($doc->object([]))]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Access.list',
			'description' => 'List all registered atomic actions (admin only).',
		]);

		$doc->addOperationFromRoute(self::ROUTE_ALLOW, 'POST', 'Allow Access Rights', [
			$doc->success([]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Access.allow',
			'description' => 'Grant one or more access rights to a user (admin only).',
		]);

		$doc->addOperationFromRoute(self::ROUTE_DENY, 'POST', 'Deny Access Rights', [
			$doc->success([]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Access.deny',
			'description' => 'Revoke one or more access rights from a user (admin only).',
		]);

		$doc->addOperationFromRoute(self::ROUTE_UPDATE, 'POST', 'Update Access Rights', [
			$doc->success([]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Access.update',
			'description' => 'Atomically grant and revoke access rights on a user (admin only).',
		]);

		$doc->addOperationFromRoute(self::ROUTE_ROLES, 'GET', 'Get the Rights of the Roles', [
			$doc->success([
				'roles'    => $doc->array($doc->object([])),
				'actions'  => $doc->array($doc->object([])),
				'grants'   => $doc->object([]),
				'editable' => $doc->boolean(),
			]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Access.readRoles',
			'description' => 'The roles with their weights, every registered action, and what each role is given'
				. ' (`true`) or refused (`false`); `editable` says whether the reader may change them (admin only).',
		]);

		$doc->addOperationFromRoute(self::ROUTE_ROLES_UPDATE, 'POST', 'Update the Rights of a Role', [
			$doc->success(['grants' => $doc->object([])]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Access.updateRoles',
			'description' => 'Give a role `allow_actions` and refuse it `deny_actions`, registered actions or'
				. ' wildcards over them; every holder of the role follows (super admin only).',
		]);
	}

	/**
	 * @throws TypesException
	 */
	private static function buildAllowForm(): Form
	{
		$fb = AuthUsers::selectorForm();

		$fb->field('actions')
			->type(new TypeString(3))
			->multiple()
			->required();

		return $fb;
	}

	/**
	 * @throws TypesException
	 */
	private static function buildDenyForm(): Form
	{
		$fb = AuthUsers::selectorForm();

		$fb->field('actions')
			->type(new TypeString(3))
			->multiple()
			->required();

		return $fb;
	}

	/**
	 * Whether an action may be given or refused: a registered one, or a wildcard (`posts.*`) over
	 * registered ones.
	 */
	private static function isGrantable(string $action): bool
	{
		if (AtomicActionsRegistry::isRegistered($action)) {
			return true;
		}

		if (!\str_ends_with($action, '.*')) {
			return false;
		}

		$prefix = \substr($action, 0, -1);

		foreach (\array_keys(AtomicActionsRegistry::getAll()) as $registered) {
			if (\str_starts_with((string) $registered, $prefix)) {
				return true;
			}
		}

		return false;
	}

	private static function buildRolesForm(): Form
	{
		$fb = new Form();

		$fb->string('role', true);

		$fb->field('allow_actions')
			->type(new TypeString(3))
			->multiple();

		$fb->field('deny_actions')
			->type(new TypeString(3))
			->multiple();

		return $fb;
	}

	/**
	 * @throws TypesException
	 */
	private static function buildUpdateForm(): Form
	{
		$fb = AuthUsers::selectorForm();

		$fb->field('deny_actions')
			->type(new TypeString(3))
			->multiple()
			->required();

		$fb->field('allow_actions')
			->type(new TypeString(3))
			->multiple()
			->required();

		return $fb;
	}
}
