<?php
	/**
 * Auto generated file, please don't edit.
 *
 * With: Gobl v1.0.9
 * Time: 1577896177
 */


	namespace OZONE\OZ\Db\Base;

	use Gobl\DBAL\Rule;
	use Gobl\ORM\ORM;
	use Gobl\ORM\ORMTableQueryBase;

	/**
	 * Class OZUsersQuery
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZUsersQuery extends ORMTableQueryBase
	{
		/**
		 * OZUsersQuery constructor.
		 */
		public function __construct()
		{
			parent::__construct(ORM::getDatabase('OZONE\OZ\Db'), OZUser::TABLE_NAME, \OZONE\OZ\Db\OZUsersResults::class);
		}

		/**
		 * Finds rows in the table `oz_users` and returns a new instance of the table's result iterator.
		 *
		 * @param int|null $max
		 * @param int      $offset
		 * @param array    $order_by
		 *
		 * @return \OZONE\OZ\Db\OZUsersResults
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function find($max = null, $offset = 0, array $order_by = [])
		{
			/** @var \OZONE\OZ\Db\OZUsersResults $results */
			$results = parent::find($max, $offset, $order_by);

			return $results;
		}
		
		/**
		 * Filters rows with condition on column `id` in the table `oz_users`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZUsersQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterById($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('id', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `phone` in the table `oz_users`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZUsersQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByPhone($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('phone', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `email` in the table `oz_users`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZUsersQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByEmail($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('email', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `pass` in the table `oz_users`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZUsersQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByPass($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('pass', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `name` in the table `oz_users`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZUsersQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByName($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('name', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `gender` in the table `oz_users`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZUsersQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByGender($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('gender', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `birth_date` in the table `oz_users`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZUsersQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByBirthDate($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('birth_date', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `picid` in the table `oz_users`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZUsersQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByPicid($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('picid', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `cc2` in the table `oz_users`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZUsersQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByCc2($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('cc2', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `data` in the table `oz_users`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZUsersQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByData($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('data', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `add_time` in the table `oz_users`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZUsersQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByAddTime($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('add_time', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `valid` in the table `oz_users`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZUsersQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByValid($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('valid', $value, $operator);
		}

	}