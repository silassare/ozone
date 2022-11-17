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

namespace OZONE\OZ\Users;

use OZONE\OZ\Cache\CacheManager;
use OZONE\OZ\Columns\Types\TypeEmail;
use OZONE\OZ\Columns\Types\TypePassword;
use OZONE\OZ\Columns\Types\TypePhone;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Crypt\DoCrypt;
use OZONE\OZ\Db\Base\OZRole;
use OZONE\OZ\Db\OZCountriesQuery;
use OZONE\OZ\Db\OZCountry;
use OZONE\OZ\Db\OZRolesQuery;
use OZONE\OZ\Db\OZSessionsQuery;
use OZONE\OZ\Db\OZUser;
use OZONE\OZ\Db\OZUsersQuery;
use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Exceptions\UnauthorizedActionException;
use OZONE\OZ\Exceptions\UnverifiedUserException;
use OZONE\OZ\Forms\Field;
use OZONE\OZ\Forms\Form;
use OZONE\OZ\Forms\FormData;
use OZONE\OZ\Sessions\SessionDataStore;
use OZONE\OZ\Users\Events\UserLoggedIn;
use OZONE\OZ\Users\Events\UserLoggedOut;
use OZONE\OZ\Users\Events\UserLogInInvalidPass;
use OZONE\OZ\Users\Events\UserLogInUnknown;
use PHPUtils\Events\Event;
use Throwable;

/**
 * Class UsersManager.
 */
final class UsersManager
{
	public const ROLE_SUPER_ADMIN = 'super-admin'; // Owner(s)

	public const ROLE_ADMIN = 'admin';

	public const ROLE_EDITOR = 'editor';

	/**
	 * @var \OZONE\OZ\Core\Context
	 */
	private Context $context;

	/**
	 * UsersManager constructor.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 */
	public function __construct(Context $context)
	{
		$this->context = $context;
	}

	/**
	 * UsersManager destructor.
	 */
	public function __destruct()
	{
		unset($this->context);
	}

	/**
	 * Checks if the current user is verified.
	 *
	 * @return bool true when user is verified, false otherwise
	 */
	public function userVerified(): bool
	{
		return $this->context->getSession()
			->getDataStore()
			->getUserIsVerified();
	}

	/**
	 * Asserts if the current user is verified.
	 *
	 * @param string         $message
	 * @param null|array     $data
	 * @param null|Throwable $previous
	 *
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 */
	public function assertUserVerified(
		string $message = 'OZ_ERROR_YOU_MUST_LOGIN',
		?array $data = [],
		?Throwable $previous = null
	): void {
		if (!$this->userVerified()) {
			throw new UnverifiedUserException($message, $data, $previous);
		}
	}

	/**
	 * Asserts if the current user is a verified super-admin.
	 *
	 * @param string         $message
	 * @param null|array     $data
	 * @param null|Throwable $previous
	 *
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 */
	public function assertIsSuperAdmin(
		string $message = 'OZ_ERROR_YOU_ARE_NOT_SUPER_ADMIN',
		?array $data = [],
		?Throwable $previous = null
	): void {
		$this->assertUserVerified($message, $data, $previous);

		$uid = $this->getCurrentUserID();

		if (!$uid || !self::isSuperAdmin($uid)) {
			// force logout, take it serious, user try to access super-admin privilege
			$this->logUserOut();

			throw new ForbiddenException($message, $data, $previous);
		}
	}

	/**
	 * Asserts if the current user is a verified admin.
	 *
	 * @param string         $message
	 * @param null|array     $data
	 * @param null|Throwable $previous
	 *
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 */
	public function assertIsAdmin(
		string $message = 'OZ_ERROR_YOU_ARE_NOT_ADMIN',
		?array $data = [],
		?Throwable $previous = null
	): void {
		$this->assertUserVerified($message, $data, $previous);

		$uid = $this->getCurrentUserID();

		if (!$uid || !self::isAdmin($uid)) {
			// force logout, take it serious, user try to access admin privilege
			$this->logUserOut();

			throw new ForbiddenException($message, $data, $previous);
		}
	}

