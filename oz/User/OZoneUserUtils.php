<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User;

	use OZONE\OZ\Core\OZoneDb;
	use OZONE\OZ\Core\OZoneRequest;
	use OZONE\OZ\Core\OZoneSessions;
	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\Crypt\DoCrypt;
	use OZONE\OZ\Exceptions\OZoneUnverifiedUserException;
	use OZONE\OZ\OZone;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class OZoneUserUtils
	 *
	 * @package OZONE\OZ\User
	 */
	final class OZoneUserUtils
	{

		/**
		 * @param $uid
		 *
		 * @return \OZONE\OZ\User\OZoneUserBase
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError    when can't load 'oz.user' setting
		 * @throws \Exception                                when user class does not extends
		 *                                                   \OZONE\OZ\User\OZoneUserBase
		 */
		public static function getUserObject($uid)
		{
			$user_class      = OZoneSettings::get('oz.user', 'OZ_USER_CLASS');
			$user_obj        = OZone::obj($user_class, $uid);
			$user_base_class = 'OZONE\OZ\User\OZoneUserBase';

			if (is_subclass_of($user_obj, $user_base_class)) {
				/** @var \OZONE\OZ\User\OZoneUserBase $user_obj */
				return $user_obj;
			}

			throw new \Exception(sprintf('your custom user class "%s" should extends "%s".', $user_class, $user_base_class));
		}

		/**
		 * check if the current user is verified
		 *
		 * @return bool    true when user is verified, false otherwise
		 */
		public static function userVerified()
		{
			$user_verified = OZoneSessions::get('ozone_user:verified');

			// toute modification de la methode de verification pourait remettre en cause la politique de securite
			return !empty($user_verified) AND $user_verified === true;
		}

		/**
		 * logon the user that have the give user id
		 *
		 * @param string|int $uid the user id
		 *
		 * @return array the user data
		 * @throws \Exception
		 * @throws \OZONE\OZ\Exceptions\OZoneUnverifiedUserException when user not found in database or something went
		 *                                                           wrong
		 */
		public static function logOn($uid)
		{
			// TODO
			// when user change his password, force login again, by deleting :
			//	- all oz_sessions rows associated with user
			//	- all oz_clients_users rows associated with user

			$saved_data  = [];
			$current_uid = OZoneSessions::get('ozone_user:data:user_id');

			// si l'utilisateur courant est le precedent on sauvegarde les donnees de la precedente session
			if (!empty($current_uid) AND $current_uid === $uid) {
				$saved_data = array_merge($saved_data, $_SESSION);
			}

			// on demarre une nouvelle session
			OZoneSessions::restart();

			$user_data = self::getUserObject($uid)
							 ->getUserData();

			if (empty($user_data)) {
				// compte supprimer ou disfonctionnement
				throw new OZoneUnverifiedUserException();
			}

			foreach ($saved_data as $k => $v) {
				$_SESSION[$k] = $v;
			}

			// TODO why not ask if user really want to attach his account to this client?
			$token = OZoneRequest::getCurrentClient()
								 ->logUser($uid);

			OZoneSessions::set('ozone_user:data', OZoneDb::tryRemoveColumnsNameMask($user_data));

			// shortcuts
			OZoneSessions::set('ozone_user:verified', true);
			OZoneSessions::set('ozone_user:token', $token);

			$user_data['token'] = $token;

			return $user_data;
		}

		/**
		 * logout the current user
		 *
		 * @throws \Exception
		 */
		public static function logOut()
		{
			$current_user_data = OZoneSessions::get('ozone_user:data');

			// SILO: just unset don't destroy because we could need to set/get
			// some data to/from $_SESSION after loging out

			session_unset();

			if (is_array($current_user_data) AND isset($current_user_data['user_id'])) {
				OZoneRequest::getCurrentClient()
							->removeUser($current_user_data['user_id']);
				// peut etre utile
				OZoneSessions::set('ozone_user:data', $current_user_data);
			}
		}

		/**
		 * check if a password match a given field value and password
		 *
		 * @param string $field the field name
		 * @param string $value the field value
		 * @param string $pass  the password
		 *
		 * @return bool    true if password is ok, false otherwise
		 */
		public static function passOk($field, $value, $pass)
		{
			$data = self::searchRegisteredUserWith($field, $value);

			if (empty($data)) {
				return false;
			}

			$crypt_obj = new DoCrypt();

			return $crypt_obj->passCheck($pass, $data['user_pass']);
		}

		/**
		 * try to logon a user with a given field value and password
		 *
		 * make sure that user is a valid user ( i.e: user_valid === 1 )
		 *
		 * @param string $field the field name
		 * @param string $value the field value
		 * @param string $pass  the password
		 *
		 * @return array|string the user data or error string
		 */
		public static function tryLogOnWith($field, $value, $pass)
		{
			$data      = self::searchRegisteredUserWith($field, $value);
			$crypt_obj = new DoCrypt();

			if (empty($data)) {
				return ($field == 'phone' ? 'OZ_FIELD_PHONE_NOT_REGISTERED' : 'OZ_FIELD_EMAIL_NOT_REGISTERED');
			}

			if (intval($data['user_valid']) !== 1) {
				return 'OZ_FIELD_USER_INVALID';
			}

			if (!$crypt_obj->passCheck($pass, $data['user_pass'])) {
				return 'OZ_FIELD_PASS_INVALID';
			}

			return self::logOn($data['user_id']);
		}

		/**
		 * search for registered user with a given field value and password
		 *
		 * no matter if user is valid or not
		 *
		 * @param string $field the field name
		 * @param string $value the field value
		 *
		 * @return array|null the user data if successful, null otherwise
		 * @throws
		 */

		public static function searchRegisteredUserWith($field, $value)
		{
			if ($field === 'phone') {
				$sql = "SELECT * FROM oz_users WHERE user_phone =:v LIMIT 0,1";
			} elseif ($field === 'email') {
				$sql = "SELECT * FROM oz_users WHERE user_email =:v LIMIT 0,1";
			} else {
				throw new \InvalidArgumentException("you should provide 'phone' or 'email'");
			}

			$req = OZoneDb::getInstance()
						  ->select($sql, ['v' => $value]);

			$data = null;

			if ($req->rowCount()) {
				$data = $req->fetch();
			}

			$req->closeCursor();

			return $data;
		}

		/**
		 * check if an user is already registered with a given field value (phone or email)
		 *
		 * @param string $field the field name
		 * @param string $value the filed value
		 *
		 * @return bool true if an user is using this value, false otherwise
		 */
		public static function registered($field, $value)
		{
			$data = self::searchRegisteredUserWith($field, $value);

			return !empty($data);
		}

		/**
		 * check if the phone could be a valid phone number
		 *
		 * @param string $phone the phone number
		 *
		 * @return bool
		 */
		public static function isPhoneNumberPossible($phone)
		{
			$number_reg = '#^\+\d{6,15}$#';

			return !!preg_match($number_reg, $phone);
		}

		/**
		 * get the country info with a given cc2 (country code 2)
		 *
		 * @param string $cc2 the country code 2
		 *
		 * @return array|null    array if country found, null otherwise
		 */
		public static function getCountry($cc2)
		{
			if (!empty($cc2) AND is_string($cc2) AND strlen($cc2) === 2) {
				$sql = "SELECT * FROM oz_countries WHERE country_cc2 = :c";

				$req = OZoneDb::getInstance()
							  ->select($sql, ['c' => $cc2]);

				if ($req->rowCount() > 0) {
					return OZoneDb::maskColumnsName($req->fetch(), ['country_cc2', 'country_ok', 'country_name']);
				}
			}

			return null;
		}

		/**
		 * check if a contry (with a given cc2) is authorized or not
		 *
		 * @param string $cc2 the country code 2
		 *
		 * @return bool
		 */
		public static function authorizedCountry($cc2)
		{
			if (!empty($cc2) AND is_string($cc2) AND strlen($cc2) === 2) {
				$sql = "SELECT * FROM oz_countries WHERE country_cc2 = :c AND country_ok = :ok";

				$req = OZoneDb::getInstance()
							  ->select($sql, ['c' => $cc2, 'ok' => 1]);

				if ($req->rowCount() > 0) {
					return true;
				}
			}

			return false;
		}
	}
