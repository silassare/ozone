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

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Events\AuthUserLoggedIn;
use OZONE\Core\Auth\Events\AuthUserLoggedOut;
use OZONE\Core\Auth\Events\AuthUserLogInFailed;
use OZONE\Core\Auth\Events\AuthUserUnknown;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Interfaces\AuthUsersRepositoryInterface;
use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Crypt\Password;
use OZONE\Core\Db\Base\OZSession;
use OZONE\Core\Db\OZRole;
use OZONE\Core\Db\OZRolesQuery;
use OZONE\Core\Db\OZSessionsQuery;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Exceptions\UnverifiedUserException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use Throwable;

/**
 * Class AuthUsers.
 */
final class AuthUsers
{
	public const SUPER_ADMIN = 'super-admin'; // Owner(s)
	public const ADMIN       = 'admin';
	public const EDITOR      = 'editor';

	public const FIELD_AUTH_USER_TYPE             = 'auth_user_type';
	public const FIELD_AUTH_USER_ID               = 'auth_user_id';
	public const FIELD_AUTH_USER_IDENTIFIER_NAME  = 'auth_user_identifier_name';
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
	public static function ref(AuthUserInterface $user, string $separator = '.', ?string $identifier_name = null): string
	{
		if (null === $identifier_name) {
			return $user->getAuthUserTypeName() . $separator . $user->getAuthIdentifier();
		}

		$identifiers      = $user->getAuthIdentifiers();
		$identifier_value = $identifiers[$identifier_name] ?? null;

		if (null === $identifier_value) {
			throw new RuntimeException('Auth user identifier not defined.', [
				'_user'            => self::selector($user),
				'_identifier_name' => $identifier_name,
			]);
		}

		return $user->getAuthUserTypeName() . $separator . $identifier_name . $separator . $identifier_value;
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
				self::FIELD_AUTH_USER_IDENTIFIER_NAME  => $parts[1],
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
		return $a->getAuthUserTypeName() === $b->getAuthUserTypeName()
			&& $a->getAuthIdentifier() === $b->getAuthIdentifier();
	}

	/**
	 * Gets the user selector.
	 */
	public static function selector(AuthUserInterface $user): array
	{
		return [
			self::FIELD_AUTH_USER_TYPE => $user->getAuthUserTypeName(),
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

			/** @var class-string<AuthUsersRepositoryInterface> $class */
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

		$form->field(self::FIELD_AUTH_USER_ID)
			->required()->if()->isNull(self::FIELD_AUTH_USER_IDENTIFIER_NAME);

		$form->field(self::FIELD_AUTH_USER_IDENTIFIER_NAME)
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
		?string $identifier_name = null
	): ?AuthUserInterface {
		try {
			$repository = self::repository($user_type);
		} catch (Throwable $t) {
			// this may be an api user that is not providing a valid user type
			// for development purpose we log the error
			oz_logger()->warning($t);

			return null;
		}
		if (null === $identifier_name) {
			return $repository->getAuthUserByIdentifier($identifier_value);
		}

		return $repository->getAuthUserByNamedIdentifier($identifier_name, $identifier_value);
	}

	/**
	 * Identifies a user using auth user selector form data.
	 *
	 * @param array|FormData $selector
	 *
	 * @return null|AuthUserInterface
	 */
	public static function identifyBySelector(array|FormData $selector): ?AuthUserInterface
	{
		$fd = $selector instanceof FormData ? $selector : new FormData($selector);

		try {
			$fd = self::selectorForm()->validate($fd);
		} catch (Throwable) {
			return null;
		}

		$user_type       = $fd->get(self::FIELD_AUTH_USER_TYPE);
		$user_identifier = $fd->get(self::FIELD_AUTH_USER_ID);

		if (null === $user_identifier) {
			$user_identifier_name  = $fd->get(self::FIELD_AUTH_USER_IDENTIFIER_NAME);
			$user_identifier_value = $fd->get(self::FIELD_AUTH_USER_IDENTIFIER_VALUE);

			return self::identify($user_type, $user_identifier_value, $user_identifier_name);
		}

		return self::identify($user_type, $user_identifier);
	}

	/**
	 * Gets active sessions for a given user id.
	 *
	 * @param AuthUserInterface $user
	 *
	 * @return \OZONE\Core\Db\OZSession[]
	 */
	public static function getUserActiveSessions(AuthUserInterface $user): array
	{
		$sq = new OZSessionsQuery();

		return $sq->whereOwnerIdIs($user->getAuthIdentifier())
			->whereOwnerTypeIs($user->getAuthUserTypeName())
			->whereExpireIsGt(\time())
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
			->whereOwnerTypeIs($user->getAuthUserTypeName())
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
	 * @throws UnauthorizedActionException
	 */
	public static function updatePassword(AuthUserInterface $user, string $new_pass, ?string $current_pass = null): void
	{
		$known_pass_hash = $user->getAuthPassword();

		if ((null !== $current_pass) && !Password::verify($current_pass, $known_pass_hash)) {
			throw new UnauthorizedActionException('OZ_FIELD_PASS_INVALID');
		}

		if (Password::verify($new_pass, $known_pass_hash)) {
			throw new UnauthorizedActionException('OZ_PASSWORD_SAME_OLD_AND_NEW_PASS');
		}

		try {
			$user->setAuthPassword(Password::hash($new_pass))
				->save();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to update user pass.', [
				'user' => self::selector($user),
			], $t);
		}

		self::forceUserLogoutOnAllActiveSessions($user);
	}

