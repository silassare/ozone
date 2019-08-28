<?php
	/**
 * Auto generated file, please don't edit.
 *
 * With: Gobl v1.0.0
 * Time: 1566582929
 */

	namespace OZONE\OZ\Db\Base;

	use Gobl\DBAL\QueryBuilder;
	use Gobl\ORM\ORM;
	use Gobl\ORM\ORMControllerBase;
	use OZONE\OZ\Db\OZClient as OZClientReal;
	use OZONE\OZ\Db\OZClientsResults as OZClientsResultsReal;
	use OZONE\OZ\Db\OZClientsQuery as OZClientsQueryReal;

	/**
	 * Class OZClientsController
	 *
	 * @package OZONE\OZ\Db\Base
	 */
	abstract class OZClientsController extends ORMControllerBase
	{
		/**
		 * OZClientsController constructor.
		 *
		 * @inheritdoc
		 */
		public function __construct()
		{
			parent::__construct(ORM::getDatabase('OZONE\OZ\Db'), OZClient::TABLE_NAME, OZClientReal::class, OZClientsQueryReal::class, OZClientsResultsReal::class);
		}

		/**
		 * Adds item to `oz_clients`.
		 *
		 * @param array $values the row values
		 *
		 * @return \OZONE\OZ\Db\OZClient
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function addItem(array $values = [])
		{
			/** @var \OZONE\OZ\Db\OZClient $result */
			$result = parent::addItem($values);

			return $result;
		}

		/**
		 * Updates one item in `oz_clients`.
		 *
		 * The returned value will be:
		 * - `false` when the item was not found
		 * - `OZClient` when the item was successfully updated,
		 * when there is an error updating you can catch the exception
		 *
		 * @param array $filters    the row filters
		 * @param array $new_values the new values
		 *
		 * @return bool|\OZONE\OZ\Db\OZClient
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function updateOneItem(array $filters, array $new_values)
		{
			return parent::updateOneItem($filters, $new_values);
		}

		/**
		 * Updates all items in `oz_clients` that match the given item filters.
		 *
		 * @param array $filters    the row filters
		 * @param array $new_values the new values
		 *
		 * @return int Affected row count.
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function updateAllItems(array $filters, array $new_values)
		{
			return parent::updateAllItems($filters, $new_values);
		}

		/**
		 * Deletes one item from `oz_clients`.
		 *
		 * The returned value will be:
		 * - `false` when the item was not found
		 * - `OZClient` when the item was successfully deleted,
		 * when there is an error deleting you can catch the exception
		 *
		 * @param array $filters the row filters
		 *
		 * @return bool|\OZONE\OZ\Db\OZClient
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function deleteOneItem(array $filters)
		{
			return parent::deleteOneItem($filters);
		}

		/**
		 * Deletes all items in `oz_clients` that match the given item filters.
		 *
		 * @param array $filters the row filters
		 *
		 * @return int Affected row count.
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function deleteAllItems(array $filters)
		{
			return parent::deleteAllItems($filters);
		}

		/**
		 * Gets item from `oz_clients` that match the given filters.
		 *
		 * The returned value will be:
		 * - `null` when the item was not found
		 * - `OZClient` otherwise
		 *
		 * @param array $filters  the row filters
		 * @param array $order_by order by rules
		 *
		 * @return \OZONE\OZ\Db\OZClient|null
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function getItem(array $filters, array $order_by = [])
		{
			/** @var \OZONE\OZ\Db\OZClient|null $result */
			$result = parent::getItem($filters, $order_by);

			return $result;
		}

		/**
		 * Gets all items from `oz_clients` that match the given filters.
		 *
		 * @param array    $filters  the row filters
		 * @param int|null $max      maximum row to retrieve
		 * @param int      $offset   first row offset
		 * @param array    $order_by order by rules
		 * @param int|bool $total    total rows without limit
		 *
		 * @return \OZONE\OZ\Db\OZClient[]
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function getAllItems(array $filters = [], $max = null, $offset = 0, array $order_by = [], &$total = false)
		{
			/** @var \OZONE\OZ\Db\OZClient[] $results */
			$results = parent::getAllItems($filters, $max, $offset, $order_by, $total);

			return $results;
		}

		/**
		 * Gets all items from `oz_clients` with a custom query builder instance.
		 *
		 * @param \Gobl\DBAL\QueryBuilder $qb
		 * @param int|null                $max    maximum row to retrieve
		 * @param int                     $offset first row offset
		 * @param int|bool                $total  total rows without limit
		 *
		 * @return \OZONE\OZ\Db\OZClient[]
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function getAllItemsCustom(QueryBuilder $qb, $max = null, $offset = 0, &$total = false)
		{
			/** @var \OZONE\OZ\Db\OZClient[] $results */
			$results = parent::getAllItemsCustom($qb, $max, $offset, $total);

			return $results;
		}
	}