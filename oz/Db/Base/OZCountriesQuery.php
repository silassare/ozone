<?php
	/**
 * Auto generated file, please don't edit.
 *
 * With: Gobl v1.0.9
 * Time: 1577896178
 */


	namespace OZONE\OZ\Db\Base;

	use Gobl\DBAL\Rule;
	use Gobl\ORM\ORM;
	use Gobl\ORM\ORMTableQueryBase;

	/**
	 * Class OZCountriesQuery
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZCountriesQuery extends ORMTableQueryBase
	{
		/**
		 * OZCountriesQuery constructor.
		 */
		public function __construct()
		{
			parent::__construct(ORM::getDatabase('OZONE\OZ\Db'), OZCountry::TABLE_NAME, \OZONE\OZ\Db\OZCountriesResults::class);
		}

		/**
		 * Finds rows in the table `oz_countries` and returns a new instance of the table's result iterator.
		 *
		 * @param int|null $max
		 * @param int      $offset
		 * @param array    $order_by
		 *
		 * @return \OZONE\OZ\Db\OZCountriesResults
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function find($max = null, $offset = 0, array $order_by = [])
		{
			/** @var \OZONE\OZ\Db\OZCountriesResults $results */
			$results = parent::find($max, $offset, $order_by);

			return $results;
		}
		
		/**
		 * Filters rows with condition on column `cc2` in the table `oz_countries`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZCountriesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByCc2($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('cc2', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `code` in the table `oz_countries`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZCountriesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByCode($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('code', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `name` in the table `oz_countries`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZCountriesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByName($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('name', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `name_real` in the table `oz_countries`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZCountriesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByNameReal($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('name_real', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `data` in the table `oz_countries`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZCountriesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByData($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('data', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `add_time` in the table `oz_countries`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZCountriesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByAddTime($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('add_time', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `valid` in the table `oz_countries`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZCountriesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByValid($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('valid', $value, $operator);
		}

	}