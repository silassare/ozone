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

use Gobl\Exceptions\GoblException;
use Override;
use OZONE\Core\App\Service;
use OZONE\Core\Auth\Providers\EmailOwnershipVerificationProvider;
use OZONE\Core\Auth\Providers\PhoneOwnershipVerificationProvider;
use OZONE\Core\Auth\VerificationPolicy;
use OZONE\Core\Db\OZUser;
use OZONE\Core\Db\OZUsersController;
use OZONE\Core\Exceptions\InternalErrorException;
use OZONE\Core\Forms\Form;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Users\UsersRepository;

/**
 * Class SignUp.
 */
final class SignUp extends Service
{
	public const ROUTE_SIGN_UP = 'oz:signup';

	/**
	 * @param RouteInfo $ri
	 *
	 * @throws GoblException
	 * @throws InternalErrorException
	 */
	public function actionSignUp(RouteInfo $ri): void
	{
		$data = $ri->getCleanFormData()
			->getData();

		// What this user type must prove (`oz.auth.verification`): null when nothing, and then the
		// identifiers are the ones the form carries, for the project to verify later.
		$provider = VerificationPolicy::resolve(
			$ri,
			VerificationPolicy::SIGN_UP,
			UsersRepository::DEFAULT_USER_TYPE
		);

		if ($provider instanceof EmailOwnershipVerificationProvider) {
			$data[OZUser::COL_EMAIL] = $provider->getEmail();
		} elseif ($provider instanceof PhoneOwnershipVerificationProvider) {
			$data[OZUser::COL_PHONE] = $provider->getPhone();
		} elseif (null !== $provider) {
			// A project named its own provider: it knows what the authorization proves, so it says
			// which identifier it fills through the payload of the authorization.
			foreach ($provider->getPayload() as $column => $value) {
				if (\in_array($column, [OZUser::COL_EMAIL, OZUser::COL_PHONE], true)) {
					$data[$column] = $value;
				}
			}
		}

		$controller = new OZUsersController();
		$user       = $controller->addItem($data);

		$ri->getContext()
			->getAuthUsers()
			->logUserIn($user);

		$this->json()
			->setDone('OZ_USER_SIGN_UP_SUCCESS')
			->setData($user);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$route = $router
			->post('/signup', static function (RouteInfo $r) {
				$s = new self($r);
				$s->actionSignUp($r);

				return $s->respond();
			})
			->name(self::ROUTE_SIGN_UP)
			->form(static fn () => Form::fromTable(OZUser::TABLE_NAME));

		// The route asks for a verification at its door only when every user type needs one; otherwise
		// the rule of the type being signed up is what `actionSignUp()` enforces.
		if (VerificationPolicy::alwaysRequired(VerificationPolicy::SIGN_UP)) {
			$route->withAuthorization(...VerificationPolicy::allProviders(VerificationPolicy::SIGN_UP));
		}
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('Users', 'User management endpoints.');
		$doc->addOperationFromRoute(
			self::ROUTE_SIGN_UP,
			'POST',
			'Sign Up',
			[
				$doc->success(['user' => $doc->object([], ['description' => 'The newly created user.'])]),
			],
			[
				'tags'        => [$tag->name],
				'operationId' => 'Users.signUp',
				'description' => 'Create a new user account. Requires prior email or phone ownership verification.',
			]
		);
	}
}
