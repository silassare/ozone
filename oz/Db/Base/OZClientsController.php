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

use Gobl\DBAL\QueryBuilder;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMControllerBase;
use OZONE\OZ\Db\OZClient as OZClientReal;
use OZONE\OZ\Db\OZClientsResults as OZClientsResultsReal;
use OZONE\OZ\Db\OZClientsQuery as OZClientsQueryReal;

/**
 * Class OZClientsController
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
		parent::__construct(
			ORM::getDatabase('OZONE\OZ\Db'),
			OZClient::TABLE_NAME,
			OZClientReal::class,
			OZClientsQueryReal::class,
			OZClientsResultsReal::class
		);
	}

	/**
	 * Adds item to `oz_clients`.
	 *
	 * @param array $values the row values
	 *
	 * @throws \Throwable
	 *
	 * @return \OZONE\OZ\Db\OZClient
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
	 * @throws \Throwable
	 *
	 * @return bool|\OZONE\OZ\Db\OZClient
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
	 * @throws \Throwable
	 *
	 * @return int affected row count
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
	 * @throws \Throwable
	 *
	 * @return bool|\OZONE\OZ\Db\OZClient
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
	 * @throws \Throwable
	 *
	 * @return int affected row count
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
	 * @throws \Throwable
	 *
	 * @return null|\OZONE\OZ\Db\OZClient
	 */
	public function getItem(array $filters, array $order_by = [])
	{
		/* @var null|\OZONE\OZ\Db\OZClient $result */
		$result = parent::getItem($filters, $order_by);

		return $result;
	}

	/**
	 * Gets all items from `oz_clients` that match the given filters.
	 *
	 * @param array    $filters  the row filters
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 * @param bool|int $total    total rows without limit
	 *
	 * @throws \Throwable
	 *
	 * @return \OZONE\OZ\Db\OZClient[]
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
	 * @param null|int                $max    maximum row to retrieve
	 * @param int                     $offset first row offset
	 * @param bool|int                $total  total rows without limit
	 *
	 * @throws \Throwable
	 *
	 * @return \OZONE\OZ\Db\OZClient[]
	 */
	public function getAllItemsCustom(QueryBuilder $qb, $max = null, $offset = 0, &$total = false)
	{
		/** @var \OZONE\OZ\Db\OZClient[] $results */
		$results = parent::getAllItemsCustom($qb, $max, $offset, $total);

		return $results;
	}
}
