<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Authenticator;

use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\Db\OZAuth;
use OZONE\OZ\Db\OZAuthenticatorQuery;

/**
 * Class Authenticator
 */
final class Authenticator
{
	const LABEL_REG = '~^[a-z0-9]{32}$~';

	/**
	 * @var string
	 */
	private $label;

	/**
	 * @var string
	 */
	private $for_hash;

	/**
	 * Contains generated authentication code, token ...
	 *
	 * @var array
	 */
	private $generated;

	/**
	 * The last auth message
	 *
	 * @var null|string
	 */
	private $message;

	/** @var \OZONE\OZ\Db\OZAuth */
	private $auth_object;

	/** @var int */
	private $auth_code_length = 6;

	/** @var bool */
	private $auth_code_alpha_num = false;

	/**
	 * Authenticator constructor.
	 *
	 * @param string $for_value the value to authenticate : email/phone number etc
	 * @param array  $options   Options
	 */
	public function __construct($for_value, array $options = [])
	{
		$this->label    = Hasher::genRandomHash(32);
		$this->for_hash = Hasher::hashIt($for_value);

		if (isset($options['auth_code_length'])) {
			$this->auth_code_length = (int) $options['auth_code_length'];
		}

		if (isset($options['auth_code_alpha_num'])) {
			$this->auth_code_alpha_num = (bool) $options['auth_code_alpha_num'];
		}
	}

	/**
	 * Checks if an authentication process was already started
	 *
	 * @throws \Exception
	 *
	 * @return bool
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
	 * @throws \Exception
	 *
	 * @return \OZONE\OZ\Authenticator\Authenticator
	 */
	public function generate($try_max = null, $lifetime = null)
	{
		if (empty($try_max)) {
			$try_max = (int) SettingsManager::get('oz.authenticator', 'OZ_AUTH_CODE_TRY_MAX');
		}

		if (empty($lifetime)) {
			$lifetime = (int) SettingsManager::get('oz.authenticator', 'OZ_AUTH_CODE_LIFE_TIME');
		}

		if (!\is_int($try_max) || !\is_int($lifetime) || $try_max <= 0 || $lifetime <= 0) {
			throw new \InvalidArgumentException('arguments should be unsigned integer.');
		}

		$expire = \time() + $lifetime;
		$code   = Hasher::genAuthCode($this->auth_code_length, $this->auth_code_alpha_num);
		$token  = Hasher::genAuthToken($code);

		// cancel any existing to prevent primary key duplication
		$this->cancel();

		$auth = new OZAuth();
		$auth->setLabel($this->label)
			 ->setFor($this->for_hash)
			 ->setCode($code)
			 ->setToken($token)
			 ->setTryMax($try_max)
			 ->setTryCount(0)
			 ->setExpire($expire)
			 ->setData(\json_encode([]))
			 ->save();

		$this->generated = [
			'auth_for'    => $this->for_hash,
			'auth_label'  => $this->label,
			'auth_expire' => $expire,
			'auth_code'   => $code,
			'auth_token'  => $token,
		];

		return $this;
	}

	/**
	 * Gets the generated authentication code token ... in an array
	 *
	 * @return array
	 */
	public function getGenerated()
	{
		if (empty($this->generated)) {
			\trigger_error('You should call Authenticator->generate() first.', \E_USER_ERROR);
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
	 * Sets label.
	 *
	 * The label and the value should be unique otherwise if an authentication
	 * exists for the same label and value it'll be overwritten, so be aware
	 *
	 * @param string $label
	 *
	 * @throws \Exception
	 *
	 * @return \OZONE\OZ\Authenticator\Authenticator
	 */
	public function setLabel($label)
	{
		if (!\is_string($label) || !\preg_match(self::LABEL_REG, $label)) {
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
	public function getRef()
	{
		return $this->label . $this->for_hash;
	}

	/**
	 * Validate the authentication process with code.
	 *
	 * @param int $code the code value
	 *
	 * @throws \Exception
	 *
	 * @return bool true when successful, false otherwise
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

			// checks if auth process has expired
			if ($auth->getExpire() <= \time()) {
				$msg = 'OZ_AUTH_CODE_EXPIRED';
				$this->cancel();
			} elseif ($rest >= 0 && $auth->getCode() === $code) {
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
	 * @throws \Exception
	 *
	 * @return bool true when successful, false otherwise
	 */
	public function validateToken($token)
	{
		$ok  = false;
		$msg = 'OZ_AUTH_PROCESS_INVALID';

		$auth = $this->get();

		if (!empty($auth)) {
			if ($auth->getExpire() <= \time()) {
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
	 * Cancel the authentication process.
	 *
	 * @throws \Exception
	 *
	 * @return int
	 */
	public function cancel()
	{
		$auth = new OZAuthenticatorQuery();

		return $auth->filterByLabel($this->label)
					->filterByFor($this->for_hash)
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

	/**
	 * Fetches the authentication data from database.
	 *
	 * @throws \Exception
	 *
	 * @return null|\OZONE\OZ\Db\OZAuth
	 */
	private function get()
	{
		if (!$this->auth_object) {
			$auth_table = new OZAuthenticatorQuery();
			$result     = $auth_table->filterByLabel($this->label)
									 ->filterByFor($this->for_hash)
									 ->find(1);

			$this->auth_object = $result->fetchClass();
		}

		return $this->auth_object;
	}
}