	/**
	 * Gets role entry for a given user id and role.
	 *
	 * @param AuthUserInterface $user       the user
	 * @param string            $role       the role
	 * @param bool              $valid_only if true, only valid role will be returned
	 *
	 * @return null|OZRole
	 */
	public static function roleEntry(AuthUserInterface $user, string $role, bool $valid_only): ?OZRole
	{
		$qb = new OZRolesQuery();

		$qb->whereOwnerIdIs($user->getAuthIdentifier())
			->whereOwnerTypeIs($user->getAuthUserTypeName())
			->whereNameIs($role);

		if ($valid_only) {
			$qb->whereIsValid();
		}

		return $qb->find(1)
			->fetchClass();
	}

	/**
	 * Assigns a role to a given user.
	 *
	 * @param AuthUserInterface $user    the user
	 * @param string            $role    the role
	 * @param bool              $restore if true and the role is invalid, it will be restored
	 *
	 * @return OZRole
	 *
	 * @throws CRUDException
	 * @throws ORMException
	 * @throws GoblException
	 */
	public static function assignRole(AuthUserInterface $user, string $role, bool $restore = false): OZRole
	{
		$entry = self::roleEntry($user, $role, false);

		if ($entry) {
			if ($restore && !$entry->isValid()) {
				$entry->setIsValid(true)
					->save();
			}
		} else {
			$entry = new OZRole();

			$entry->setOwnerID($user->getAuthIdentifier())
				->setOwnerType($user->getAuthUserTypeName())
				->setName($role)
				->setIsValid(true)
				->save();
		}

		return $entry;
	}

	/**
	 * Revokes a role from a given user.
	 *
	 * @param AuthUserInterface $user the user
	 * @param string            $role the role
	 *
	 * @return bool
	 *
	 * @throws CRUDException
	 * @throws GoblException
	 * @throws ORMException
	 */
	public static function revokeRole(AuthUserInterface $user, string $role): bool
	{
		if ($entry = self::roleEntry($user, $role, false)) {
			$entry->setIsValid(false)
				->save();
		}

		return true;
	}

