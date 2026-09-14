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
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Events\AuthUserLoggedIn;
use OZONE\Core\Auth\Events\AuthUserLoggedOut;
use OZONE\Core\Auth\Events\AuthUserLogInFailed;
use OZONE\Core\Auth\Events\AuthUserUnknown;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Interfaces\AuthUsersRepositoryInterface;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Crypt\Password;
use OZONE\Core\Db\OZSession;
use OZONE\Core\Db\OZSessionsQuery;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Exceptions\UnauthenticatedException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormDataClean;
use OZONE\Core\Roles\Interfaces\RoleInterface;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Roles\RolesUtils;
use OZONE\Core\Utils\Random;
use Throwable;

/**
 * Class AuthUsers.
 */
final class AuthUsers
{
	public const FIELD_AUTH_USER_TYPE             = 'auth_user_type';
	public const FIELD_AUTH_USER_ID               = 'auth_user_id';
	public const FIELD_AUTH_USER_IDENTIFIER_TYPE  = 'auth_user_identifier_type';
	public const FIELD_AUTH_USER_IDENTIFIER_VALUE = 'auth_user_identifier_value';
	public const FIELD_AUTH_USER_PASSWORD         = 'auth_user_password';

	/**
	 * @var array<string, AuthUsersRepositoryInterface>
	 */
	public static array $repositories = [];

	/**
	 * AuthUsers constructor.
	 *
	 * @param Context $context
	 */
	public function __construct(private readonly Context $context) {}

	/**
	 * Gets the user ref.
	 */
	public static function ref(
		AuthUserInterface $user,
		string $separator = '.',
		?string $identifier_type = null
	): string {
		if (null === $identifier_type) {
			return $user->getAuthUserType() . $separator . $user->getAuthIdentifier();
		}

		$identifiers      = $user->getAuthIdentifiers();
		$identifier_value = $identifiers[$identifier_type] ?? null;

		if (null === $identifier_value) {
			throw new RuntimeException('Auth user identifier not defined.', [
				'_user'            => self::selector($user),
				'_identifier_type' => $identifier_type,
			]);
		}

		return $user->getAuthUserType() . $separator . $identifier_type . $separator . $identifier_value;
	}

	/**
	 * Parses a auth user ref to a selector.
	 */
	public static function refToSelector(string $ref, string $separator = '.'): array|false
	{
		$parts = \explode($separator, $ref, 3);

		if (2 === \count($parts)) {
			return [
				self::FIELD_AUTH_USER_TYPE => $parts[0],
				self::FIELD_AUTH_USER_ID   => $parts[1],
			];
		}
		if (3 === \count($parts)) {
			return [
				self::FIELD_AUTH_USER_TYPE             => $parts[0],
				self::FIELD_AUTH_USER_IDENTIFIER_TYPE  => $parts[1],
				self::FIELD_AUTH_USER_IDENTIFIER_VALUE => $parts[2],
			];
		}

		return false;
	}

	/**
	 * Checks if two users are the same.
	 *
	 * @param AuthUserInterface $a
	 * @param AuthUserInterface $b
	 *
	 * @return bool
	 */
	public static function same(AuthUserInterface $a, AuthUserInterface $b): bool
	{
		return $a->getAuthUserType() === $b->getAuthUserType()
			&& $a->getAuthIdentifier() === $b->getAuthIdentifier();
	}

	/**
	 * Gets the user selector.
	 */
	public static function selector(AuthUserInterface $user): array
	{
		return [
			self::FIELD_AUTH_USER_TYPE => $user->getAuthUserType(),
			self::FIELD_AUTH_USER_ID   => $user->getAuthIdentifier(),
		];
	}

	/**
	 * Gets the auth user repository for a given user type name.
	 */
	public static function repository(string $user_type_name): AuthUsersRepositoryInterface
	{
		if (!isset(self::$repositories[$user_type_name])) {
			$class = Settings::get('oz.auth.users.repositories', $user_type_name);

			if (!$class) {
				throw (new RuntimeException(
					\sprintf(
						'Auth users repository for "%s" not found in settings.',
						$user_type_name
					)
				))->suspectConfig('oz.auth.users.repositories', $user_type_name);
			}

			if (!\class_exists($class) || !\is_subclass_of($class, AuthUsersRepositoryInterface::class)) {
				throw (new RuntimeException(
					\sprintf(
						'Auth users repository "%s" should be subclass of: %s',
						$class,
						AuthUsersRepositoryInterface::class
					)
				))->suspectConfig('oz.auth.users.repositories', $user_type_name);
			}
			self::$repositories[$user_type_name] = $class::get($user_type_name);
		}

		return self::$repositories[$user_type_name];
	}