	/**
	 * Asserts if the current user is a verified editor.
	 *
	 * @param string         $message
	 * @param null|array     $data
	 * @param null|Throwable $previous
	 *
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 */
	public function assertIsEditor(
		string $message = 'OZ_ERROR_YOU_ARE_NOT_EDITOR',
		?array $data = [],
		?Throwable $previous = null
	): void {
		$this->assertUserVerified($message, $data, $previous);

		$uid = $this->getCurrentUserID();

		if (!$uid || !self::isEditor($uid)) {
			// force logout, take it serious, user try to access editor privilege
			$this->logUserOut();

			throw new ForbiddenException($message, $data, $previous);
		}
	}

	/**
	 * Gets the current user id.
	 *
	 * @return null|string
	 */
	public function getCurrentUserID(): ?string
	{
		return $this->context->getSession()
			->getDataStore()
			->getUserID();
	}

	/**
	 * Gets the current user object.
	 *
	 * @return \OZONE\OZ\Db\OZUser
	 *
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 */
	public function getCurrentUserObject(): OZUser
	{
		$this->assertUserVerified();

		$uid = $this->context->getSession()
			->getDataStore()
			->getUserID();

		return self::getUserObject($uid);
	}

	/**
	 * Logon the user that have the given user id.
	 *
	 * @param \OZONE\OZ\Db\OZUser $user the user object
	 *
	 * @return $this
	 */
	public function logUserIn(OZUser $user): self
	{
		if (!$user->isSaved()) {
			// something is going wrong
			throw new RuntimeException('OZ_USER_CANT_LOG_ON', $user->toArray());
		}

		$session     = $this->context->getSession();
		$current_uid = $session->getDataStore()
			->getUserID();
		$saved_data  = [];

		// if the current user is the previous one,
		// hold the data of the current session
		if (!empty($current_uid) && $current_uid === $user->getID()) {
			$saved_data = \array_merge($saved_data, $session->getDataStore()
				->getData());
		}

		try {
			$session->restart();

			$session->getDataStore()
				->merge($saved_data);

			$session->attachUser($user);
		} catch (Throwable $t) {
			throw new RuntimeException('OZ_USER_LOG_ON_FAIL', null, $t);
		}

		$session->getDataStore()
			->setUserIsVerified(true);

		Event::trigger(new UserLoggedIn($user));

		return $this;
	}

	/**
	 * Log the current user out.
	 */
	public function logUserOut(): self
	{
		if ($this->userVerified()) {
			$session = $this->context->getSession();

			try {
				$current_user = $this->getCurrentUserObject();

				$session->restart();
			} catch (Throwable $t) {
				throw new RuntimeException('OZ_USER_LOG_OUT_FAIL', null, $t);
			}

			Event::trigger(new UserLoggedOut($current_user));
		}

		return $this;
	}

	/**
	 * Try to log on as the current api client owner.
	 *
	 * This is used when we have a user attached to the api client
	 * The user right will be used every time the api key of the client is used
	 *
	 * @return \OZONE\OZ\Db\OZUser the user object or error string
	 *
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 */
	public function tryLogOnAsApiClientOwner(): OZUser
	{
		$session = $this->context->getSession();
		$client  = $session->getClient();
		$owner   = $client->getOwner();

		if (!$owner) {
			throw new ForbiddenException('OZ_API_CLIENT_OWNER_IS_NOT_DEFINED');
		}

		if (!$owner->getValid()) {
			throw new ForbiddenException('OZ_API_CLIENT_OWNER_IS_DISABLED');
		}

		$this->logUserIn($owner);

		return $owner;
	}

	/**
	 * Build a logon form.
	 *
	 * @param bool $phone
	 *
	 * @return \OZONE\OZ\Forms\Form
	 */
	public function buildLogOnForm(bool $phone): Form
	{
		$form = new Form();

		if ($phone) {
			$form->addField(new Field('phone', new TypePhone(), true));
		} else {
			$form->addField(new Field('email', new TypeEmail(), true));
		}

		$form->addField(new Field('pass', new TypePassword(), true));

		return $form;
	}

