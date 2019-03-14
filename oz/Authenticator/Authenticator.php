<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Authenticator;

	use OZONE\OZ\Core\Hasher;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Db\OZAuth;
	use OZONE\OZ\Db\OZAuthenticatorQuery;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class Authenticator
	 *
	 * @package OZONE\OZ\Authenticator
	 */
	final class Authenticator
	{
		/**
		 * @var string
		 */
		private $label = null;

		/**
		 * @var string
		 */
		private $for_value = null;

		/**
		 * Contains generated authentication code, token ...
		 *
		 * @var array
		 */
		private $generated = null;

		/**
		 * The last auth message
		 *
		 * @var string|null
		 */
		private $message = null;

		/** @var \OZONE\OZ\Db\OZAuth */
		private $auth_object = null;

		/** @var int */
		private $auth_code_length = 6;
		/** @var bool */
		private $auth_code_alpha_num = false;

		/**
		 * Authenticator constructor.
		 *
		 * @param string $name      The authentication process name.
		 * @param string $for_value The value to authenticate : email/phone number etc.
		 * @param array  $options   Options
		 */
		function __construct($name, $for_value, array $options = [])
		{
			if (empty($name)) {
				$this->label = Hasher::genRandomHash(32);
			} else {
				$this->label = Hasher::hashIt($name);
			}

			if (isset($options["auth_code_length"])) {
				$this->auth_code_length = (int)$options["auth_code_length"];
			}

			if (isset($options["auth_code_alpha_num"])) {
				$this->auth_code_alpha_num = (bool)$options["auth_code_alpha_num"];
			}

			$this->for_value = $for_value;
		}

		/**
		 * Checks if an authentication process was already started
		 *
		 * @return bool
		 * @throws \Exception
		 */
		public function exists()
		{
			return !empty($this->get());
		}

		/**
		 * Generate new authentication code, token ...
		 *
		 * @param int $try_max  maximum failure count
		 * @param int $lifetime auth lifetime
		 *
		 * @return \OZONE\OZ\Authenticator\Authenticator
		 * @throws \Exception
		 */
		public function generate($try_max = null, $lifetime = null)
		{
			if (empty($try_max)) {
				$try_max = (int)SettingsManager::get('oz.authenticator', 'OZ_AUTH_CODE_TRY_MAX');
			}

			if (empty($lifetime)) {
				$lifetime = (int)SettingsManager::get('oz.authenticator', 'OZ_AUTH_CODE_LIFE_TIME');
			}

			if (!is_int($try_max) OR !is_int($lifetime) OR $try_max <= 0 OR $lifetime <= 0) {
				throw new \InvalidArgumentException('arguments should be unsigned integer.');
			}

			$expire = time() + $lifetime;
			$code   = Hasher::genAuthCode($this->auth_code_length, $this->auth_code_alpha_num);
			$token  = Hasher::genAuthToken($code);

			// cancel any existing to prevent primary key duplication
			$this->cancel();

			$auth = new OZAuth();
			$auth->setLabel($this->label)
				 ->setFor($this->for_value)
				 ->setCode($code)
				 ->setToken($token)
				 ->setTryMax($try_max)
				 ->setTryCount(0)
				 ->setExpire($expire)
				 ->save();

			$this->generated = [
				'auth_for_value' => $this->for_value,
				'auth_label'     => $this->label,
				'auth_expire'    => $expire,
				'auth_code'      => $code,
				'auth_token'     => $token
			];

			return $this;
		}

		/**
		 * Gets the generated authentication code token ... in an array
		 *
		 * @return array
		 *
		 * @throws \Exception
		 */
		public function getGenerated()
		{
			if (empty($this->generated)) {
				throw new \Exception('You should call Authenticator->generate() first.');
			}

			return $this->generated;
		}

		/**
		 * Gets the authentication label.
		 *
		 * @return string
		 */
		public function getLabel()
		{
			return $this->label;
		}

		/**
		 * Checks if you can use a given authentication label
		 *
		 * this doesn't check the validity of the label
		 *
		 * @param string $label
		 *
		 * @return bool
		 */
		public function canUseLabel($label)
		{
			if (!is_string($label) OR !preg_match('#^[a-z0-9]{32}$#', $label)) {
				return false;
			}

			return true;
		}

		/**
		 * Sets label.
		 *
		 * the label and the value should be unique otherwise if an authentication
		 * exists for the same label and value it'll be overwritten, so be aware
		 *
		 * @param string $label
		 *
		 * @return \OZONE\OZ\Authenticator\Authenticator
		 *
		 * @throws \Exception
		 */
		public function setLabel($label)
		{
			if (!$this->canUseLabel($label)) {
				throw new \Exception('Authenticator: invalid label');
			}

			$this->label = $label;

			return $this;
		}

		/**
		 * Gets the value we want to authenticate
		 *
		 * @return string
		 */
		public function getForValue()
		{
			return $this->for_value;
		}

		/**
		 * Validate the authentication process with code.
		 *
		 * @param int $code the code value
		 *
		 * @return bool            true when successful, false otherwise
		 * @throws \Exception
		 */
		public function validateCode($code)
		{
			$ok  = false;
			$msg = 'OZ_AUTH_PROCESS_INVALID';

			$auth = $this->get();

			if (!empty($auth)) {
				$try_max = $auth->getTryMax();
				$count   = $auth->getTryCount() + 1;
				$rest    = $try_max - $count;

				// check if auth process has expired
				if ($auth->getExpire() <= time()) {
					$msg = 'OZ_AUTH_CODE_EXPIRED';
					$this->cancel();
				} elseif ($rest >= 0 AND $auth->getCode() === $code) {
					// we don't exceed the auth_try_max and the code is valid
					$ok  = true;
					$msg = 'OZ_AUTH_CODE_OK';
					$this->cancel();
				} elseif ($rest <= 0) { /* it is our last tentative or we already exceed auth_try_max*/
					$msg = 'OZ_AUTH_CODE_EXCEED_MAX_FAIL';
					$this->cancel();
				} else { /*we have another chance*/
					$auth->setTryCount($auth->getTryCount() + 1)
						 ->save();
					$msg = 'OZ_AUTH_CODE_INVALID';
				}
			}

			$this->message = $msg;

			return $ok;
		}

		/**
		 * Validate the authentication process with token.
		 *
		 * only one tentative per authentication process are allowed with token
		 *
		 * @param string $token the token value
		 *
		 * @return bool                true when successful, false otherwise
		 * @throws \Exception
		 */
		public function validateToken($token)
		{
			$ok  = false;
			$msg = 'OZ_AUTH_PROCESS_INVALID';

			$auth = $this->get();

			if (!empty($auth)) {
				if ($auth->getExpire() <= time()) {
					$msg = 'OZ_AUTH_TOKEN_EXPIRED';
				} elseif ($auth->getToken() != $token) {
					$msg = 'OZ_AUTH_TOKEN_INVALID';
				} else {
					$ok  = true;
					$msg = 'OZ_AUTH_TOKEN_OK';
				}

				// only one tentative per authentication process are allowed with token
				$this->cancel();
			}

			$this->message = $msg;

			return $ok;
		}

		/**
		 * Fetch the authentication data from database.
		 *
		 * @return null|\OZONE\OZ\Db\OZAuth
		 * @throws \Exception
		 */
		private function get()
		{
			if (!$this->auth_object) {
				$auth_table = new OZAuthenticatorQuery();
				$result     = $auth_table->filterByLabel($this->label)
										 ->filterByFor($this->for_value)
										 ->find(1);

				$this->auth_object = $result->fetchClass();
			}

			return $this->auth_object;
		}

		/**
		 * Cancel the authentication process.
		 *
		 * @return int
		 * @throws \Exception
		 */
		public function cancel()
		{
			$auth = new OZAuthenticatorQuery();

			return $auth->filterByLabel($this->label)
						->filterByFor($this->for_value)
						->delete()
						->execute();
		}

		/**
		 * Gets the last message.
		 *
		 * @return null|string
		 */
		public function getMessage()
		{
			return $this->message;
		}
	}