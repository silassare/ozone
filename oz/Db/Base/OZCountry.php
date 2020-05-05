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
use OZONE\OZ\Db\OZCountriesQuery as OZCountriesQueryReal;

/**
 * Class OZCountry
 *
 * @package OZONE\OZ\Db\Base
 */
abstract class OZCountry extends ORMEntityBase
{
	const TABLE_NAME = 'oz_countries';

	
	const COL_CC2 = 'country_cc2';
	const COL_CODE = 'country_code';
	const COL_NAME = 'country_name';
	const COL_NAME_REAL = 'country_name_real';
	const COL_DATA = 'country_data';
	const COL_ADD_TIME = 'country_add_time';
	const COL_VALID = 'country_valid';

	


	/**
	 * OZCountry constructor.
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
			OZCountry::TABLE_NAME,
			OZCountriesQueryReal::class
		);
	}
	

	
	/**
	 * Getter for column `oz_countries`.`cc2`.
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
	 * Setter for column `oz_countries`.`cc2`.
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
	 * Getter for column `oz_countries`.`code`.
	 *
	 * @return string the real type is: string
	 */
	public function getCode()
	{
		$column = self::COL_CODE;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_countries`.`code`.
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
	 * Getter for column `oz_countries`.`name`.
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
	 * Setter for column `oz_countries`.`name`.
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
	 * Getter for column `oz_countries`.`name_real`.
	 *
	 * @return string the real type is: string
	 */
	public function getNameReal()
	{
		$column = self::COL_NAME_REAL;
		$v = $this->$column;

		if ($v !== null) {
			$v = (string)$v;
		}

		return $v;
	}

	/**
	 * Setter for column `oz_countries`.`name_real`.
	 *
	 * @param string $name_real
	 *
	 * @return static
	 */
	public function setNameReal($name_real)
	{
		$column = self::COL_NAME_REAL;
		$this->$column = $name_real;

		return $this;
	}

	/**
	 * Getter for column `oz_countries`.`data`.
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
	 * Setter for column `oz_countries`.`data`.
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
	 * Getter for column `oz_countries`.`add_time`.
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
	 * Setter for column `oz_countries`.`add_time`.
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
	 * Getter for column `oz_countries`.`valid`.
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
	 * Setter for column `oz_countries`.`valid`.
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
