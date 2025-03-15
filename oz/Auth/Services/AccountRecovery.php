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

namespace OZONE\Core\Auth\Services;

use OZONE\Core\App\Service;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Providers\EmailVerificationProvider;
use OZONE\Core\Auth\Providers\PhoneVerificationProvider;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Router\Guards\TwoFactorRouteGuard;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class AccountRecovery.
 */
final class AccountRecovery extends Service
{
	public const ROUTE_ACCOUNT_RECOVERY = 'oz:account-recovery';

	/**
	 * @param RouteInfo $ri
	 *
	 * @throws ForbiddenException
	 * @throws UnauthorizedActionException
	 */
	public function actionRecover(RouteInfo $ri): void
	{
		$context = $ri->getContext();

		/** @var OZAuth $auth */
		$auth = $ri->getGuardFormData(TwoFactorRouteGuard::class)
			->get('auth');

		$um = $context->getUsers();

		$user = AuthUsers::identifyBySelector([
			AuthUsers::FIELD_AUTH_USER_TYPE => $auth->getOwnerType(),
			AuthUsers::FIELD_AUTH_USER_ID   => $auth->getOwnerId(),
		]);

		if (!$user || !$user->isAuthUserValid()) {
			throw new ForbiddenException();
		}

		$new_pass = $ri->getCleanFormField('pass');

		AuthUsers::updatePassword($user, $new_pass);

		$um->logUserIn($user);

		$this->json()
			->setDone('OZ_PASSWORD_EDIT_SUCCESS')
			->setData($user);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router
			->post('/account-recovery', static function (RouteInfo $ri) {
				$s = new self($ri);

				$s->actionRecover($ri);

				return $s->respond();
			})
			->name(self::ROUTE_ACCOUNT_RECOVERY)
			->with2FA(PhoneVerificationProvider::NAME, EmailVerificationProvider::NAME)
			->form(self::editPassForm(...));
	}

	/**
	 * @return Form
	 */
	public static function editPassForm(): Form
	{
		$form = new Form();
		$pass = $form->field('pass')
			->type(new TypePassword())
			->required();

		return $form->doubleCheck($pass);
	}
}
