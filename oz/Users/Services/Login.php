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
use OZONE\Core\Auth\AuthMethodType;
use OZONE\Core\Db\OZUser;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Users\Users;

/**
 * Class Login.
 */
final class Login extends Service
{
	public const ROUTE_LOGIN = 'oz:login';

	/**
	 * @throws \OZONE\Core\Exceptions\InvalidFormException
	 */
	public function actionLogin(): void
	{
		$context       = $this->getContext();
		$users_manager = $context->getUsers();
		// And yes! user sent us a form
		// so we check that the form is valid.
		$users_manager->logUserOut();

		$form = $context->getRequest()
			->getUnsafeFormData();

		if (isset($form['phone'])) {
			$result = $users_manager->tryPhoneLogIn($form);
		} elseif (isset($form['email'])) {
			$result = $users_manager->tryEmailLogIn($form);
		} else {
			throw new InvalidFormException();
		}

		if ($result instanceof OZUser) {
			$this->json()
				->setDone('OZ_USER_SIGN_IN_DONE')
				->setData($result);
		} else {
			$this->json()
				->setError($result);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router
			->post('/login', static function (RouteInfo $ri) {
				$s = new self($ri);
				$s->actionLogin();

				return $s->respond();
			})
			->name(self::ROUTE_LOGIN)
			->auths(AuthMethodType::SESSION)
			->form(Users::logOnForm(...));
	}
}
