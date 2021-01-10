<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\User;

use Exception;
use Gobl\DBAL\Rule;
use OZONE\OZ\Admin\AdminUtils;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\Session;
use OZONE\OZ\Core\SessionDataStore;
use OZONE\OZ\Crypt\DoCrypt;
use OZONE\OZ\Db\OZCountriesQuery;
use OZONE\OZ\Db\OZSessionsQuery;
use OZONE\OZ\Db\OZUser;
use OZONE\OZ\Db\OZUsersQuery;
use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Exceptions\InternalErrorException;
use OZONE\OZ\Exceptions\UnauthorizedActionException;
use OZONE\OZ\Exceptions\UnverifiedUserException;
use OZONE\OZ\Ofv\OFormValidator;
use OZONE\OZ\OZone;

/**
 * Class UsersManager
 */
final class UsersManager
{
	/**
	 * @var \OZONE\OZ\Core\Context
	 */
	private $context;

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
	public function userVerified()
	{
		$verified = $this->context->getSession()
								  ->get('ozone.user.verified');

		return $verified === true;
	}

	/**
	 * Asserts if the current user is verified
	 *
	 * @param null|\Exception|string $error_msg  the error message
	 * @param mixed                  $error_data the error data
	 *
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 * @throws string
	 */
	public function assertUserVerified($error_msg = 'OZ_ERROR_YOU_MUST_LOGIN', $error_data = null)
	{
		if (!$this->userVerified()) {
			if (!($error_msg instanceof Exception)) {
				$error_msg = new UnverifiedUserException($error_msg, $error_data);
			}

			throw $error_msg;
		}
	}

	/**
	 * Asserts if the current user is a verified admin
	 *
	 * @param null|\Exception|string $error_msg  the error message
	 * @param mixed                  $error_data the error data
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 */
	public function assertIsAdmin($error_msg = 'OZ_ERROR_YOU_ARE_NOT_ADMIN', $error_data = null)
	{
		if (!$this->userVerified()) {
			if (!($error_msg instanceof Exception)) {
				$error_msg = new UnverifiedUserException($error_msg, $error_data);
			}

			throw $error_msg;
		}

		if (!AdminUtils::isAdmin($this->getCurrentUserId())) {
			if (!($error_msg instanceof Exception)) {
				$error_msg = new ForbiddenException($error_msg, $error_data);
			}

			// force logout, take it serious, user try to access admin privilege
			$this->logUserOut();

			throw $error_msg;
		}
	}

	/**
	 * Gets the current user id.
	 *
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 *
	 * @return string
	 */
	public function getCurrentUserId()
	{
		$this->assertUserVerified();

		return $this->context->getSession()
							 ->get('ozone.user.id');
	}

	/**
	 * Gets current user session token.
	 *
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 *
	 * @return string
	 */
	public function getCurrentSessionToken()
	{
		$this->assertUserVerified();

		return $this->context->getSession()
							 ->get('ozone.user.token');
	}

	/**
	 * Gets the current user object.
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 *
	 * @return \OZONE\OZ\Db\OZUser
	 */
	public function getCurrentUserObject()
	{
		$this->assertUserVerified();

		$uid = $this->context->getSession()
							 ->get('ozone.user.id');

		return self::getUserObject($uid);
	}

	/**
	 * Logon the user that have the given user id.
	 *
	 * @param \OZONE\OZ\Db\OZUser $user the user object
	 *
	 * @throws \Throwable
	 *
	 * @return string the login token
	 */
	public function logUserIn(OZUser $user)
	{
		if (!$user->isSaved()) {
			// something is going wrong
			throw new InternalErrorException('OZ_USER_CANT_LOG_ON', $user->asArray());
		}

		$session     = $this->context->getSession();
		$current_uid = $session->get('ozone.user.id', null);
		$saved_data  = [];

		if (!empty($current_uid)) {
			// if the current user is the previous one,
			// save the data of the current session
			if ($current_uid === $user->getId()) {
				$saved_data = \array_merge($saved_data, $session->getData());
			}
		}

		try {
			$session->restart();

			foreach ($saved_data as $key => $value) {
				$session->set($key, $value);
			}

			$token = $session->attachUser($user)
							 ->getToken();
		} catch (Exception $e) {
			throw new InternalErrorException('OZ_USER_LOG_ON_FAIL', null, $e);
		}

		$session->set('ozone.user.id', $user->getId())
				->set('ozone.user.verified', true)
				->set('ozone.user.token', $token);

		OZone::getEventManager()
			 ->trigger('OZ_EVENT_USER_LOGIN', $user, ['token' => $token]);

		return $token;
	}

