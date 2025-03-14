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
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class Login.
 */
final class Login extends Service
{
	public const ROUTE_LOGIN = 'oz:login';

	/**
	 * @throws InvalidFormException
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

		$result = $users_manager->tryLogInForm($form);

		if ($result instanceof AuthUserInterface) {
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
			->form(AuthUsers::logInForm(...));
	}
}
