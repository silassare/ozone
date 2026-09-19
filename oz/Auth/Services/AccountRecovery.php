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
use Override;
use OZONE\Core\App\Service;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Providers\EmailOwnershipVerificationProvider;
use OZONE\Core\Auth\Providers\PhoneOwnershipVerificationProvider;
use OZONE\Core\Auth\VerificationPolicy;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\InternalErrorException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Forms\Form;
use OZONE\Core\REST\ApiDoc;
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
	 * @throws UnauthorizedException
	 * @throws InternalErrorException
	 */
	public function actionRecover(RouteInfo $ri): void
	{
		$user_type               = $ri->getCleanFormField(AuthUsers::FIELD_AUTH_USER_TYPE);
		$auto_login_on_success   = $ri->getCleanFormField(self::AUTO_LOGIN_ON_SUCCESS_FIELD, false);

		// What this user type must prove (`oz.auth.verification`), checked against what was presented.
		$provider = VerificationPolicy::resolve(
			$ri,
			VerificationPolicy::ACCOUNT_RECOVERY,
			$user_type
		);
		$selector = [
			AuthUsers::FIELD_AUTH_USER_TYPE => $user_type,
		];

		if ($provider instanceof EmailOwnershipVerificationProvider) {
			$selector[AuthUsers::FIELD_AUTH_USER_IDENTIFIER_TYPE]  = AuthUserInterface::IDENTIFIER_TYPE_EMAIL;
			$selector[AuthUsers::FIELD_AUTH_USER_IDENTIFIER_VALUE] = $provider->getEmail();
		} elseif ($provider instanceof PhoneOwnershipVerificationProvider) {
			$selector[AuthUsers::FIELD_AUTH_USER_IDENTIFIER_TYPE]  = AuthUserInterface::IDENTIFIER_TYPE_PHONE;
			$selector[AuthUsers::FIELD_AUTH_USER_IDENTIFIER_VALUE] = $provider->getPhone();
		} else {
			// Nothing had to be proven, or a provider of the project's own: the account is the one the
			// form names, and it is the project's rule that says this is enough.
			$selector[AuthUsers::FIELD_AUTH_USER_IDENTIFIER_TYPE]  = $ri->getCleanFormField(
				AuthUsers::FIELD_AUTH_USER_IDENTIFIER_TYPE
			);
			$selector[AuthUsers::FIELD_AUTH_USER_IDENTIFIER_VALUE] = $ri->getCleanFormField(
				AuthUsers::FIELD_AUTH_USER_IDENTIFIER_VALUE
			);
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
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$route = $router
			->post('/account-recovery', static function (RouteInfo $ri) {
				$s = new self($ri);

				$s->actionRecover($ri);

				return $s->respond();
			})
			->name(self::ROUTE_ACCOUNT_RECOVERY)
			->form(self::editPassForm(...));

		// Asked for at the door only when every user type must prove something; the rule of the type
		// being recovered is what `actionRecover()` enforces in any case.
		if (VerificationPolicy::alwaysRequired(VerificationPolicy::ACCOUNT_RECOVERY)) {
			$route->withAuthorization(
				...VerificationPolicy::allProviders(VerificationPolicy::ACCOUNT_RECOVERY)
			);
		}
	}

	/**
	 * @return Form
	 */
	public static function editPassForm(): Form
	{
		$form = new Form();

		$form->field(AuthUsers::FIELD_AUTH_USER_TYPE)->required();
		$form->field(self::AUTO_LOGIN_ON_SUCCESS_FIELD)->type(new TypeBool());

		$form->field('pass')
			->type(new TypePassword())
			->required()
			->doubleCheck();

		return $form;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('Auth', 'Authentication endpoints.');
		$doc->addOperationFromRoute(
			self::ROUTE_ACCOUNT_RECOVERY,
			'POST',
			'Account Recovery',
			[
				$doc->success(['user' => $doc->object([], ['description' => 'The recovered user.'])]),
			],
			[
				'tags'        => [$tag->name],
				'operationId' => 'Auth.accountRecovery',
				'description' => 'Reset a user password after email or phone ownership verification.',
			]
		);
	}
}
