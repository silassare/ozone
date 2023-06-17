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

namespace OZONE\Core\Users;

use OZONE\Core\App\Context;
use OZONE\Core\Columns\Types\TypeEmail;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Columns\Types\TypePhone;
use OZONE\Core\Crypt\Password;
use OZONE\Core\Db\OZUser;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Sessions\Session;
use OZONE\Core\Users\Events\UserLoggedIn;
use OZONE\Core\Users\Events\UserLoggedOut;
use OZONE\Core\Users\Events\UserLogInInvalidPass;
use OZONE\Core\Users\Events\UserLogInUnknown;
use OZONE\Core\Users\Traits\UsersUtilsTrait;
use PHPUtils\Events\Event;
use PHPUtils\Store\Store;
use Throwable;

/**
 * Class Users.
 */
final class Users
{
	use UsersUtilsTrait;
	public const SUPER_ADMIN = 'super-admin'; // Owner(s)
	public const ADMIN       = 'admin';
	public const EDITOR      = 'editor';

	/**
	 * Users constructor.
	 *
	 * @param \OZONE\Core\App\Context $context
	 */
	public function __construct(private readonly Context $context)
	{
	}

	/**
	 * Logon the user that have the given user id.
	 *
	 * @param \OZONE\Core\Db\OZUser $user the user object
	 *
	 * @return $this
	 */
	public function logUserIn(OZUser $user): self
	{
		if (!$user->isSaved()) {
			// something is going wrong
			throw new RuntimeException('OZ_USER_CANT_LOG_ON', $user->toArray());
		}

		$session      = $this->context->session();
		$previous_uid = $session->state()
			->getPreviousUserID();
		$saved_data   = [];

		// if the current user is the previous one,
		// hold the data of the current session
		if (!empty($previous_uid) && $previous_uid === $user->getID()) {
			$saved_data = $session->state()
				->getData();
		}

		try {
			$session->restart();

			$user_dt = new Store($user->getData());
			$state   = $session->state();

			if ($user_dt->has('oz.2fa_enabled')) {
				$this->start2FAAuthProcess($user, $session);
			} else {
				$session->attachUser($user);
			}
			$state->merge($saved_data);
		} catch (Throwable $t) {
			throw new RuntimeException('OZ_USER_LOG_ON_FAIL', null, $t);
		}

		Event::trigger(new UserLoggedIn($user));

		return $this;
	}

	/**
	 * Log the current user out.
	 */
	public function logUserOut(): self
	{
		if ($this->context->hasAuthenticatedUser()) {
			$session = $this->context->session();

			try {
				$current_user = $this->context->user();
				$data         = $session->state()
					->getData();
				$session->restart()
					->state()
					->merge($data)
					->setPreviousUserID($current_user->getID());
			} catch (Throwable $t) {
				throw new RuntimeException('OZ_USER_LOG_OUT_FAIL', null, $t);
			}

			Event::trigger(new UserLoggedOut($current_user));
		}

		return $this;
	}

	/**
	 * Build a logon form.
	 *
	 * @return \OZONE\Core\Forms\Form
	 */
	public static function logOnForm(): Form
	{
		$form = new Form();

		$form->field('pass')
			->type(new TypePassword())
			->required();
		$form->field('phone')
			->type(new TypePhone())
			->required()
			->if()
			->isNull('email');
		$form->field('email')
			->type(new TypeEmail())
			->required()
			->if()
			->isNull('phone');

		return $form;
	}

	/**
	 * Try to log on a user with a given phone number and password.
	 *
	 * @param \OZONE\Core\Forms\FormData $form_data
	 *
	 * @return \OZONE\Core\Db\OZUser|string the user object or error string
	 *
	 * @throws \OZONE\Core\Exceptions\InvalidFormException
	 */
	public function tryPhoneLogIn(FormData $form_data): OZUser|string
	{
		$form = self::logOnForm()
			->validate($form_data);

		$phone = $form['phone'];
		$pass  = $form['pass'];

		$user = self::withPhone($phone);

		if (!$user) {
			Event::trigger(new UserLogInUnknown($this->context));

			return 'OZ_FIELD_PHONE_NOT_REGISTERED';
		}

		return $this->tryLogIn($user, $pass);
	}

	/**
	 * Try to log on a user with a given email address and password.
	 *
	 * @param FormData $form_data
	 *
	 * @return \OZONE\Core\Db\OZUser|string the user object or error string
	 *
	 * @throws \OZONE\Core\Exceptions\InvalidFormException
	 */
	public function tryEmailLogIn(FormData $form_data): OZUser|string
	{
		$form = self::logOnForm()
			->validate($form_data);

		$email = $form['email'];
		$pass  = $form['pass'];

		$user = self::withEmail($email);

		if (!$user) {
			Event::trigger(new UserLogInUnknown($this->context));

			return 'OZ_FIELD_EMAIL_NOT_REGISTERED';
		}

		return $this->tryLogIn($user, $pass);
	}

	/**
	 * Try to log on a user.
	 *
	 * @param \OZONE\Core\Db\OZUser $user
	 * @param string                $pass
	 *
	 * @return \OZONE\Core\Db\OZUser|string
	 */
	public function tryLogIn(OZUser $user, string $pass): OZUser|string
	{
		if (!$user->isValid()) {
			return 'OZ_USER_INVALID';
		}

		if (!Password::verify($pass, $user->getPass())) {
			Event::trigger(new UserLogInInvalidPass($this->context, $user));

			return 'OZ_FIELD_PASS_INVALID';
		}

		$this->logUserIn($user);

		return $user;
	}

	/**
	 * Starts a 2FA auth process for login.
	 *
	 * @param \OZONE\Core\Db\OZUser        $user
	 * @param \OZONE\Core\Sessions\Session $session
	 */
	private function start2FAAuthProcess(OZUser $user, Session $session): void
	{
		// TODO
		// if user enable 2FA, we need to verify it first
		// before attaching the user to the session
		// To do that we start a 2FA auth process
		// and make sure we store the session id
		// in the 2FA auth process data
		// so after the user verify the 2FA
		// we can attach the user to the session
	}
}