	/**
	 * Log the current user out.
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 */
	public function logUserOut()
	{
		if ($this->userVerified()) {
			$session = $this->context->getSession();

			try {
				$current_user = self::getCurrentUserObject();

				$session->restart();

				// may be useful
				$session->set('ozone.user.id', $current_user->getId());
			} catch (Exception $e) {
				throw new InternalErrorException('OZ_USER_LOG_OUT_FAIL', null, $e);
			}

			OZone::getEventManager()
				 ->trigger('OZ_EVENT_USER_LOGOUT', $current_user);
		}
	}

	/**
	 * Try to log on as the current client owner
	 *
	 * This is used when we have a user attached to the client
	 * The user right will be used every time the api key of the client is used
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Throwable
	 *
	 * @return \OZONE\OZ\Db\OZUser the user object or error string
	 */
	public function tryLogOnAsClientOwner()
	{
		$client = $this->context->getClient();
		$uid    = $client->getUserId();
		$owner  = null;

		if ($uid) {
			$uq    = new OZUsersQuery();
			$owner = $uq->filterById($uid)
						->find(1)
						->fetchClass();
		}

		if (!$owner) {
			throw new ForbiddenException('OZ_CLIENT_OWNER_IS_NOT_DEFINED');
		}

		if (!$owner->getValid()) {
			throw new ForbiddenException('OZ_CLIENT_OWNER_IS_DISABLED');
		}

		$this->logUserIn($owner);

		return $owner;
	}

	/**
	 * Try to log on a user with a given phone number and password.
	 *
	 * @param string $phone the phone number
	 * @param string $pass  the password
	 *
	 * @throws \Throwable
	 *
	 * @return \OZONE\OZ\Db\OZUser|string the user object or error string
	 */
	public function tryLogOnWithPhone($phone, $pass)
	{
		$fv_obj = new OFormValidator(['phone' => $phone, 'pass' => $pass]);

		$fv_obj->checkForm(['phone' => ['registered'], 'pass' => null]);

		$form  = $fv_obj->getForm();
		$phone = $form['phone'];
		$pass  = $form['pass'];

		$user = self::searchUserWithPhone($phone);

		if (!$user) {
			return 'OZ_FIELD_PHONE_NOT_REGISTERED';
		}

		if (!$user->getValid()) {
			return 'OZ_USER_INVALID';
		}

		$crypt_obj = new DoCrypt();

		if (!$crypt_obj->passCheck($pass, $user->getPass())) {
			return 'OZ_FIELD_PASS_INVALID';
		}

		$this->logUserIn($user);

		return $user;
	}

	/**
	 * Try to log on a user with a given email address and password.
	 *
	 * @param string $email the email address
	 * @param string $pass  the password
	 *
	 * @throws \Throwable
	 *
	 * @return \OZONE\OZ\Db\OZUser|string the user object or error string
	 */
	public function tryLogOnWithEmail($email, $pass)
	{
		$fv_obj = new OFormValidator(['email' => $email, 'pass' => $pass]);

		$fv_obj->checkForm(['email' => ['registered'], 'pass' => null]);

		$form  = $fv_obj->getForm();
		$email = $form['email'];
		$pass  = $form['pass'];

		$u = self::searchUserWithEmail($email);

		if (!$u) {
			return 'OZ_FIELD_EMAIL_NOT_REGISTERED';
		}

		if (!$u->getValid()) {
			return 'OZ_USER_INVALID';
		}

		$crypt_obj = new DoCrypt();

		if (!$crypt_obj->passCheck($pass, $u->getPass())) {
			return 'OZ_FIELD_PASS_INVALID';
		}

		$this->logUserIn($u);

		return $u;
	}

	/**
	 * Update the given user password.
	 *
	 * @param \OZONE\OZ\Db\OZUser $user         the target user
	 * @param string              $new_pass     the new password
	 * @param null|string         $current_pass the current pass
	 *
	 * @throws \Exception
	 *
	 * @return \OZONE\OZ\User\UsersManager
	 */
	public function updateUserPass(OZUser $user, $new_pass, $current_pass = null)
	{
		$real_pass_hash = $user->getPass();// encrypted
		$crypt_obj      = new DoCrypt();

		if ($current_pass != null) {
			if (!$crypt_obj->passCheck($current_pass, $real_pass_hash)) {
				throw new UnauthorizedActionException('OZ_FIELD_PASS_INVALID');
			}
		}

		if ($crypt_obj->passCheck($new_pass, $real_pass_hash)) {
			throw new UnauthorizedActionException('OZ_PASSWORD_SAME_OLD_AND_NEW_PASS');
		}

		$user->setPass($new_pass)
			 ->save();

		return $this;
	}

