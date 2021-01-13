<?php

/**
 * Auto generated file
 *
 * WARNING: please don't edit.
 *
 * Proudly With: gobl v1.5.0
 * Time: 1617030519
 */

namespace OZONE\OZ\Db\Base;

use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntityBase;
use OZONE\OZ\Db\OZUsersQuery as OZUsersQueryReal;
use OZONE\OZ\Db\OZFilesController as OZFilesControllerRealR;
use OZONE\OZ\Db\OZCountriesController as OZCountriesControllerRealR;

/**
 * Class OZUser
 */
abstract class OZUser extends ORMEntityBase
{
	const TABLE_NAME = 'oz_users';

	const COL_ID = 'user_id';
	const COL_PHONE = 'user_phone';
	const COL_EMAIL = 'user_email';
	const COL_PASS = 'user_pass';
	const COL_NAME = 'user_name';
	const COL_GENDER = 'user_gender';
	const COL_BIRTH_DATE = 'user_birth_date';
	const COL_PICID = 'user_picid';
	const COL_CC2 = 'user_cc2';
	const COL_DATA = 'user_data';
	const COL_ADD_TIME = 'user_add_time';
	const COL_VALID = 'user_valid';

	/**
	 * @var \OZONE\OZ\Db\OZCountry
	 */
	protected $_r_oz_country;


	/**
	 * OZUser constructor.
	 *
	 * @param bool $is_new true for new entity false for entity fetched
	 *                     from the database, default is true
	 * @param bool $strict Enable/disable strict mode
	 */
	public function __construct($is_new = true, $strict = true)
	{
		parent::__construct(
			ORM::getDatabase('OZONE\OZ\Db'),
			$is_new,
			$strict,
			self::TABLE_NAME,
			OZUsersQueryReal::class
		);
	}

	/**
	 * OneToMany relation between `oz_users` and `oz_files`.
	 *
	 * @param array	$filters  the row filters
	 * @param int|null $max	  maximum row to retrieve
	 * @param int	  $offset   first row offset
	 * @param array	$order_by order by rules
	 * @param int|bool $total	total rows without limit
	 *
	 * @return \OZONE\OZ\Db\OZFile[]
	 * @throws \Throwable
	 */
	function getOZFiles($filters = [], $max = null, $offset = 0, $order_by = [], &$total = false)
	{
		if (!is_null($v = $this->getId())) {
			$filters['file_user_id'] = $v;
		}
		if (empty($filters)) {
			return [];
		}

		$ctrl = new OZFilesControllerRealR();

		return $ctrl->getAllItems($filters, $max, $offset, $order_by, $total);
	}

	/**
	 * OneToOne relation between `oz_users` and `oz_countries`.
	 *
	 * @return null|\OZONE\OZ\Db\OZCountry
	 * @throws \Throwable
	 */
	public function getOZCountry()
	{
		if (!isset($this->_r_oz_country)) {
			$filters = [];
			if (!is_null($v = $this->getCc2())) {
				$filters['country_cc2'] = $v;
			}
			if (empty($filters)) {
				return null;
			}

			$m = new OZCountriesControllerRealR();
			$this->_r_oz_country = $m->getItem($filters);
		}

		return $this->_r_oz_country;
	}

	/**
	 * Getter for column `oz_users`.`id`.
	 *
	 * @return string the real type is: bigint
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
	 * Setter for column `oz_users`.`id`.
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
	 * Getter for column `oz_users`.`phone`.
	 *
	 * @return string the real type is: string
	 */
	public function getPhone()
	{
		$column = self::COL_PHONE;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_users`.`phone`.
	 *
	 * @param string $phone
	 *
	 * @return static
	 */
	public function setPhone($phone)
	{
		$column = self::COL_PHONE;
		$this->$column = $phone;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`email`.
	 *
	 * @return string the real type is: string
	 */
	public function getEmail()
	{
		$column = self::COL_EMAIL;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_users`.`email`.
	 *
	 * @param string $email
	 *
	 * @return static
	 */
	public function setEmail($email)
	{
		$column = self::COL_EMAIL;
		$this->$column = $email;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`pass`.
	 *
	 * @return string the real type is: string
	 */
	public function getPass()
	{
		$column = self::COL_PASS;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_users`.`pass`.
	 *
	 * @param string $pass
	 *
	 * @return static
	 */
	public function setPass($pass)
	{
		$column = self::COL_PASS;
		$this->$column = $pass;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`name`.
	 *
	 * @return string the real type is: string
	 */
	public function getName()
	{
		$column = self::COL_NAME;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_users`.`name`.
	 *
	 * @param string $name
	 *
	 * @return static
	 */
	public function setName($name)
	{
		$column = self::COL_NAME;
		$this->$column = $name;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`gender`.
	 *
	 * @return string the real type is: string
	 */
	public function getGender()
	{
		$column = self::COL_GENDER;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_users`.`gender`.
	 *
	 * @param string $gender
	 *
	 * @return static
	 */
	public function setGender($gender)
	{
		$column = self::COL_GENDER;
		$this->$column = $gender;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`birth_date`.
	 *
	 * @return string the real type is: string
	 */
	public function getBirthDate()
	{
		$column = self::COL_BIRTH_DATE;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_users`.`birth_date`.
	 *
	 * @param string $birth_date
	 *
	 * @return static
	 */
	public function setBirthDate($birth_date)
	{
		$column = self::COL_BIRTH_DATE;
		$this->$column = $birth_date;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`picid`.
	 *
	 * @return string the real type is: string
	 */
	public function getPicid()
	{
		$column = self::COL_PICID;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_users`.`picid`.
	 *
	 * @param string $picid
	 *
	 * @return static
	 */
	public function setPicid($picid)
	{
		$column = self::COL_PICID;
		$this->$column = $picid;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`cc2`.
	 *
	 * @return string the real type is: string
	 */
	public function getCc2()
	{
		$column = self::COL_CC2;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_users`.`cc2`.
	 *
	 * @param string $cc2
	 *
	 * @return static
	 */
	public function setCc2($cc2)
	{
		$column = self::COL_CC2;
		$this->$column = $cc2;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`data`.
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
	 * Setter for column `oz_users`.`data`.
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
	 * Getter for column `oz_users`.`add_time`.
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
	 * Setter for column `oz_users`.`add_time`.
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
	 * Getter for column `oz_users`.`valid`.
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
	 * Setter for column `oz_users`.`valid`.
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
