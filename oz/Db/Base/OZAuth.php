<?php
	/**
 * Auto generated file, please don't edit.
 *
 * With: Gobl v1.0.9
 * Time: 1577896178
 */


	namespace OZONE\OZ\Db\Base;

	use Gobl\ORM\ORM;
	use Gobl\ORM\ORMEntityBase;
	use OZONE\OZ\Db\OZAuthenticatorQuery as OZAuthenticatorQueryReal;

	

	/**
	 * Class OZAuth
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZAuth extends ORMEntityBase
	{
		const TABLE_NAME = 'oz_authenticator';

				const COL_LABEL = 'auth_label';
		const COL_FOR = 'auth_for';
		const COL_CODE = 'auth_code';
		const COL_TOKEN = 'auth_token';
		const COL_TRY_MAX = 'auth_try_max';
		const COL_TRY_COUNT = 'auth_try_count';
		const COL_EXPIRE = 'auth_expire';
		const COL_DATA = 'auth_data';
		const COL_ADD_TIME = 'auth_add_time';
		const COL_VALID = 'auth_valid';

		

		/**
		 * OZAuth constructor.
		 *
		 * @param bool $is_new True for new entity false for entity fetched
		 *                     from the database, default is true.
		 * @param bool $strict Enable/disable strict mode
		 */
		public function __construct($is_new = true, $strict = true)
		{
			parent::__construct(ORM::getDatabase('OZONE\OZ\Db'), $is_new, $strict, OZAuth::TABLE_NAME, OZAuthenticatorQueryReal::class);
		}
		
		
		/**
		 * Getter for column `oz_authenticator`.`label`.
		 *
		 * @return string the real type is: string
		 */
		public function getLabel()
		{
			$column = self::COL_LABEL;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`label`.
		 *
		 * @param string $label
		 *
		 * @return static
		 */
		public function setLabel($label)
		{
			$column = self::COL_LABEL;
			$this->$column = $label;

			return $this;
		}

		/**
		 * Getter for column `oz_authenticator`.`for`.
		 *
		 * @return string the real type is: string
		 */
		public function getFor()
		{
			$column = self::COL_FOR;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`for`.
		 *
		 * @param string $for
		 *
		 * @return static
		 */
		public function setFor($for)
		{
			$column = self::COL_FOR;
			$this->$column = $for;

			return $this;
		}

		/**
		 * Getter for column `oz_authenticator`.`code`.
		 *
		 * @return string the real type is: string
		 */
		public function getCode()
		{
			$column = self::COL_CODE;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`code`.
		 *
		 * @param string $code
		 *
		 * @return static
		 */
		public function setCode($code)
		{
			$column = self::COL_CODE;
			$this->$column = $code;

			return $this;
		}

		/**
		 * Getter for column `oz_authenticator`.`token`.
		 *
		 * @return string the real type is: string
		 */
		public function getToken()
		{
			$column = self::COL_TOKEN;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`token`.
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
		 * Getter for column `oz_authenticator`.`try_max`.
		 *
		 * @return int the real type is: int
		 */
		public function getTryMax()
		{
			$column = self::COL_TRY_MAX;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (int)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`try_max`.
		 *
		 * @param int $try_max
		 *
		 * @return static
		 */
		public function setTryMax($try_max)
		{
			$column = self::COL_TRY_MAX;
			$this->$column = $try_max;

			return $this;
		}

		/**
		 * Getter for column `oz_authenticator`.`try_count`.
		 *
		 * @return int the real type is: int
		 */
		public function getTryCount()
		{
			$column = self::COL_TRY_COUNT;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (int)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`try_count`.
		 *
		 * @param int $try_count
		 *
		 * @return static
		 */
		public function setTryCount($try_count)
		{
			$column = self::COL_TRY_COUNT;
			$this->$column = $try_count;

			return $this;
		}

		/**
		 * Getter for column `oz_authenticator`.`expire`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getExpire()
		{
			$column = self::COL_EXPIRE;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`expire`.
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
		 * Getter for column `oz_authenticator`.`data`.
		 *
		 * @return string the real type is: string
		 */
		public function getData()
		{
			$column = self::COL_DATA;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`data`.
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
		 * Getter for column `oz_authenticator`.`add_time`.
		 *
		 * @return string the real type is: bigint
		 */
		public function getAddTime()
		{
			$column = self::COL_ADD_TIME;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (string)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`add_time`.
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
		 * Getter for column `oz_authenticator`.`valid`.
		 *
		 * @return bool the real type is: bool
		 */
		public function getValid()
		{
			$column = self::COL_VALID;
		    $v = $this->$column;

		    if( $v !== null){
		        $v = (bool)$v;
		    }

			return $v;
		}

		/**
		 * Setter for column `oz_authenticator`.`valid`.
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