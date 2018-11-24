<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User;

	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\SessionsData;
	use OZONE\OZ\Core\SessionsHandler;
	use OZONE\OZ\Crypt\DoCrypt;
	use OZONE\OZ\Db\OZCountriesQuery;
	use OZONE\OZ\Db\OZUser;
	use OZONE\OZ\Db\OZUsersQuery;
	use OZONE\OZ\Exceptions\UnverifiedUserException;
	use OZONE\OZ\Ofv\OFormValidator;
	use OZONE\OZ\OZone;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class UsersUtils
	 *
	 * @package OZONE\OZ\User
	 */
	final class UsersUtils
	{
		/**
		 * Gets the current user id.
		 *
		 * @return string|int
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function getCurrentUserId()
		{
			Assert::assertUserVerified();
			$uid = SessionsData::get('ozone_user:id');

			return $uid;
		}

		/**
		 * Gets current user session token.
		 *
		 * @return string
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 */
		public static function getCurrentSessionToken()
		{
			Assert::assertUserVerified();

			return SessionsData::get('ozone_user:token');
		}

		/**
		 * Gets the current user object.
		 *
		 * @return \OZONE\OZ\Db\OZUser
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 */
		public static function getCurrentUserObject()
		{
			Assert::assertUserVerified();
			$uid = SessionsData::get('ozone_user:id');

			return self::getUserObject($uid);
		}

		/**
		 * Gets the user object with a given user id.
		 *
		 * @param int|string $uid the user id
		 *
		 * @return null|\OZONE\OZ\Db\OZUser
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public static function getUserObject($uid)
		{
			$u_table = new OZUsersQuery();

			return $u_table->filterById($uid)
						   ->find(1)
						   ->fetchClass();
		}

		/**
		 * Checks if the current user is verified.
		 *
		 * @return bool    true when user is verified, false otherwise
		 * @throws \Exception
		 */
		public static function userVerified()
		{
			$user_verified = SessionsData::get('ozone_user:verified');

			return $user_verified === true;
		}

		/**
		 * Logon the user that have the given user id.
		 *
		 * @param \OZONE\OZ\Db\OZUser $user the user object
		 *
		 * @return string the login token
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 * @throws \Exception
		 */
		public static function logUserIn(OZUser $user)
		{
			// TODO
			// when user change his password, force login again, by deleting :
			//	- all oz_sessions rows associated with user
			//	- all oz_clients_users rows associated with user

			if (!$user->isSaved()) {
				// deleted account or something is going wrong
				throw new UnverifiedUserException();
			}

			$saved_data  = [];
			$current_uid = SessionsData::get('ozone_user:id');
			if (!empty($current_uid)) {                // if the current user is the previous one,
				// save the data of the previous session
				if ($current_uid === $user->getId()) {
					$saved_data = array_merge($saved_data, $_SESSION);
				}
			}

			SessionsHandler::restart();

			foreach ($saved_data as $k => $v) {
				$_SESSION[$k] = $v;
			}

			$token = SessionsHandler::attachUser($user)
									->getToken();

			SessionsData::set('ozone_user:id', $user->getId());
			SessionsData::set('ozone_user:verified', true);
			SessionsData::set('ozone_user:token', $token);

			OZone::getEventManager()
				 ->trigger('OZ_EVENT:USER_LOGIN', $user, ['token' => $token]);

			return $token;
		}

		/**
		 * Log out the current user.
		 *
		 * @throws \Exception
		 */
		public static function logUserOut()
		{
			if (self::userVerified()) {
				$current_user = self::getCurrentUserObject();

				SessionsHandler::restart();

				// may be useful
				SessionsData::set('ozone_user:id', $current_user->getId());

				OZone::getEventManager()
					 ->trigger('OZ_EVENT:USER_LOGOUT', $current_user);
			}
		}

		/**
		 * Checks if a password match the user with a given phone number.
		 *
		 * @param string $phone the phone number
		 * @param string $pass  the password
		 *
		 * @return bool    true if password is ok, false otherwise
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public static function checkUserPassWithPhone($phone, $pass)
		{
			$u = self::searchUserWithPhone($phone);
			if ($u) {
				$crypt_obj = new DoCrypt();

				return $crypt_obj->passCheck($pass, $u->getPass());
			}

			return false;
		}

		/**
		 * Checks if a password match the user with a given email address.
		 *
		 * @param string $email the email address
		 * @param string $pass  the password
		 *
		 * @return bool    true if password is ok, false otherwise
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public static function checkUserPassWithEmail($email, $pass)
		{
			$u = self::searchUserWithEmail($email);
			if ($u) {
				$crypt_obj = new DoCrypt();

				return $crypt_obj->passCheck($pass, $u->getPass());
			}

			return false;
		}

		/**
		 * Try to log on a user with a given phone number and password.
		 *
		 * Make sure that user is a valid user.
		 *
		 * @param string $phone the phone number
		 * @param string $pass  the password
		 *
		 * @return \OZONE\OZ\Db\OZUser|string the user object or error string
		 * @throws \Exception
		 */
		public static function tryLogOnWithPhone($phone, $pass)
		{
			$fv_obj = new OFormValidator(["phone" => $phone, "pass" => $pass]);

			$fv_obj->checkForm(['phone' => ['registered'], 'pass' => null]);

			$form  = $fv_obj->getForm();
			$phone = $form['phone'];
			$pass  = $form['pass'];

			$u = self::searchUserWithPhone($phone);

			if (!$u) {
				return 'OZ_FIELD_PHONE_NOT_REGISTERED';
			}

			if (!$u->getValid()) {
				return 'OZ_USER_INVALID';
			}

			$crypt_obj = new DoCrypt();
			if (!$crypt_obj->passCheck($pass, $u->getPass())) {
				return 'OZ_FIELD_PASS_INVALID';
			}

			self::logUserIn($u);

			return $u;
		}

		/**
		 * Try to log on a user with a given email address and password.
		 *
		 * Make sure that user is a valid user.
		 *
		 * @param string $email the email address
		 * @param string $pass  the password
		 *
		 * @return \OZONE\OZ\Db\OZUser|string the user object or error string
		 * @throws \Exception
		 */
		public static function tryLogOnWithEmail($email, $pass)
		{
			$fv_obj = new OFormValidator(["email" => $email, "pass" => $pass]);

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

			self::logUserIn($u);

			return $u;
		}

		/**
		 * Search for registered user with a given phone number.
		 *
		 * No matter if user is valid or not.
		 *
		 * @param string $phone the phone number
		 *
		 * @return null|\OZONE\OZ\Db\OZUser
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Exception
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
		 * @return null|\OZONE\OZ\Db\OZUser
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Exception
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
		 * @return null|\OZONE\OZ\Db\OZCountry
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Exception
		 */
		public static function getCountryObject($cc2)
		{
			if (!empty($cc2) AND is_string($cc2) AND strlen($cc2) === 2) {
				$c_table = new OZCountriesQuery();
				$result  = $c_table->filterByCc2($cc2)
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
		 * @return bool
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public static function authorizedCountry($cc2)
		{
			$c = self::getCountryObject($cc2);

			if ($c) {
				return $c->getValid();
			}

			return false;
		}
	}
