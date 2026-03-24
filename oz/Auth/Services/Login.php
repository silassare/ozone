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

use Override;
use OZONE\Core\App\Service;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\REST\ApiDoc;
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
		$users_manager = $context->getAuthUsers();
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
	#[Override]
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

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('Auth', 'Authentication endpoints.');
		$doc->addOperationFromRoute(
			self::ROUTE_LOGIN,
			'POST',
			'Login',
			[
				$doc->success(['user' => $doc->object([], ['description' => 'The authenticated user.'])]),
			],
			[
				'tags'        => [$tag->name],
				'operationId' => 'Auth.login',
				'description' => 'Authenticate a user and start a session.',
			]
		);
	}
}
