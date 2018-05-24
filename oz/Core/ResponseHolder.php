<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class ResponseHolder
	{
		/**
		 * @var int ozone done response code
		 */
		const RESPONSE_CODE_DONE = 0;

		/**
		 * @var int ozone error response code
		 */
		const RESPONSE_CODE_ERROR = 1;

		/**
		 * @var array map label to responses
		 */
		private static $responses = [];

		/**
		 * @var string response label
		 */
		private $label;

		/**
		 * ResponseHolder constructor.
		 *
		 * @param string $label the response label
		 */
		public function __construct($label)
		{
			$this->label             = $label;
			self::$responses[$label] = [
				'error' => self::RESPONSE_CODE_DONE
			];
		}

		/**
		 * Gets responses holder instance for a given label
		 *
		 * @param string $label the response label
		 *
		 * @return \OZONE\OZ\Core\ResponseHolder
		 */
		public static function getInstance($label)
		{
			return new self($label);
		}

		/**
		 * @param string $msg  the response message
		 * @param int    $code the response code
		 *
		 * @return $this
		 */
		private function setMessage($msg, $code)
		{
			return $this->setKey('error', $code)
						->setKey('msg', $msg);
		}

		/**
		 * Sets an error message
		 *
		 * @param string $msg the error message
		 *
		 * @return \OZONE\OZ\Core\ResponseHolder
		 */
		public function setError($msg = 'OZ_ERROR_INTERNAL')
		{
			return $this->setMessage($msg, self::RESPONSE_CODE_ERROR);
		}

		/**
		 * Sets a successful message
		 *
		 * @param string $msg the message
		 *
		 * @return \OZONE\OZ\Core\ResponseHolder
		 */
		public function setDone($msg = 'OK')
		{
			return $this->setMessage($msg, self::RESPONSE_CODE_DONE);
		}

		/**
		 * add data to the response
		 *
		 * @param array $data the data
		 *
		 * @return \OZONE\OZ\Core\ResponseHolder
		 */
		public function setData($data)
		{
			return $this->setKey('data', $data);
		}

		/**
		 * add a custom key/value to the response
		 *
		 * @param string $key   the key name
		 * @param mixed  $value the value to be added
		 *
		 * @return $this
		 */
		public function setKey($key, $value)
		{
			if (!empty($key)) {
				self::$responses[$this->label][$key] = $value;
			}

			return $this;
		}

		/**
		 * Gets the response from the response holder instance
		 *
		 * @return array
		 */
		public function getResponse()
		{
			return self::$responses[$this->label];
		}

		/**
		 * Sets the response from a given response holder instance
		 *
		 * @param \OZONE\OZ\Core\ResponseHolder $response
		 *
		 * @return $this
		 */
		public function setResponse(ResponseHolder $response)
		{
			self::$responses[$this->label] = $response->getResponse();

			return $this;
		}

		/**
		 * Gets response with a given label or get all responses if no label is given
		 *
		 * @param null|string $label
		 *
		 * @return array|null
		 */
		public static function getResponses($label = null)
		{
			if (!empty($label)) {
				if (array_key_exists($label, self::$responses)) {
					return self::$responses[$label];
				} else {
					return null;
				}
			}

			return self::$responses;
		}
	}