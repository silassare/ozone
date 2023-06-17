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

namespace OZONE\Core\Users\Traits;

use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Crypt\Password;
use OZONE\Core\Db\Base\OZSession;
use OZONE\Core\Db\OZRolesQuery;
use OZONE\Core\Db\OZSessionsQuery;
use OZONE\Core\Db\OZUser;
use OZONE\Core\Db\OZUsersQuery;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Exceptions\UnverifiedUserException;
use OZONE\Core\Users\Users;
use Throwable;

/**
 * Trait UsersUtilsTrait.
 */
trait UsersUtilsTrait
{
	/**
	 * Identifies a user with email or phone or user id.
	 *
	 * @param string $username
	 *
	 * @return null|\OZONE\Core\Db\OZUser
	 */
	public static function identify(string $username): ?OZUser
	{
		if (\str_contains($username, '@')) {
			return self::withEmail($username);
		}

		if (\str_starts_with($username, '+')) {
			return self::withPhone($username);
		}

		return self::withID($username);
	}

	/**
	 * Search for registered user with a given phone number.
	 *
	 * No matter if user is valid or not.
	 *
	 * @param string $phone the phone number
	 *
	 * @return null|\OZONE\Core\Db\OZUser
	 */
	public static function withPhone(string $phone): ?OZUser
	{
		try {
			$u_table = new OZUsersQuery();

			return $u_table->wherePhoneIs($phone)
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to load user by phone.', [
				'phone' => $phone,
			], $t);
		}
	}

	/**
	 * Search for registered user with a given email address.
	 *
	 * No matter if user is valid or not.
	 *
	 * @param string $email the email address
	 *
	 * @return null|\OZONE\Core\Db\OZUser
	 */
	public static function withEmail(string $email): ?OZUser
	{
		try {
			$u_table = new OZUsersQuery();

			return $u_table->whereEmailIs($email)
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to load user by email.', [
				'email' => $email,
			], $t);
		}
	}

	/**
	 * Gets the user object with a given user id.
	 *
	 * @param string $uid the user id
	 *
	 * @return null|\OZONE\Core\Db\OZUser
	 */
	public static function withID(string $uid): ?OZUser
	{
		try {
			$uq = new OZUsersQuery();

			return $uq->whereIdIs($uid)
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to load user by id.', [
				'uid' => $uid,
			], $t);
		}
	}

	/**
	 * Gets active sessions for a given user id.
	 *
	 * @param string $user_id
	 *
	 * @return \OZONE\Core\Db\OZSession[]
	 */
	public static function getUserActiveSessions(string $user_id): array
	{
		$sq = new OZSessionsQuery();

		return $sq->whereUserIdIs($user_id)
			->whereExpireIsGt(\time())
			->find()
			->fetchAllClass();
	}

	/**
	 * Log user out from all active sessions.
	 *
	 * @param string $user_id
	 */
	public static function forceUserLogoutOnAllActiveSessions(string $user_id): void
	{
		$sq = new OZSessionsQuery();

		$sq->whereUserIdIs($user_id)
			->update([
				OZSession::COL_USER_ID => null,
			])
			->execute();
	}

	/**
	 * Update the given user password.
	 *
	 * @param \OZONE\Core\Db\OZUser $user         the target user
	 * @param string                $new_pass     the new password
	 * @param null|string           $current_pass the current pass
	 *
	 * @throws \OZONE\Core\Exceptions\UnauthorizedActionException
	 */
	public static function updatePass(OZUser $user, string $new_pass, ?string $current_pass = null): void
	{
		$known_pass_hash = $user->getPass();

		if ((null !== $current_pass) && !Password::verify($current_pass, $known_pass_hash)) {
			throw new UnauthorizedActionException('OZ_FIELD_PASS_INVALID');
		}

		if (Password::verify($new_pass, $known_pass_hash)) {
			throw new UnauthorizedActionException('OZ_PASSWORD_SAME_OLD_AND_NEW_PASS');
		}

		try {
			$user->setPass($new_pass)
				->save();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to update user pass.', [
				'uid' => $user->getID(),
			], $t);
		}

		self::forceUserLogoutOnAllActiveSessions($user->getID());
	}

	/**
	 * Gets a given user roles.
	 *
	 * @param string $uid The user id
	 *
	 * @return \OZONE\Core\Db\OZRole[]
	 */
	public static function roles(string $uid): array
	{
		$factory = function () use ($uid) {
			try {
				$qb = new OZRolesQuery();

				return $qb->whereUserIdIs($uid)
					->whereIsValid()
					->find(1)
					->fetchAllClass();
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to load user roles.', [
					'uid' => $uid,
				], $t);
			}
		};

		return CacheManager::runtime(__METHOD__)
			->factory($uid, $factory)
			->get();
	}

	/**
	 * Asserts if we have an authenticated user.
	 *
	 * @param null|string    $message
	 * @param null|array     $data
	 * @param null|Throwable $previous
	 *
	 * @throws \OZONE\Core\Exceptions\UnverifiedUserException
	 */
	public function assertUserVerified(
		?string $message = null,
		?array $data = [],
		?Throwable $previous = null
	): void {
		try {
			$user = $this->context->user();
		} catch (Throwable) {
			throw new UnverifiedUserException($message, $data, $previous);
		}

		if (!$user->isValid()) {
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
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 * @throws \OZONE\Core\Exceptions\UnverifiedUserException
	 */
	public function assertIsAdmin(
		string $message = 'OZ_ERROR_YOU_ARE_NOT_ADMIN',
		?array $data = [],
		?Throwable $previous = null
	): void {
		$this->assertUserVerified($message, $data, $previous);

		$uid = $this->context->user()
			->getID();

		if (!$uid || !self::isAdmin($uid)) {
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
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 * @throws \OZONE\Core\Exceptions\UnverifiedUserException
	 */
	public function assertIsEditor(
		string $message = 'OZ_ERROR_YOU_ARE_NOT_EDITOR',
		?array $data = [],
		?Throwable $previous = null
	): void {
		$this->assertUserVerified($message, $data, $previous);

		$uid = $this->context->user()
			->getID();

		if (!$uid || !self::isEditor($uid)) {
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
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 * @throws \OZONE\Core\Exceptions\UnverifiedUserException
	 */
	public function assertIsSuperAdmin(
		string $message = 'OZ_ERROR_YOU_ARE_NOT_SUPER_ADMIN',
		?array $data = [],
		?Throwable $previous = null
	): void {
		$this->assertUserVerified($message, $data, $previous);

		$uid = $this->context->user()
			->getID();

		if (!$uid || !self::isSuperAdmin($uid)) {
			throw new ForbiddenException($message, $data, $previous);
		}
	}

	/**
	 * Checks if the given id belongs to an user with super-admin role.
	 *
	 * @param string $uid The user id
	 *
	 * @return bool
	 */
	public static function isSuperAdmin(string $uid): bool
	{
		return self::hasRole($uid, Users::SUPER_ADMIN);
	}

	/**
	 * Checks if the given id belongs to an user with admin or super-admin role.
	 *
	 * @param string $uid The user id
	 *
	 * @return bool
	 */
	public static function isAdmin(string $uid): bool
	{
		return self::hasRole($uid, Users::ADMIN, false);
	}

	/**
	 * Checks if the given id belongs to an user with editor role.
	 *
	 * @param string $uid The user id
	 *
	 * @return bool
	 */
	public static function isEditor(string $uid): bool
	{
		return self::hasRole($uid, Users::EDITOR, false);
	}

	/**
	 * Checks if the user with the given id has a given role.
	 *
	 * @param string $uid    The user id
	 * @param string $role   The role
	 * @param bool   $strict In strict mode user should have one of the roles,
	 *                       in non-strict mode user should have one of the roles
	 *                       or be an admin or super-admin
	 *
	 * @return bool
	 */
	public static function hasRole(string $uid, string $role, bool $strict = true): bool
	{
		return self::hasOneRoleAtLeast($uid, [$role], $strict);
	}

	/**
	 * Checks if the user with the given id has at least one role in a given roles list.
	 *
	 * @param string   $uid           The user id
	 * @param string[] $allowed_roles The roles list
	 * @param bool     $strict        In strict mode user should have one of the roles,
	 *                                in non-strict mode user should have one of the roles
	 *                                or be an admin or super-admin
	 *
	 * @return bool
	 */
	public static function hasOneRoleAtLeast(string $uid, array $allowed_roles, bool $strict = true): bool
	{
		$roles         = Users::roles($uid);
		$allowed_roles = \array_fill_keys(\array_values($allowed_roles), 1);
		foreach ($roles as $entry) {
			$r = $entry->getName();
			if (isset($allowed_roles[$r])) {
				return true;
			}

			if (!$strict && (Users::ADMIN === $r || Users::SUPER_ADMIN === $r)) {
				return true;
			}
		}

		return false;
	}
}
