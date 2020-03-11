<?php
	/**
 * Auto generated file, please don't edit.
 *
 * With: Gobl v1.0.9
 * Time: 1583761352
 */


	namespace OZONE\OZ\Db\Base;

	use Gobl\DBAL\Rule;
	use Gobl\ORM\ORM;
	use Gobl\ORM\ORMTableQueryBase;

	/**
	 * Class OZFilesQuery
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZFilesQuery extends ORMTableQueryBase
	{
		/**
		 * OZFilesQuery constructor.
		 */
		public function __construct()
		{
			parent::__construct(ORM::getDatabase('OZONE\OZ\Db'), OZFile::TABLE_NAME, \OZONE\OZ\Db\OZFilesResults::class);
		}

		/**
		 * Finds rows in the table `oz_files` and returns a new instance of the table's result iterator.
		 *
		 * @param int|null $max
		 * @param int      $offset
		 * @param array    $order_by
		 *
		 * @return \OZONE\OZ\Db\OZFilesResults
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function find($max = null, $offset = 0, array $order_by = [])
		{
			/** @var \OZONE\OZ\Db\OZFilesResults $results */
			$results = parent::find($max, $offset, $order_by);

			return $results;
		}
		
		/**
		 * Filters rows with condition on column `id` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterById($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('id', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `user_id` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByUserId($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('user_id', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `key` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByKey($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('key', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `clone` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByClone($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('clone', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `origin` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByOrigin($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('origin', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `size` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterBySize($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('size', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `type` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByType($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('type', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `name` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByName($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('name', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `label` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByLabel($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('label', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `path` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByPath($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('path', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `thumb` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByThumb($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('thumb', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `data` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByData($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('data', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `add_time` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByAddTime($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('add_time', $value, $operator);
		}

		/**
		 * Filters rows with condition on column `valid` in the table `oz_files`.
		 *
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 *
		 * @return $this|\OZONE\OZ\Db\OZFilesQuery
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function filterByValid($value, $operator = Rule::OP_EQ)
		{
		    return $this->filterBy('valid', $value, $operator);
		}

	}