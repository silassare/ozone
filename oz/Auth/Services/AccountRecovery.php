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

use Gobl\DBAL\Types\TypeBool;
use OZONE\Core\App\Service;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Providers\EmailOwnershipVerificationProvider;
use OZONE\Core\Auth\Providers\PhoneOwnershipVerificationProvider;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\InternalErrorException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Router\Guards\AuthorizationProviderRouteGuard;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class AccountRecovery.
 */
final class AccountRecovery extends Service
{
	public const ROUTE_ACCOUNT_RECOVERY      = 'oz:account-recovery';
	public const AUTO_LOGIN_ON_SUCCESS_FIELD = 'auto_login_on_success';

	/**
	 * @param RouteInfo $ri
	 *
	 * @throws ForbiddenException
	 * @throws UnauthorizedActionException
	 * @throws InternalErrorException
	 */
	public function actionRecover(RouteInfo $ri): void
	{
		$user_type               = $ri->getCleanFormField(AuthUsers::FIELD_AUTH_USER_TYPE);
		$auto_login_on_success   = $ri->getCleanFormField(self::AUTO_LOGIN_ON_SUCCESS_FIELD, false);

		$provider = AuthorizationProviderRouteGuard::resolveResults($ri)['provider'];
		$selector = [
			AuthUsers::FIELD_AUTH_USER_TYPE => $user_type,
		];

		if ($provider instanceof EmailOwnershipVerificationProvider) {
			$selector[AuthUsers::FIELD_AUTH_USER_IDENTIFIER_NAME]  = AuthUserInterface::IDENTIFIER_NAME_EMAIL;
			$selector[AuthUsers::FIELD_AUTH_USER_IDENTIFIER_VALUE] = $provider->getEmail();
		} elseif ($provider instanceof PhoneOwnershipVerificationProvider) {
			$selector[AuthUsers::FIELD_AUTH_USER_IDENTIFIER_NAME]  = AuthUserInterface::IDENTIFIER_NAME_PHONE;
			$selector[AuthUsers::FIELD_AUTH_USER_IDENTIFIER_VALUE] = $provider->getPhone();
		} else {
			// this is a logic error or someone is playing with us
			throw new InternalErrorException();
		}
		$user = AuthUsers::identifyBySelector($selector);

		if (!$user || !$user->isAuthUserValid()) {
			throw new ForbiddenException();
		}

		$new_pass = $ri->getCleanFormField('pass');

		AuthUsers::updatePassword($user, $new_pass);

		if ($auto_login_on_success) {
			$ri->getContext()->getAuthUsers()->logUserIn($user);
		}

		$this->json()
			->setDone('OZ_ACCOUNT_RECOVERY_SUCCESS')
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
			->withAuthorization(
				EmailOwnershipVerificationProvider::NAME,
				PhoneOwnershipVerificationProvider::NAME
			)
			->form(self::editPassForm(...));
	}

	/**
	 * @return Form
	 */
	public static function editPassForm(): Form
	{
		$form = new Form();

		$form->field(AuthUsers::FIELD_AUTH_USER_TYPE)->required();
		$form->field(self::AUTO_LOGIN_ON_SUCCESS_FIELD)->type(new TypeBool());

		$pass = $form->field('pass')
			->type(new TypePassword())
			->required();

		return $form->doubleCheck($pass);
	}
}