	/**
	 * Build a form to select an auth user.
	 *
	 * @return Form
	 */
	public static function selectorForm(): Form
	{
		$form = new Form();
		$form->field(self::FIELD_AUTH_USER_TYPE)
			->required();

		// Either the user id or an identifier (type and value): the identifier fields read the id,
		// so it is declared first.
		$form->field(self::FIELD_AUTH_USER_ID);

		$form->field(self::FIELD_AUTH_USER_IDENTIFIER_TYPE)
			->required()->if()->isNull(self::FIELD_AUTH_USER_ID);

		$form->field(self::FIELD_AUTH_USER_IDENTIFIER_VALUE)
			->required()->if()->isNull(self::FIELD_AUTH_USER_ID);

		return $form;
	}

	/**
	 * Build a logon form.
	 *
	 * @return Form
	 */
	public static function logInForm(): Form
	{
		$form = self::selectorForm();

		$form->field(self::FIELD_AUTH_USER_PASSWORD)
			->type(new TypePassword())
			->required();

		return $form;
	}

	/**
	 * Identifies a auth user using a given identifier.
	 */
	public static function identify(
		string $user_type,
		string $identifier_value,
		?string $identifier_type = null
	): ?AuthUserInterface {
		try {
			$repository = self::repository($user_type);
		} catch (Throwable $t) {
			// this may be an api user that is not providing a valid user type
			oz_logger()->warning($t);

			return null;
		}
		if (null === $identifier_type) {
			return $repository->getAuthUserByIdentifier($identifier_value);
		}

		return $repository->getAuthUserByIdentifierType($identifier_type, $identifier_value);
	}

	/**
	 * Identifies a user using auth user selector form data.
	 *
	 * @param array|FormData|FormDataClean $selector raw or already validated selector data
	 *
	 * @return null|AuthUserInterface
	 */
	public static function identifyBySelector(array|FormData|FormDataClean $selector): ?AuthUserInterface
	{
		if ($selector instanceof FormDataClean) {
			$selector = $selector->toArray();
		}

		$fd = $selector instanceof FormData ? $selector : new FormData($selector);

		try {
			$fd = self::selectorForm()->validate($fd);
		} catch (Throwable) {
			return null;
		}

		$user_type       = $fd->get(self::FIELD_AUTH_USER_TYPE);
		$user_identifier = $fd->get(self::FIELD_AUTH_USER_ID);

		if (null === $user_identifier) {
			$user_identifier_type  = $fd->get(self::FIELD_AUTH_USER_IDENTIFIER_TYPE);
			$user_identifier_value = $fd->get(self::FIELD_AUTH_USER_IDENTIFIER_VALUE);

			return self::identify($user_type, $user_identifier_value, $user_identifier_type);
		}

		return self::identify($user_type, $user_identifier);
	}

	/**
	 * Gets active sessions for a given user id.
	 *
	 * @param AuthUserInterface $user
	 *
	 * @return OZSession[]
	 */
	public static function getUserActiveSessions(AuthUserInterface $user): array
	{
		$sq = new OZSessionsQuery();

		return $sq->whereOwnerIdIs($user->getAuthIdentifier())
			->whereOwnerTypeIs($user->getAuthUserType())
			->whereExpireAtIsGt(\time())
			->find()
			->fetchAllClass();
	}

	/**
	 * Log user out from all active sessions.
	 */
	public static function forceUserLogoutOnAllActiveSessions(AuthUserInterface $user): void
	{
		$sq = new OZSessionsQuery();

		$sq->whereOwnerIdIs($user->getAuthIdentifier())
			->whereOwnerTypeIs($user->getAuthUserType())
			->update([
				OZSession::COL_OWNER_ID   => null,
				OZSession::COL_OWNER_TYPE => null,
			])
			->execute();
	}

