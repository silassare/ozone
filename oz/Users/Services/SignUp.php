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

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Exceptions\ORMQueryException;
use OZONE\Core\App\Service;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\Providers\EmailVerificationProvider;
use OZONE\Core\Auth\Providers\PhoneVerificationProvider;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Db\OZUser;
use OZONE\Core\Db\OZUsersController;
use OZONE\Core\Exceptions\InternalErrorException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Router\Guards\TwoFactorRouteGuard;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class SignUp.
 */
final class SignUp extends Service
{
	public const ROUTE_SIGN_UP = 'oz:signup';

	/**
	 * @param RouteInfo $ri
	 *
	 * @throws CRUDException
	 * @throws ORMException
	 * @throws ORMQueryException
	 * @throws InternalErrorException
	 * @throws GoblException
	 */
	public function actionSignUp(RouteInfo $ri): void
	{
		$data = $ri->getCleanFormData()
			->getData();

		/** @var OZAuth $auth */
		$auth = $ri->getGuardFormData(TwoFactorRouteGuard::class)
			->get('auth');

		$provider = Auth::provider($this->getContext(), $auth);

		if ($provider instanceof EmailVerificationProvider) {
			$data[OZUser::COL_EMAIL] = $provider->getEmail();
		} elseif ($provider instanceof PhoneVerificationProvider) {
			$data[OZUser::COL_PHONE] = $provider->getPhone();
		} else {
			// this is a logic error or someone is playing with us
			throw new InternalErrorException();
		}

		$controller = new OZUsersController();
		$user       = $controller->addItem($data);

		$ri->getContext()
			->getUsers()
			->logUserIn($user);

		$this->json()
			->setDone('OZ_USER_SIGN_UP_SUCCESS')
			->setData($user);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router
			->post('/signup', static function (RouteInfo $r) {
				$s = new self($r);
				$s->actionSignUp($r);

				return $s->respond();
			})
			->name(self::ROUTE_SIGN_UP)
			->form(static fn () => Form::fromTable(OZUser::TABLE_NAME))
			->with2FA(EmailVerificationProvider::NAME, PhoneVerificationProvider::NAME);
	}
}
