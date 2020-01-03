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
	 * Class OZAdministratorsQuery
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZAdministratorsQuery extends ORMTableQueryBase
	{
		/**
		 * OZAdministratorsQuery constructor.
		 */
		public function __construct()
		{
			parent::__construct(ORM::getDatabase('OZONE\OZ\Db'), OZAdmin::TABLE_NAME, \OZONE\OZ\Db\OZAdministratorsResults::class);
		}

		/**
		 * Finds rows in the table `oz_administrators` and returns a new instance of the table's result iterator.
		 *
		 * @param int|null $max
		 * @param int      $offset
		 * @param array    $order_by
		 *
		 * @return \OZONE\OZ\Db\OZAdministratorsResults
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function find($max = null, $offset = 0, array $order_by = [])
		{
			/** @var \OZONE\OZ\Db\OZAdministratorsResults $results */
			$results = parent::find($max, $offset, $order_by);

			return $results;
		}
		
		/**
		 * Filters rows with condition on column `user_id` in the table `oz_administrators`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAdministratorsQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByUserId($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('user_id', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `level` in the table `oz_administrators`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAdministratorsQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByLevel($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('level', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `data` in the table `oz_administrators`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAdministratorsQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByData($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('data', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `add_time` in the table `oz_administrators`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAdministratorsQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByAddTime($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('add_time', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `valid` in the table `oz_administrators`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAdministratorsQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByValid($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('valid', $value, $operator);
		}

	}