	/**
	 * Gets a given user roles.
	 *
	 * @param AuthUserInterface $user
	 *
	 * @return OZRole[]
	 */
	public static function roles(AuthUserInterface $user): array
	{
		$factory = static function () use ($user) {
			try {
				$qb = new OZRolesQuery();

				return $qb->whereOwnerIdIs($user->getAuthIdentifier())
					->whereOwnerTypeIs($user->getAuthUserTypeName())
					->whereIsValid()
					->find(1)
					->fetchAllClass();
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to load user roles.', [
					'user' => AuthUsers::selector($user),
				], $t);
			}
		};

		return CacheManager::runtime(__METHOD__)
			->factory(self::ref($user), $factory)
			->get();
	}

	/**
	 * Asserts if we have an authenticated user.
	 *
	 * @param null|string    $message
	 * @param null|array     $data
	 * @param null|Throwable $previous
	 *
	 * @throws UnverifiedUserException
	 */
	public function assertUserVerified(
		?string $message = null,
		?array $data = [],
		?Throwable $previous = null
	): void {
		try {
			$user = $this->context->auth()->user();
		} catch (Throwable) {
			throw new UnverifiedUserException($message, $data, $previous);
		}

		if (!$user->isAuthUserValid()) {
			throw new UnverifiedUserException($message, $data, $previous);
		}
	}

	/**
	 * Asserts if the authenticated user is a verified admin.
	 *
	 * @param string         $message
	 * @param null|array     $data
	 * @param null|Throwable $previous
	 *
	 * @throws ForbiddenException
	 * @throws UnverifiedUserException
	 */
	public function assertIsAdmin(
		string $message = 'OZ_ERROR_YOU_ARE_NOT_ADMIN',
		?array $data = [],
		?Throwable $previous = null
	): void {
		$this->assertUserVerified($message, $data, $previous);

		$user = $this->context->auth()->user();

		if (!self::isAdmin($user)) {
			throw new ForbiddenException($message, $data, $previous);
		}
	}

	/**
	 * Asserts if the authenticated user is a verified editor.
	 *
	 * @param string         $message
	 * @param null|array     $data
	 * @param null|Throwable $previous
	 *
	 * @throws ForbiddenException
	 * @throws UnverifiedUserException
	 */
	public function assertIsEditor(
		string $message = 'OZ_ERROR_YOU_ARE_NOT_EDITOR',
		?array $data = [],
		?Throwable $previous = null
	): void {
		$this->assertUserVerified($message, $data, $previous);

		$user = $this->context->auth()->user();

		if (!self::isEditor($user)) {
			throw new ForbiddenException($message, $data, $previous);
		}
	}

	/**
	 * Asserts if the authenticated user is a super-admin.
	 *
	 * @param string         $message
	 * @param null|array     $data
	 * @param null|Throwable $previous
	 *
	 * @throws ForbiddenException
	 * @throws UnverifiedUserException
	 */
	public function assertIsSuperAdmin(
		string $message = 'OZ_ERROR_YOU_ARE_NOT_SUPER_ADMIN',
		?array $data = [],
		?Throwable $previous = null
	): void {
		$this->assertUserVerified($message, $data, $previous);

		$user = $this->context->auth()->user();

		if (!self::isSuperAdmin($user)) {
			throw new ForbiddenException($message, $data, $previous);
		}
	}

	/**
	 * Checks if the given id belongs to an user with super-admin role.
	 *
	 * @param AuthUserInterface $user
	 *
	 * @return bool
	 */
	public static function isSuperAdmin(AuthUserInterface $user): bool
	{
		return self::hasRole($user, self::SUPER_ADMIN);
	}

	/**
	 * Checks if the given id belongs to an user with admin or super-admin role.
	 *
	 * @param AuthUserInterface $user
	 *
	 * @return bool
	 */
	public static function isAdmin(AuthUserInterface $user): bool
	{
		return self::hasRole($user, self::ADMIN, false);
	}

	/**
	 * Checks if the given id belongs to an user with editor role.
	 *
	 * @param AuthUserInterface $user
	 *
	 * @return bool
	 */
	public static function isEditor(AuthUserInterface $user): bool
	{
		return self::hasRole($user, self::EDITOR, false);
	}

	/**
	 * Checks if the user with the given id has a given role.
	 *
	 * @param AuthUserInterface $user
	 * @param string            $role   The role
	 * @param bool              $strict In strict mode user should have one of the roles,
	 *                                  in non-strict mode user should have one of the roles
	 *                                  or be an admin or super-admin
	 *
	 * @return bool
	 */
	public static function hasRole(AuthUserInterface $user, string $role, bool $strict = true): bool
	{
		return self::hasOneRoleAtLeast($user, [$role], $strict);
	}

	/**
	 * Checks if the user with the given id has at least one role in a given roles list.
	 *
	 * @param AuthUserInterface $user
	 * @param string[]          $allowed_roles The roles list
	 * @param bool              $strict        In strict mode user should have one of the roles,
	 *                                         in non-strict mode user should have one of the roles
	 *                                         or be an admin or super-admin
	 *
	 * @return bool
	 */
	public static function hasOneRoleAtLeast(AuthUserInterface $user, array $allowed_roles, bool $strict = true): bool
	{
		$roles         = self::roles($user);
		$allowed_roles = \array_fill_keys(\array_values($allowed_roles), 1);
		foreach ($roles as $entry) {
			$r = $entry->getName();
			if (isset($allowed_roles[$r])) {
				return true;
			}

			if (!$strict && (self::ADMIN === $r || self::SUPER_ADMIN === $r)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Logon the auth user.
	 *
	 * @return $this
	 */
	public function logUserIn(AuthUserInterface $user): self
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
		if (!$user->isAuthUserValid()) {
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