	/**
	 * Try to log on a user with a given phone number and password.
	 *
	 * @param \OZONE\OZ\Core\Context   $context
	 * @param \OZONE\OZ\Forms\FormData $form_data
	 *
	 * @return \OZONE\OZ\Db\OZUser|string the user object or error string
	 *
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 */
	public function tryLogOnWithPhone(Context $context, FormData $form_data): OZUser|string
	{
		$form = $this->buildLogOnForm(true)
			->validate($form_data);

		$phone = $form['phone'];
		$pass  = $form['pass'];

		$user = self::searchUserWithPhone($phone);

		if (!$user) {
			Event::trigger(new UserLogInUnknown($context));

			return 'OZ_FIELD_PHONE_NOT_REGISTERED';
		}

		if (!$user->getValid()) {
			return 'OZ_USER_INVALID';
		}

		$crypt_obj = new DoCrypt();

		if (!$crypt_obj->passCheck($pass, $user->getPass())) {
			Event::trigger(new UserLogInInvalidPass($context, $user));

			return 'OZ_FIELD_PASS_INVALID';
		}

		$this->logUserIn($user);

		return $user;
	}

	/**
	 * Try to log on a user with a given email address and password.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param FormData               $form_data
	 *
	 * @return \OZONE\OZ\Db\OZUser|string the user object or error string
	 *
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 */
	public function tryLogOnWithEmail(Context $context, FormData $form_data): OZUser|string
	{
		$form = $this->buildLogOnForm(false)
			->validate($form_data);

		$email = $form['email'];
		$pass  = $form['pass'];

		$user = self::searchUserWithEmail($email);

		if (!$user) {
			Event::trigger(new UserLogInUnknown($context));

			return 'OZ_FIELD_EMAIL_NOT_REGISTERED';
		}

		if (!$user->getValid()) {
			return 'OZ_USER_INVALID';
		}

		$crypt_obj = new DoCrypt();

		if (!$crypt_obj->passCheck($pass, $user->getPass())) {
			Event::trigger(new UserLogInInvalidPass($context, $user));

			return 'OZ_FIELD_PASS_INVALID';
		}

		$this->logUserIn($user);

		return $user;
	}

	/**
	 * Update the given user password.
	 *
	 * @param \OZONE\OZ\Db\OZUser $user         the target user
	 * @param string              $new_pass     the new password
	 * @param null|string         $current_pass the current pass
	 *
	 * @return \OZONE\OZ\Users\UsersManager
	 *
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	public function updateUserPass(OZUser $user, string $new_pass, ?string $current_pass = null): self
	{
		$real_pass_hash = $user->getPass(); // encrypted
		$crypt_obj      = new DoCrypt();

		if ((null !== $current_pass) && !$crypt_obj->passCheck($current_pass, $real_pass_hash)) {
			throw new UnauthorizedActionException('OZ_FIELD_PASS_INVALID');
		}

		if ($crypt_obj->passCheck($new_pass, $real_pass_hash)) {
			throw new UnauthorizedActionException('OZ_PASSWORD_SAME_OLD_AND_NEW_PASS');
		}

		try {
			$user->setPass($new_pass)
				->save();
		} catch (Throwable $t) {
			throw new RuntimeException('OZ_USER_CANT_UPDATE_PASS', null, $t);
		}

		return $this;
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
		return self::hasRole($uid, self::ROLE_SUPER_ADMIN, false);
	}

	/**
	 * Checks if the given id belongs to an user with admin role.
	 *
	 * @param string $uid The user id
	 *
	 * @return bool
	 */
	public static function isAdmin(string $uid): bool
	{
		return self::hasRole($uid, self::ROLE_ADMIN);
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
		return self::hasRole($uid, self::ROLE_EDITOR);
	}

	/**
	 * Checks if the user with the given id has a given role.
	 *
	 * @param string $uid      The user id
	 * @param string $role     The role
	 * @param bool   $or_admin Returns true if the user is an admin or super-admin
	 *
	 * @return bool
	 */
	public static function hasRole(string $uid, string $role, bool $or_admin = true): bool
	{
		return self::hasOneRoleAtLeast($uid, [$role], $or_admin);
	}

	/**
	 * Checks if the user with the given id has at least one role in a given roles list.
	 *
	 * @param string   $uid           The user id
	 * @param string[] $allowed_roles The roles list
	 * @param bool     $or_admin      Returns true if the user is an admin or super-admin
	 *
	 * @return bool
	 */
	public static function hasOneRoleAtLeast(string $uid, array $allowed_roles, bool $or_admin = true): bool
	{
		$roles         = self::getUserRoles($uid);
		$is_admin      = false;
		$allowed_roles = \array_fill_keys(\array_values($allowed_roles), 1);
		foreach ($roles as $entry) {
			$r = $entry->getName();
			if (isset($allowed_roles[$r])) {
				return true;
			}
			if (self::ROLE_ADMIN === $r || self::ROLE_SUPER_ADMIN === $r) {
				$is_admin = true;
			}
		}

		return $or_admin ? $is_admin : false;
	}

