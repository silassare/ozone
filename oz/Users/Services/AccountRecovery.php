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

namespace OZONE\Core\Users\Services;

use OZONE\Core\App\Service;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\AuthMethodType;
use OZONE\Core\Auth\Providers\EmailVerificationProvider;
use OZONE\Core\Auth\Providers\PhoneVerificationAuthProvider;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\InternalErrorException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Router\Guards\TwoFactorRouteGuard;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Users\Users;

/**
 * Class AccountRecovery.
 */
final class AccountRecovery extends Service
{
	public const ROUTE_ACCOUNT_RECOVERY = 'oz:account-recovery';

	/**
	 * @param \OZONE\Core\Router\RouteInfo $ri
	 *
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 * @throws \OZONE\Core\Exceptions\UnauthorizedActionException
	 * @throws \OZONE\Core\Exceptions\InternalErrorException
	 */
	public function actionRecover(RouteInfo $ri): void
	{
		$context = $ri->getContext();

		/** @var \OZONE\Core\Db\OZAuth $auth */
		$auth = $ri->getGuardFormData(TwoFactorRouteGuard::class)
			->get('auth');

		$um = $context->getUsers();

		$provider = Auth::provider($this->getContext(), $auth);

		if ($provider instanceof EmailVerificationProvider) {
			$user = Users::withEmail($provider->getEmail());
		} elseif ($provider instanceof PhoneVerificationAuthProvider) {
			$user = Users::withPhone($provider->getPhone());
		} else {
			// this is a logic error or someone is playing with us
			throw new InternalErrorException();
		}

		if (!$user || !$user->isValid()) {
			throw new ForbiddenException();
		}

		$new_pass = $ri->getCleanFormField('pass');

		Users::updatePass($user, $new_pass);

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
			->auths(AuthMethodType::SESSION)
			->with2FA(PhoneVerificationAuthProvider::NAME, EmailVerificationProvider::NAME)
			->form(self::editPassForm(...));
	}

	/**
	 * @return \OZONE\Core\Forms\Form
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
