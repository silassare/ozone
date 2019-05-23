<?php
	/**
 * Auto generated file, please don't edit.
 *
 * With: Gobl v1.0.0
 * Time: 1557147498
 */

	namespace OZONE\OZ\Db\Base;

	use Gobl\DBAL\Rule;
	use Gobl\ORM\ORM;
	use Gobl\ORM\ORMTableQueryBase;

	/**
	 * Class OZAuthenticatorQuery
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZAuthenticatorQuery extends ORMTableQueryBase
	{
		/**
		 * OZAuthenticatorQuery constructor.
		 */
		public function __construct()
		{
			parent::__construct(ORM::getDatabase('OZONE\OZ\Db'), OZAuth::TABLE_NAME, \OZONE\OZ\Db\OZAuthenticatorResults::class);
		}

		/**
		 * Finds rows in the table `oz_authenticator` and returns a new instance of the table's result iterator.
		 *
		 * @param int|null $max
		 * @param int      $offset
		 * @param array    $order_by
		 *
		 * @return \OZONE\OZ\Db\OZAuthenticatorResults
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function find($max = null, $offset = 0, array $order_by = [])
		{
			/** @var \OZONE\OZ\Db\OZAuthenticatorResults $results */
			$results = parent::find($max, $offset, $order_by);

			return $results;
		}
		
		/**
		 * Filters rows with condition on column `label` in the table `oz_authenticator`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAuthenticatorQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByLabel($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('label', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `for` in the table `oz_authenticator`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAuthenticatorQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByFor($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('for', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `code` in the table `oz_authenticator`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAuthenticatorQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByCode($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('code', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `token` in the table `oz_authenticator`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAuthenticatorQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByToken($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('token', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `try_max` in the table `oz_authenticator`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAuthenticatorQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByTryMax($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('try_max', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `try_count` in the table `oz_authenticator`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAuthenticatorQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByTryCount($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('try_count', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `expire` in the table `oz_authenticator`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAuthenticatorQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByExpire($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('expire', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `data` in the table `oz_authenticator`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAuthenticatorQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByData($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('data', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `add_time` in the table `oz_authenticator`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAuthenticatorQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByAddTime($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('add_time', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `valid` in the table `oz_authenticator`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZAuthenticatorQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByValid($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('valid', $value, $operator);
		}

	}