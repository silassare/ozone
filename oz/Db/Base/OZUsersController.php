<?php
	/**
 * Auto generated file, please don't edit.
 *
 * With: Gobl v1.0.9
 * Time: 1583761352
 */


	namespace OZONE\OZ\Db\Base;

	use Gobl\DBAL\QueryBuilder;
	use Gobl\ORM\ORM;
	use Gobl\ORM\ORMControllerBase;
	use OZONE\OZ\Db\OZUser as OZUserReal;
	use OZONE\OZ\Db\OZUsersResults as OZUsersResultsReal;
	use OZONE\OZ\Db\OZUsersQuery as OZUsersQueryReal;

	/**
	 * Class OZUsersController
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZUsersController extends ORMControllerBase
	{
		/**
		 * OZUsersController constructor.
		 *
		 * @inheritdoc
		 */
		public function __construct()
		{
			parent::__construct(ORM::getDatabase('OZONE\OZ\Db'), OZUser::TABLE_NAME, OZUserReal::class, OZUsersQueryReal::class, OZUsersResultsReal::class);
		}

		/**
		 * Adds item to `oz_users`.
		 *
		 * @param array $values the row values
		 *
		 * @return \OZONE\OZ\Db\OZUser
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function addItem(array $values = [])
		{
			/** @var \OZONE\OZ\Db\OZUser $result */
			$result = parent::addItem($values);

			return $result;
		}

		/**
		 * Updates one item in `oz_users`.
		 *
		 * The returned value will be:
		 * - `false` when the item was not found
		 * - `OZUser` when the item was successfully updated,
		 * when there is an error updating you can catch the exception
		 *
		 * @param array $filters    the row filters
		 * @param array $new_values the new values
		 *
		 * @return bool|\OZONE\OZ\Db\OZUser
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function updateOneItem(array $filters, array $new_values)
		{
			return parent::updateOneItem($filters, $new_values);
		}

		/**
		 * Updates all items in `oz_users` that match the given item filters.
		 *
		 * @param array $filters    the row filters
		 * @param array $new_values the new values
		 *
		 * @return int Affected row count.
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function updateAllItems(array $filters, array $new_values)
		{
			return parent::updateAllItems($filters, $new_values);
		}

		/**
		 * Deletes one item from `oz_users`.
		 *
		 * The returned value will be:
		 * - `false` when the item was not found
		 * - `OZUser` when the item was successfully deleted,
		 * when there is an error deleting you can catch the exception
		 *
		 * @param array $filters the row filters
		 *
		 * @return bool|\OZONE\OZ\Db\OZUser
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function deleteOneItem(array $filters)
		{
			return parent::deleteOneItem($filters);
		}

		/**
		 * Deletes all items in `oz_users` that match the given item filters.
		 *
		 * @param array $filters the row filters
		 *
		 * @return int Affected row count.
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function deleteAllItems(array $filters)
		{
			return parent::deleteAllItems($filters);
		}

		/**
		 * Gets item from `oz_users` that match the given filters.
		 *
		 * The returned value will be:
		 * - `null` when the item was not found
		 * - `OZUser` otherwise
		 *
		 * @param array $filters  the row filters
		 * @param array $order_by order by rules
		 *
		 * @return \OZONE\OZ\Db\OZUser|null
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function getItem(array $filters, array $order_by = [])
		{
			/** @var \OZONE\OZ\Db\OZUser|null $result */
			$result = parent::getItem($filters, $order_by);

			return $result;
		}

		/**
		 * Gets all items from `oz_users` that match the given filters.
		 *
		 * @param array    $filters  the row filters
		 * @param int|null $max      maximum row to retrieve
		 * @param int      $offset   first row offset
		 * @param array    $order_by order by rules
		 * @param int|bool $total    total rows without limit
		 *
		 * @return \OZONE\OZ\Db\OZUser[]
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function getAllItems(array $filters = [], $max = null, $offset = 0, array $order_by = [], &$total = false)
		{
			/** @var \OZONE\OZ\Db\OZUser[] $results */
			$results = parent::getAllItems($filters, $max, $offset, $order_by, $total);

			return $results;
		}

		/**
		 * Gets all items from `oz_users` with a custom query builder instance.
		 *
		 * @param \Gobl\DBAL\QueryBuilder $qb
		 * @param int|null                $max    maximum row to retrieve
		 * @param int                     $offset first row offset
		 * @param int|bool                $total  total rows without limit
		 *
		 * @return \OZONE\OZ\Db\OZUser[]
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function getAllItemsCustom(QueryBuilder $qb, $max = null, $offset = 0, &$total = false)
		{
			/** @var \OZONE\OZ\Db\OZUser[] $results */
			$results = parent::getAllItemsCustom($qb, $max, $offset, $total);

			return $results;
		}
	}