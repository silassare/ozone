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

namespace OZONE\OZ\Users\Services;

use OZONE\OZ\Columns\Types\TypePassword;
use OZONE\OZ\Core\Service;
use OZONE\OZ\Exceptions\NotFoundException;
use OZONE\OZ\Forms\Form;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
use OZONE\OZ\Users\UserRole;
use OZONE\OZ\Users\UsersManager;

/**
 * Class Password.
 */
final class Password extends Service
{
	public const ROUTE_PASS_EDIT_BY_ADMIN      = 'oz:pass-edit-admin';
	public const ROUTE_PASS_EDIT_SELF          = 'oz:pass-edit-self';
	public const ROUTE_PASS_EDIT_SELF_WITH_2FA = 'oz:pass-edit-self-with-2fa';

	protected const FIELD_PASS_NEW     = 'pass_new';
	protected const FIELD_PASS_CURRENT = 'pass_current';

	/**
	 * Edit password: verified user only.
	 *
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 */
	public function actionEditOwnPass(RouteInfo $ri): void
	{
		$um   = $ri->getContext()
			->getUsersManager();
		$user = $um->getCurrentUserObject();

		$um->updateUserPass(
			$user,
			$ri->getCleanFormField(self::FIELD_PASS_NEW),
			$ri->getCleanFormField(self::FIELD_PASS_CURRENT)
		);

		$this->getJSONResponse()
			->setDone('OZ_PASSWORD_EDIT_SUCCESS')
			->setData($user);
	}

	/**
	 * Edit password: admin only.
	 *
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 * @param string                     $uid
	 *
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	public function actionEditPassAdmin(RouteInfo $ri, string $uid): void
	{
		$um   = $ri->getContext()
			->getUsersManager();
		$user = UsersManager::getUserObject($uid);

		if (!$user) {
			throw new NotFoundException();
		}

		$um->updateUserPass($user, $ri->getCleanFormField(self::FIELD_PASS_NEW));

		$this->getJSONResponse()
			->setDone('OZ_PASSWORD_EDIT_SUCCESS')
			->setData($user);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->group('/users', function (Router $r) {
			$r->map(['PATCH', 'POST'], '/{uid}/password/edit', function (RouteInfo $ri) {
				$s = new self($ri);
				$s->actionEditPassAdmin($ri, $ri->getParam('uid'));

				return $s->respond();
			})
				->name(self::ROUTE_PASS_EDIT_BY_ADMIN)
				->params(['uid' => '\d+'])
				->withRole(UserRole::ROLE_ADMIN);

			$r->map(['PATCH', 'POST'], '/password/edit', function (RouteInfo $ri) {
				$s = new self($ri);
				$s->actionEditOwnPass($ri);

				return $s->respond();
			})
				->name(self::ROUTE_PASS_EDIT_SELF)
				->form(function () {
					$form = new Form();
					$form->field(self::FIELD_PASS_CURRENT);

					return $form;
				});

			$r->map(['PATCH', 'POST'], '/password/edit-with-2fa', function (RouteInfo $ri) {
				$s = new self($ri);
				$s->actionEditOwnPass($ri);

				return $s->respond();
			})
				->name(self::ROUTE_PASS_EDIT_SELF_WITH_2FA)
				->with2FA();
		})
			->form(function () {
				$form = new Form();

				$form->doubleCheck($form->field(self::FIELD_PASS_NEW)
					->type(new TypePassword())
					->required());

				return $form;
			});
	}
}
