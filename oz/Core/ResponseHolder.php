<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Core;

\defined('OZ_SELF_SECURITY_CHECK') || die;

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
	 * Gets response with a given label or get all responses if no label is given
	 *
	 * @param null|string $label
	 *
	 * @return null|array
	 */
	public static function getResponses($label = null)
	{
		if (!empty($label)) {
			if (\array_key_exists($label, self::$responses)) {
				return self::$responses[$label];
			}

			return null;
		}

		return self::$responses;
	}

	/**
	 * ResponseHolder constructor.
	 *
	 * @param string $label the response label
	 */
	public function __construct($label)
	{
		$this->label             = $label;
		self::$responses[$label] = [
			'error' => self::RESPONSE_CODE_DONE,
			'msg'   => 'OK',
			'data'  => [],
		];
	}

	/**
	 * ResponseHolder destructor.
	 */
	public function __destruct()
	{
		unset(self::$responses[$this->label]);
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
		self::$responses[$this->label]['error'] = self::RESPONSE_CODE_ERROR;
		self::$responses[$this->label]['msg']   = $msg;

		return $this;
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
		self::$responses[$this->label]['error'] = self::RESPONSE_CODE_DONE;
		self::$responses[$this->label]['msg']   = $msg;

		return $this;
	}

	/**
	 * Adds data to the response
	 *
	 * @param array $data the data
	 *
	 * @return \OZONE\OZ\Core\ResponseHolder
	 */
	public function setData(array $data)
	{
		self::$responses[$this->label]['data'] = $data;

		return $this;
	}

	/**
	 * Sets a custom key/value to the response data
	 *
	 * @param string $key   the key name
	 * @param mixed  $value the value to be added
	 *
	 * @return $this
	 */
	public function setDataKey($key, $value)
	{
		if (!empty($key)) {
			self::$responses[$this->label]['data'][$key] = $value;
		}

		return $this;
	}

	/**
	 * Gets a custom key/value from the response data
	 *
	 * @param string     $key the key name
	 * @param null|mixed $def
	 *
	 * @return null|mixed
	 */
	public function getDataKey($key, $def = null)
	{
		if (isset(self::$responses[$this->label]['data'][$key])) {
			return self::$responses[$this->label]['data'][$key];
		}

		return $def;
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
	public function setResponse(self $response)
	{
		self::$responses[$this->label] = $response->getResponse();

		return $this;
	}
}