	/**
	 * @param \OZONE\OZ\Db\OZUser $user
	 *
	 * @return \OZONE\OZ\Db\OZSession[]
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public static function getUserActiveSessions(OZUser $user): array
	{
		$sq = new OZSessionsQuery();

		return $sq->whereUserIdIs($user->getID())
			->whereExpireIsGt(\time())
			->find()
			->fetchAllClass();
	}

	/**
	 * Log user out from all active sessions.
	 *
	 * @param \OZONE\OZ\Db\OZUser $user
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 */
	public static function forceUserLogoutOnActiveSessions(OZUser $user): void
	{
		$sessions = self::getUserActiveSessions($user);

		foreach ($sessions as $session) {
			$data_store = SessionDataStore::getInstance($session);
			$verified   = $data_store->getUserIsVerified();

			if ($verified) {
				$new_data = $data_store->setUserIsVerified(false)
					->getData();
				$session->setData($new_data)
					->save();
			}
		}
	}

	/**
	 * Search for registered user with a given phone number.
	 *
	 * No matter if user is valid or not.
	 *
	 * @param string $phone the phone number
	 *
	 * @return null|\OZONE\OZ\Db\OZUser
	 */
	public static function searchUserWithPhone(string $phone): ?OZUser
	{
		try {
			$u_table = new OZUsersQuery();

			return $u_table->wherePhoneIs($phone)
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException(\sprintf('Unable to load user with "%s" entity object.', $phone), null, $t);
		}
	}

	/**
	 * Search for registered user with a given email address.
	 *
	 * No matter if user is valid or not.
	 *
	 * @param string $email the email address
	 *
	 * @return null|\OZONE\OZ\Db\OZUser
	 */
	public static function searchUserWithEmail(string $email): ?OZUser
	{
		try {
			$u_table = new OZUsersQuery();

			return $u_table->whereEmailIs($email)
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException(\sprintf('Unable to load user with "%s" entity object.', $email), null, $t);
		}
	}

	/**
	 * Gets the country info with a given cc2 (country code 2).
	 *
	 * @param string $cc2 the country code 2
	 *
	 * @return null|\OZONE\OZ\Db\OZCountry
	 */
	public static function getCountryObject(string $cc2): ?OZCountry
	{
		if (!empty($cc2) && 2 === \strlen($cc2)) {
			try {
				$cq     = new OZCountriesQuery();

				return $cq->whereCc2Is($cc2)
					->find(1)
					->fetchClass();
			} catch (Throwable $t) {
				throw new RuntimeException(\sprintf('Unable to load country "%s" entity object.', $cc2), null, $t);
			}
		}

		return null;
	}

	/**
	 * Checks if a country (with a given cc2) is authorized or not.
	 *
	 * @param string $cc2 the country code 2
	 *
	 * @return bool
	 */
	public static function authorizedCountry(string $cc2): bool
	{
		$country = self::getCountryObject($cc2);

		if ($country) {
			return $country->getValid();
		}

		return false;
	}

	/**
	 * Gets the user object with a given user id.
	 *
	 * @param string $uid the user id
	 *
	 * @return null|\OZONE\OZ\Db\OZUser
	 */
	public static function getUserObject(string $uid): ?OZUser
	{
		try {
			$uq = new OZUsersQuery();

			return $uq->whereIdIs($uid)
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException(\sprintf('Unable to load user "%s" entity object.', $uid), null, $t);
		}
	}

	/**
	 * Gets users roles.
	 *
	 * @param string $uid The user id
	 *
	 * @return \OZONE\OZ\Db\OZRole[]
	 */
	private static function getUserRoles(string $uid): array
	{
		$factory = function () use ($uid) {
			try {
				$qb = new OZRolesQuery();

				return $qb->whereUserIdIs($uid)
					->whereValidIsTrue()
					->find(1)
					->fetchAllClass();
			} catch (Throwable $t) {
				throw new RuntimeException(
					\sprintf(
						'Unable to get user entries in "%s" table.',
						OZRole::TABLE_NAME
					),
					null,
					$t
				);
			}
		};

		return CacheManager::runtime(__METHOD__)
			->getFactory($uid, $factory)
			->get();
	}
}
