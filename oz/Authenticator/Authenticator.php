<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Authenticator;

	use OZONE\OZ\Core\OZoneDb;
	use OZONE\OZ\Core\OZoneKeyGen;
	use OZONE\OZ\Core\OZoneSettings;

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
		private $forValue = null;

		/**
		 * contains generated authentication code, token ...
		 *
		 * @var array
		 */
		private $generated = null;

		/**
		 * the last auth message
		 *
		 * @var string|null
		 */
		private $message = null;

		/**
		 * Authenticator constructor.
		 *
		 * @param string|null $name     The authentication proccess name.
		 * @param string      $forValue The value to authenticate : email/phone number etc.
		 */
		function __construct($name = null, $forValue)
		{
			if (empty($name)) {
				$this->label = OZoneKeyGen::genRandomHash(32);
			} else {
				$this->label = OZoneKeyGen::hashIt($name);
			}

			$this->forValue = $forValue;
		}

		/**
		 * check if an authentication process was already started
		 *
		 * @return bool
		 */
		public function exists()
		{
			return !empty($this->get());
		}

		/**
		 * generate new authentication code, token ...
		 *
		 * @param int $tryMax
		 * @param int $lifeTime
		 *
		 * @return \OZONE\OZ\Authenticator\Authenticator
		 * @throws \Exception
		 */
		public function generate($tryMax = null, $lifeTime = null)
		{
			if (empty($tryMax)) {
				$tryMax = intval(OZoneSettings::get('oz.authenticator', 'OZ_AUTH_CODE_TRY_MAX'));
			}

			if (empty($lifeTime)) {
				$lifeTime = intval(OZoneSettings::get('oz.authenticator', 'OZ_AUTH_CODE_LIFE_TIME'));
			}

			if (!($tryMax > 0 AND $lifeTime > 0 AND is_int($tryMax) AND is_int($lifeTime))) {
				throw new \Exception('invalid tryMax and lifeTime, you should provide natural integer values');
			}

			$expire = time() + $lifeTime;
			$code   = OZoneKeyGen::genAuthCode();
			$token  = OZoneKeyGen::genAuthToken($code);

			$sql = "
				INSERT INTO
					oz_authenticator ( auth_label , auth_for , auth_code , auth_token, auth_try_max , auth_try_count , auth_expire )
				VALUES ( :label , :forv , :code , :token, :tmax , :tcount , :expire )
				ON DUPLICATE KEY
				UPDATE auth_code =:code , auth_token =:token , auth_try_count =:tcount, auth_expire =:expire";

			OZoneDb::getInstance()
				   ->insert($sql, [
					   'label'  => $this->label,
					   'forv'   => $this->forValue,
					   'code'   => $code,
					   'token'  => $token,
					   'tmax'   => $tryMax,
					   'tcount' => 0,
					   'expire' => $expire
				   ]);

			$this->generated = [
				'authForValue' => $this->forValue,
				'authLabel'    => $this->label,
				'authExpire'   => $expire,
				'authCode'     => $code,
				'authToken'    => $token
			];

			return $this;
		}

		/**
		 * get the generated authentication code token ... in an array
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
		 * get this authenticator label
		 *
		 * @return string
		 */
		public function getLabel()
		{
			return $this->label;
		}

		/**
		 * check if you can use a given authenticator label
		 *
		 * this doesn't check the validity of the label
		 *
		 * @param string $label
		 *
		 * @return bool
		 */
		public function canUseLabel($label)
		{
			if (!is_string($label) AND !preg_match('#^[a-z0-9]{32}$#', $label)) {
				return false;
			}

			return true;
		}

		/**
		 * set label
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
		 * get the value we want to authenticate
		 *
		 * @return string
		 */
		public function getForValue()
		{
			return $this->forValue;
		}

		/**
		 * validate this authentication process with code
		 *
		 * @param int $code the code value
		 *
		 * @return bool            true when successful, false otherwise
		 */
		public function validateCode($code)
		{
			$ok  = false;
			$msg = 'OZ_AUTH_PROCESS_INVALID';

			$data = $this->get();

			if (!empty($data)) {
				$try_max = intval($data['auth_try_max']);
				$count   = intval($data['auth_try_count']) + 1;
				$rest    = $try_max - $count;

				// check if auth process has expired
				if ($data['auth_expire'] <= time()) {
					$msg = 'OZ_AUTH_CODE_EXPIRED';
					$this->cancel();
				} elseif ($rest >= 0 AND $data['auth_code'] === $code) {
					// we don't exceed the auth_try_max and the code is valid
					$ok  = true;
					$msg = 'OZ_AUTH_CODE_OK';
					$this->cancel();
				} elseif ($rest <= 0) { /* it is our last tentative or we already exceed auth_try_max*/
					$msg = 'OZ_AUTH_CODE_EXCEED_MAX_FAIL';
					$this->cancel();
				} else { /*we have another chance*/
					$this->tryUpdateCounter();
					$msg = 'OZ_AUTH_CODE_INVALID';
				}
			}

			$this->message = $msg;

			return $ok;
		}

		/**
		 * validate this authentication process with token
		 *
		 * only one tentative per authentication process are allowed with token
		 *
		 * @param string $token the token value
		 *
		 * @return bool                true when successful, false otherwise
		 */
		public function validateToken($token)
		{
			$ok  = false;
			$msg = 'OZ_AUTH_PROCESS_INVALID';

			$data = $this->get();

			if (!empty($data)) {
				if ($data['auth_expire'] <= time()) {
					$msg = 'OZ_AUTH_TOKEN_EXPIRED';
				} elseif ($data['auth_token'] != $token) {
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
		 * retrieve this authentication data from database
		 *
		 * @return array|null;
		 */
		private function get()
		{
			$sql = "
				SELECT * FROM oz_authenticator
				WHERE auth_label =:label AND auth_for =:forv
				LIMIT 0,1";

			$req = OZoneDb::getInstance()
						  ->select($sql, ['label' => $this->label, 'forv' => $this->forValue]);

			oz_logger([$this->label, $this->forValue]);

			if ($req->rowCount() > 0) {
				$data = $req->fetch();
				$req->closeCursor();

				return $data;
			}

			return null;
		}

		/**
		 * cancel this authentication process
		 *
		 * @return int
		 */
		public function cancel()
		{
			$sql = "
				DELETE FROM oz_authenticator 
				WHERE auth_label =:label AND auth_for =:for";

			return OZoneDb::getInstance()
						  ->delete($sql, ['label' => $this->label, 'for' => $this->forValue]);
		}

		/**
		 * update the authentication counter when code fails
		 *
		 * @return int
		 */
		private function tryUpdateCounter()
		{
			$sql = "
				UPDATE oz_authenticator 
				SET auth_try_count = auth_try_count + 1
				WHERE auth_label =:label AND auth_for =:forv";

			return OZoneDb::getInstance()
						  ->update($sql, ['label' => $this->label, 'forv' => $this->forValue]);
		}

		/**
		 * return the last message
		 *
		 * @return null|string
		 */
		public function getMessage()
		{
			return $this->message;
		}
	}