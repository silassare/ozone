<?php

/**
 * Auto generated file, please don't edit.
 *
 * With: Gobl v1.0.9
 * Time: 1586982104
 */

namespace OZONE\OZ\Db\Base;

use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntityBase;
use OZONE\OZ\Db\OZSessionsQuery as OZSessionsQueryReal;
use OZONE\OZ\Db\OZClientsController as OZClientsControllerRealR;
use OZONE\OZ\Db\OZUsersController as OZUsersControllerRealR;

/**
 * Class OZSession
 *
 * @package OZONE\OZ\Db\Base
 */
abstract class OZSession extends ORMEntityBase
{
	const TABLE_NAME = 'oz_sessions';

	
	const COL_ID = 'session_id';
	const COL_CLIENT_API_KEY = 'session_client_api_key';
	const COL_USER_ID = 'session_user_id';
	const COL_TOKEN = 'session_token';
	const COL_EXPIRE = 'session_expire';
	const COL_LAST_SEEN = 'session_last_seen';
	const COL_DATA = 'session_data';
	const COL_ADD_TIME = 'session_add_time';
	const COL_VALID = 'session_valid';

	
	/**
	 * @var \OZONE\OZ\Db\OZClient
	 */
	protected $_r_oz_client;

	/**
	 * @var \OZONE\OZ\Db\OZUser
	 */
	protected $_r_oz_user;



	/**
	 * OZSession constructor.
	 *
	 * @param bool $is_new True for new entity false for entity fetched
	 *                     from the database, default is true.
	 * @param bool $strict Enable/disable strict mode
	 */
	public function __construct($is_new = true, $strict = true)
	{
		parent::__construct(
			ORM::getDatabase('OZONE\OZ\Db'),
			$is_new,
			$strict,
			OZSession::TABLE_NAME,
			OZSessionsQueryReal::class
		);
	}
	
	/**
	 * ManyToOne relation between `oz_sessions` and `oz_clients`.
	 *
	 * @return null|\OZONE\OZ\Db\OZClient
	 * @throws \Throwable
	 */
	public function getOZClient()
	{
		if (!isset($this->_r_oz_client)) {
			$filters = [];
			if (!is_null($v = $this->getClientApiKey())) {
				$filters['client_api_key'] = $v;
			}
			if (empty($filters)) {
				return null;
			}

			$m = new OZClientsControllerRealR();
			$this->_r_oz_client = $m->getItem($filters);
		}

		return $this->_r_oz_client;
	}

	/**
	 * ManyToOne relation between `oz_sessions` and `oz_users`.
	 *
	 * @return null|\OZONE\OZ\Db\OZUser
	 * @throws \Throwable
	 */
	public function getOZUser()
	{
		if (!isset($this->_r_oz_user)) {
			$filters = [];
			if (!is_null($v = $this->getUserId())) {
				$filters['user_id'] = $v;
			}
			if (empty($filters)) {
				return null;
			}

			$m = new OZUsersControllerRealR();
			$this->_r_oz_user = $m->getItem($filters);
		}

		return $this->_r_oz_user;
	}


	
	/**
	 * Getter for column `oz_sessions`.`id`.
	 *
	 * @return string the real type is: string
	 */
	public function getId()
	{
		$column = self::COL_ID;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_sessions`.`id`.
	 *
	 * @param string $id
	 *
	 * @return static
	 */
	public function setId($id)
	{
		$column = self::COL_ID;
		$this->$column = $id;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`client_api_key`.
	 *
	 * @return string the real type is: string
	 */
	public function getClientApiKey()
	{
		$column = self::COL_CLIENT_API_KEY;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_sessions`.`client_api_key`.
	 *
	 * @param string $client_api_key
	 *
	 * @return static
	 */
	public function setClientApiKey($client_api_key)
	{
		$column = self::COL_CLIENT_API_KEY;
		$this->$column = $client_api_key;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`user_id`.
	 *
	 * @return string the real type is: bigint
	 */
	public function getUserId()
	{
		$column = self::COL_USER_ID;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_sessions`.`user_id`.
	 *
	 * @param string $user_id
	 *
	 * @return static
	 */
	public function setUserId($user_id)
	{
		$column = self::COL_USER_ID;
		$this->$column = $user_id;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`token`.
	 *
	 * @return string the real type is: string
	 */
	public function getToken()
	{
		$column = self::COL_TOKEN;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_sessions`.`token`.
	 *
	 * @param string $token
	 *
	 * @return static
	 */
	public function setToken($token)
	{
		$column = self::COL_TOKEN;
		$this->$column = $token;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`expire`.
	 *
	 * @return string the real type is: bigint
	 */
	public function getExpire()
	{
		$column = self::COL_EXPIRE;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_sessions`.`expire`.
	 *
	 * @param string $expire
	 *
	 * @return static
	 */
	public function setExpire($expire)
	{
		$column = self::COL_EXPIRE;
		$this->$column = $expire;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`last_seen`.
	 *
	 * @return string the real type is: bigint
	 */
	public function getLastSeen()
	{
		$column = self::COL_LAST_SEEN;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_sessions`.`last_seen`.
	 *
	 * @param string $last_seen
	 *
	 * @return static
	 */
	public function setLastSeen($last_seen)
	{
		$column = self::COL_LAST_SEEN;
		$this->$column = $last_seen;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`data`.
	 *
	 * @return string the real type is: string
	 */
	public function getData()
	{
		$column = self::COL_DATA;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_sessions`.`data`.
	 *
	 * @param string $data
	 *
	 * @return static
	 */
	public function setData($data)
	{
		$column = self::COL_DATA;
		$this->$column = $data;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`add_time`.
	 *
	 * @return string the real type is: bigint
	 */
	public function getAddTime()
	{
		$column = self::COL_ADD_TIME;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_sessions`.`add_time`.
	 *
	 * @param string $add_time
	 *
	 * @return static
	 */
	public function setAddTime($add_time)
	{
		$column = self::COL_ADD_TIME;
		$this->$column = $add_time;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`valid`.
	 *
	 * @return bool the real type is: bool
	 */
	public function getValid()
	{
		$column = self::COL_VALID;
		$v = $this->$column;

		if ($v !== null) {
			$v = (bool)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_sessions`.`valid`.
	 *
	 * @param bool $valid
	 *
	 * @return static
	 */
	public function setValid($valid)
	{
		$column = self::COL_VALID;
		$this->$column = $valid;

		return $this;
	}
}
