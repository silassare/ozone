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
use OZONE\Core\App\Service;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\UnauthenticatedException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Http\Response;
use OZONE\Core\REST\ApiDoc;
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
	public const ROUTE_READ_ME       = 'oz:access-rights.me';

	public const ROUTE_READ_AS_ADMIN = 'oz:access-rights.read';

	public const ROUTE_LIST          = 'oz:access-rights.list';

	public const ROUTE_ALLOW         = 'oz:access-rights.allow';

	public const ROUTE_DENY          = 'oz:access-rights.deny';

	public const ROUTE_UPDATE        = 'oz:access-rights.update';

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
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router->group('/access-rights', static function (Router $router) {
			$router->get('/me', static function (RouteInfo $ri) {
				return (new self($ri))->readForMe($ri);
			})->name(self::ROUTE_READ_ME)->withAuthenticatedUser();

			$router->get('/read', static function (RouteInfo $ri) {
				return (new self($ri))->readAsAdmin($ri);
			})
				->name(self::ROUTE_READ_AS_ADMIN)
				->form(AuthUsers::selectorForm(...))
				->withRole(RolesUtils::admin());

			$router->get('/list', static function (RouteInfo $ri) {
				return (new self($ri))->list($ri);
			})
				->name(self::ROUTE_LIST)
				->withRole(RolesUtils::admin());

			$router->post('/allow', static function (RouteInfo $ri) {
				return (new self($ri))->allow($ri);
			})
				->name(self::ROUTE_ALLOW)
				->form(self::buildAllowForm(...))
				->withRole(RolesUtils::admin());

			$router->post('/deny', static function (RouteInfo $ri) {
				return (new self($ri))->deny($ri);
			})
				->name(self::ROUTE_DENY)
				->form(self::buildDenyForm(...))
				->withRole(RolesUtils::admin());

			$router->post('/update', static function (RouteInfo $ri) {
				return (new self($ri))->update($ri);
			})
				->name(self::ROUTE_UPDATE)
				->form(self::buildUpdateForm(...))
				->withRole(RolesUtils::admin());
		});
	}

	/**
	 * {@inheritDoc}
	 */
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
	}

	/**
	 * @return Form
	 *
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
	 * @return Form
	 *
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
	 * @return Form
	 *
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