	/**
	 * @param \OZONE\OZ\Db\OZUser $user
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 *
	 * @return \OZONE\OZ\Db\OZSession[]
	 */
	public static function getUserActiveSessions(OZUser $user)
	{
		$sq = new OZSessionsQuery();

		return $sq->filterByUserId($user->getId())
				  ->filterByExpire(\time(), Rule::OP_GT)
				  ->find()
				  ->fetchAllClass();
	}

	/**
	 * @param \OZONE\OZ\Db\OZUser $user
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 */
	public static function forceLoginOnUserAttachedSessions(OZUser $user)
	{
		$sessions = self::getUserActiveSessions($user);

		foreach ($sessions as $session) {
			$decoded = Session::decode($session->getData());

			if (\is_array($decoded)) {
				$data_store = new SessionDataStore($decoded);
				$verified   = $data_store->get('ozone.user.verified');

				if ($verified) {
					$new_data = $data_store->remove('ozone.user.verified')
										   ->getStoreData();
					$session->setData(Session::encode($new_data))
							->save();
				}
			}
		}
	}

	/**
	 * Checks if a password match the user with a given phone number.
	 *
	 * @param string $phone the phone number
	 * @param string $pass  the password
	 *
	 * @throws \Exception
	 *
	 * @return bool true if password is ok, false otherwise
	 */
	public static function checkUserPassWithPhone($phone, $pass)
	{
		$user = self::searchUserWithPhone($phone);

		if ($user) {
			$crypt_obj = new DoCrypt();

			return $crypt_obj->passCheck($pass, $user->getPass());
		}

		return false;
	}

	/**
	 * Checks if a password match the user with a given email address.
	 *
	 * @param string $email the email address
	 * @param string $pass  the password
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 *
	 * @return bool true if password is ok, false otherwise
	 */
	public static function checkUserPassWithEmail($email, $pass)
	{
		$user = self::searchUserWithEmail($email);

		if ($user) {
			$crypt_obj = new DoCrypt();

			return $crypt_obj->passCheck($pass, $user->getPass());
		}

		return false;
	}

	/**
	 * Search for registered user with a given phone number.
	 *
	 * No matter if user is valid or not.
	 *
	 * @param string $phone the phone number
	 *
	 * @throws \Exception
	 *
	 * @return null|\OZONE\OZ\Db\OZUser
	 */
	public static function searchUserWithPhone($phone)
	{
		$u_table = new OZUsersQuery();
		$result  = $u_table->filterByPhone($phone)
						   ->find(1);

		return $result->fetchClass();
	}

	/**
	 * Search for registered user with a given email address.
	 *
	 * No matter if user is valid or not.
	 *
	 * @param string $email the email address
	 *
	 * @throws \Exception
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 *
	 * @return null|\OZONE\OZ\Db\OZUser
	 */
	public static function searchUserWithEmail($email)
	{
		$u_table = new OZUsersQuery();
		$result  = $u_table->filterByEmail($email)
						   ->find(1);

		return $result->fetchClass();
	}

	/**
	 * Gets the country info with a given cc2 (country code 2).
	 *
	 * @param string $cc2 the country code 2
	 *
	 * @throws \Exception
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 *
	 * @return null|\OZONE\OZ\Db\OZCountry
	 */
	public static function getCountryObject($cc2)
	{
		if (!empty($cc2) && \is_string($cc2) && \strlen($cc2) === 2) {
			$cq     = new OZCountriesQuery();
			$result = $cq->filterByCc2($cc2)
						 ->find(1);

			return $result->fetchClass();
		}

		return null;
	}

	/**
	 * Checks if a country (with a given cc2) is authorized or not.
	 *
	 * @param string $cc2 the country code 2
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 *
	 * @return bool
	 */
	public static function authorizedCountry($cc2)
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
	 * @param int|string $uid the user id
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return null|\OZONE\OZ\Db\OZUser
	 */
	public static function getUserObject($uid)
	{
		try {
			$uq = new OZUsersQuery();

			return $uq->filterById($uid)
					  ->find(1)
					  ->fetchClass();
		} catch (Exception $e) {
			throw new InternalErrorException('OZ_USER_CANT_LOAD_USER_ENTITY', ['id' => $uid], $e);
		}
	}
}
