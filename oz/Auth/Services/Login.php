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
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\Rates\IPRateLimit;
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
			->form(AuthUsers::logInForm(...))
			// Per-IP limit; per-account attempts are limited by LoginThrottle.
			->rateLimit(static fn (RouteInfo $ri) => new IPRateLimit(
				$ri,
				(int) Settings::get('oz.auth', 'OZ_AUTH_LOGIN_IP_RATE'),
				(int) Settings::get('oz.auth', 'OZ_AUTH_LOGIN_IP_INTERVAL')
			));
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
				$doc->success(
					$doc->object([], ['additionalProperties' => true]),
					'Login successful.',
					'OZ_USER_SIGN_IN_DONE'
				),
				$doc->error([], 'Unknown account or wrong password (never told apart).', 'OZ_AUTH_INVALID_CREDENTIALS'),
				$doc->error([], 'Right password, but the account is not verified yet.', 'OZ_AUTH_USER_UNVERIFIED'),
				$doc->error([], 'Too many failed attempts for this account; retry later.', 'OZ_AUTH_TOO_MUCH_ATTEMPT'),
				$doc->error([], 'Too many login requests from this IP.', 'OZ_RATE_LIMIT_EXCEEDED', 429),
			],
			[
				'tags'        => [$tag->name],
				'operationId' => 'Auth.login',
				'description' => 'Authenticate a user and start a session. '
					. 'Provide `auth_user_type` to select the user repository '
					. '(registered in `oz.auth.users.repositories`), '
					. 'then identify the user by `auth_user_id` or by an identifier '
					. '(e.g. `auth_user_identifier_type = "email"`).',
			]
		);
	}
}
