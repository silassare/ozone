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

namespace OZONE\Core\Auth;

use OZONE\Core\App\Context;
use OZONE\Core\Auth\Events\AuthUserLoggedIn;
use OZONE\Core\Auth\Events\AuthUserLoggedOut;
use OZONE\Core\Auth\Events\AuthUserLogInFailed;
use OZONE\Core\Auth\Events\AuthUserUnknown;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Traits\AuthUsersUtilsTrait;
use OZONE\Core\Crypt\Password;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\FormData;
use Throwable;

/**
 * Class AuthUsers.
 */
final class AuthUsers
{
	use AuthUsersUtilsTrait;

	public const SUPER_ADMIN = 'super-admin'; // Owner(s)
	public const ADMIN       = 'admin';
	public const EDITOR      = 'editor';

	public const FIELD_AUTH_USER_TYPE             = 'auth_user_type';
	public const FIELD_AUTH_USER_ID               = 'auth_user_id';
	public const FIELD_AUTH_USER_IDENTIFIER_NAME  = 'auth_user_identifier_name';
	public const FIELD_AUTH_USER_IDENTIFIER_VALUE = 'auth_user_identifier_value';
	public const FIELD_AUTH_USER_PASSWORD         = 'auth_user_password';

	/**
	 * AuthUsers constructor.
	 *
	 * @param Context $context
	 */
	public function __construct(private readonly Context $context) {}

	/**
	 * Logon the auth user.
	 *
	 * @return $this
	 */
	public function logUserIn(AuthUserInterface $user): self
	{
		$auth_method   = $this->context->requireStatefulAuth();
		$previous_user = $auth_method->store()
			->getPreviousUser();
		$saved_data = [];

		// if the current user is the previous one,
		// keep the previous user data
		if ($previous_user && self::same($user, $previous_user)) {
			$saved_data = $auth_method->store()
				->getData();
		}

		try {
			$auth_method->renew();

			$auth_method->attachAuthUser($user);

			$auth_method->store()->merge($saved_data);
		} catch (Throwable $t) {
			throw new RuntimeException('OZ_USER_LOG_ON_FAIL', null, $t);
		}

		(new AuthUserLoggedIn($this->context, $user))->dispatch();

		return $this;
	}

	/**
	 * Log the current user out.
	 *
	 * @return $this
	 */
	public function logUserOut(): self
	{
		// we require a stateful auth method to log out
		// this make sure that we raise an exception
		// if a call to this method is made without
		// defining a stateful authentication method
		$auth_method = $this->context->requireStatefulAuth();

		// then we check if we have an authenticated user
		// attached to the session
		if ($this->context->hasAuthenticatedUser()) {
			try {
				$current_user = $this->context->auth()->user();
				$data         = $auth_method->store()->getData();

				$auth_method->renew();
				$auth_method->store()
					->merge($data)
					->setPreviousUser($current_user);
			} catch (Throwable $t) {
				throw new RuntimeException('OZ_USER_LOG_OUT_FAIL', null, $t);
			}

			(new AuthUserLoggedOut($this->context, $current_user))->dispatch();
		}

		return $this;
	}

	/**
	 * Try to log on a user with a given form.
	 *
	 * @param FormData $form_data
	 *
	 * @return AuthUserInterface|string the user object or error string
	 *
	 * @throws InvalidFormException
	 */
	public function tryLogInForm(FormData $form_data): AuthUserInterface|string
	{
		$fd = self::logInForm()
			->validate($form_data);

		$user_pass = $fd->get(self::FIELD_AUTH_USER_PASSWORD);

		$user = self::identifyBySelector($fd);

		if (!$user) {
			(new AuthUserUnknown($this->context))->dispatch();

			return 'OZ_AUTH_USER_UNKNOWN';
		}

		return $this->tryLogIn($user, $user_pass);
	}

	/**
	 * Try to log on a user.
	 *
	 * @param AuthUserInterface $user
	 * @param string            $pass
	 *
	 * @return AuthUserInterface|string
	 */
	public function tryLogIn(AuthUserInterface $user, string $pass): AuthUserInterface|string
	{
		if (!$user->isAuthUserVerified()) {
			return 'OZ_AUTH_USER_UNVERIFIED';
		}

		if (!Password::verify($pass, $user->getAuthPassword())) {
			(new AuthUserLogInFailed($this->context, $user))->dispatch();

			return 'OZ_FIELD_PASS_INVALID';
		}

		$this->logUserIn($user);

		return $user;
	}
}