	/**
	 * Update the given user password.
	 *
	 * @param AuthUserInterface $user         the target user
	 * @param string            $new_pass     the new password
	 * @param null|string       $current_pass the current pass
	 *
	 * @throws UnauthorizedException
	 */
	public static function updatePassword(AuthUserInterface $user, string $new_pass, ?string $current_pass = null): void
	{
		$known_pass_hash = $user->getAuthPassword();

		if ((null !== $current_pass) && !Password::verify($current_pass, $known_pass_hash)) {
			throw new UnauthorizedException('OZ_FIELD_PASS_INVALID');
		}

		if (Password::verify($new_pass, $known_pass_hash)) {
			throw new UnauthorizedException('OZ_PASSWORD_SAME_OLD_AND_NEW_PASS');
		}

		try {
			$user->setAuthPassword(Password::hash($new_pass))
				->save();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to update user pass.', [
				'_user' => self::selector($user),
			], $t);
		}

		self::forceUserLogoutOnAllActiveSessions($user);
	}

	/**
	 * Asserts that we have an authenticated user.
	 *
	 * @param null|string    $message
	 * @param null|array     $data
	 * @param null|Throwable $previous
	 *
	 * @throws UnauthenticatedException
	 */
	public function assertUserIsAuthenticated(
		?string $message = null,
		?array $data = [],
		?Throwable $previous = null
	): void {
		try {
			$user = $this->context->auth()->user();
		} catch (Throwable) {
			throw new UnauthenticatedException($message, $data, $previous);
		}

		if (!$user->isAuthUserValid()) {
			throw new UnauthenticatedException($message, $data, $previous);
		}
	}

	/**
	 * Asserts that the authenticated user is at least a verified admin.
	 *
	 * @param string         $message
	 * @param null|array     $data
	 * @param null|Throwable $previous
	 *
	 * @throws ForbiddenException
	 * @throws UnauthenticatedException
	 */
	public function assertUserIsAtLeastAdmin(
		string $message = 'OZ_ERROR_YOU_ARE_NOT_ADMIN',
		?array $data = [],
		?Throwable $previous = null
	): void {
		$this->assertUserIsAuthenticated($message, $data, $previous);

		$user = $this->context->auth()->user();

		if (!Roles::isAdmin($user, false)) {
			throw new ForbiddenException($message, $data, $previous);
		}
	}

	/**
	 * Asserts that the authenticated user is at least a verified editor.
	 *
	 * @param string         $message
	 * @param null|array     $data
	 * @param null|Throwable $previous
	 *
	 * @throws ForbiddenException
	 * @throws UnauthenticatedException
	 */
	public function assertUserIsAtLeastEditor(
		string $message = 'OZ_ERROR_YOU_ARE_NOT_EDITOR',
		?array $data = [],
		?Throwable $previous = null
	): void {
		$this->assertUserIsAuthenticated($message, $data, $previous);

		$user = $this->context->auth()->user();

		if (!Roles::isEditor($user, false)) {
			throw new ForbiddenException($message, $data, $previous);
		}
	}

	/**
	 * Asserts that the authenticated user is a super-admin.
	 *
	 * @param string         $message
	 * @param null|array     $data
	 * @param null|Throwable $previous
	 *
	 * @throws ForbiddenException
	 * @throws UnauthenticatedException
	 */
	public function assertUserIsSuperAdmin(
		string $message = 'OZ_ERROR_YOU_ARE_NOT_SUPER_ADMIN',
		?array $data = [],
		?Throwable $previous = null
	): void {
		$this->assertUserIsAuthenticated($message, $data, $previous);

		$user = $this->context->auth()->user();

		if (!Roles::isSuperAdmin($user)) {
			throw new ForbiddenException($message, $data, $previous);
		}
	}

	/**
	 * Asserts that the user with the given id has at least one role in a given roles list.
	 *
	 * @param array<RoleInterface|string> $allowed_roles The roles list
	 * @param null|RoleInterface          $at_least      when set and the user has none of the allowed roles,
	 *                                                   a role of higher or equal weight is accepted
	 * @param string                      $message
	 * @param null|array                  $data
	 * @param null|Throwable              $previous
	 *
	 * @throws ForbiddenException
	 * @throws UnauthenticatedException
	 */
	public function assertUserHasOneOfRoles(
		array $allowed_roles,
		?RoleInterface $at_least = null,
		string $message = 'OZ_ERROR_USER_IS_MISSING_REQUIRED_ROLE',
		?array $data = [],
		?Throwable $previous = null
	): void {
		$this->assertUserIsAuthenticated($message, $data, $previous);

		$user = $this->context->auth()->user();

		if (!Roles::hasOneOfRoles($user, $allowed_roles, $at_least)) {
			throw new ForbiddenException($message, $data + [
				'_allowed_roles' => RolesUtils::ensureRolesString($allowed_roles),
				'_at_least_role' => $at_least?->value,
			], $previous);
		}
	}

	/**
	 * Logon the auth user.
	 */
	public function logUserIn(AuthUserInterface $user): static
	{
		$auth_method = $this->context->requireStatefulAuth();
		$previous    = $auth_method->store()
			->getPreviousUser();
		$saved_data = [];

		// if the current user is the previous one,
		// keep the previous user data
		if ($previous && self::same($user, $previous)) {
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
	 */
	public function logUserOut(): static
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

			return self::checkPassword(null, $user_pass, self::selectorSubject($fd)) ?? 'OZ_AUTH_INVALID_CREDENTIALS';
		}

		return $this->tryLogIn($user, $user_pass);
	}

	/**
	 * Try to log on a user.
	 *
	 * @param AuthUserInterface $user
	 * @param string            $pass
	 *
	 * @return AuthUserInterface|string the user, or the failure code (see {@see self::checkPassword()})
	 */
	public function tryLogIn(AuthUserInterface $user, string $pass): AuthUserInterface|string
	{
		$error = self::checkPassword($user, $pass, self::ref($user));

		if (null !== $error) {
			if ('OZ_AUTH_INVALID_CREDENTIALS' === $error) {
				(new AuthUserLogInFailed($this->context, $user))->dispatch();
			}

			return $error;
		}

		$this->logUserIn($user);

		return $user;
	}

	/**
	 * Checks a password attempt, with brute-force protection.
	 *
	 * Known and unknown accounts get the same work and the same result, so a failure
	 * reveals nothing: an unknown account is checked against a dummy hash, and
	 * failures are counted per account (or per submitted identifier when unknown) by
	 * {@see LoginThrottle}. The account state is only revealed after a right password.
	 *
	 * Failure codes: `OZ_AUTH_TOO_MUCH_ATTEMPT` (locked), `OZ_AUTH_INVALID_CREDENTIALS`
	 * (unknown account or wrong password), `OZ_AUTH_USER_UNVERIFIED` (right password,
	 * account not usable yet).
	 *
	 * @param null|AuthUserInterface $user    the account, or null when the identifier matched none
	 * @param string                 $pass    the submitted password
	 * @param string                 $subject throttling subject used when `$user` is null
	 *
	 * @return null|string null when the password is right and the account usable
	 */
	public static function checkPassword(?AuthUserInterface $user, string $pass, string $subject): ?string
	{
		$subject = null !== $user ? self::ref($user) : $subject;

		if (LoginThrottle::isLocked($subject)) {
			return 'OZ_AUTH_TOO_MUCH_ATTEMPT';
		}

		$hash = null !== $user ? $user->getAuthPassword() : self::dummyPasswordHash();

		// Verify first, so an unknown account costs the same as a wrong password.
		if (!Password::verify($pass, $hash) || null === $user) {
			LoginThrottle::recordFailure($subject);

			return 'OZ_AUTH_INVALID_CREDENTIALS';
		}

		if (!$user->isAuthUserValid()) {
			return 'OZ_AUTH_USER_UNVERIFIED';
		}

		LoginThrottle::clear($subject);

		return null;
	}

	/**
	 * Throttling subject for a login attempt whose identifier matched no account.
	 */
	private static function selectorSubject(FormData|FormDataClean $fd): string
	{
		return \implode('|', [
			$fd->get(self::FIELD_AUTH_USER_TYPE),
			$fd->get(self::FIELD_AUTH_USER_ID),
			$fd->get(self::FIELD_AUTH_USER_IDENTIFIER_TYPE),
			$fd->get(self::FIELD_AUTH_USER_IDENTIFIER_VALUE),
		]);
	}

	/**
	 * A hash no password matches, to verify against when the account is unknown.
	 */
	private static function dummyPasswordHash(): string
	{
		static $hash = null;

		return $hash ??= Password::hash(Random::string(32));
	}
}
