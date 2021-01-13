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
use OZONE\OZ\Db\OZUser as OZUserReal;
use OZONE\OZ\Db\OZUsersResults as OZUsersResultsReal;
use OZONE\OZ\Db\OZUsersQuery as OZUsersQueryReal;

/**
 * Class OZUsersController
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
		parent::__construct(
			ORM::getDatabase('OZONE\OZ\Db'),
			OZUser::TABLE_NAME,
			OZUserReal::class,
			OZUsersQueryReal::class,
			OZUsersResultsReal::class
		);
	}

	/**
	 * Adds item to `oz_users`.
	 *
	 * @param array $values the row values
	 *
	 * @throws \Throwable
	 *
	 * @return \OZONE\OZ\Db\OZUser
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
	 * @throws \Throwable
	 *
	 * @return bool|\OZONE\OZ\Db\OZUser
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
	 * @throws \Throwable
	 *
	 * @return int affected row count
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
	 * @throws \Throwable
	 *
	 * @return bool|\OZONE\OZ\Db\OZUser
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
	 * @throws \Throwable
	 *
	 * @return int affected row count
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
	 * @throws \Throwable
	 *
	 * @return null|\OZONE\OZ\Db\OZUser
	 */
	public function getItem(array $filters, array $order_by = [])
	{
		/* @var null|\OZONE\OZ\Db\OZUser $result */
		$result = parent::getItem($filters, $order_by);

		return $result;
	}

	/**
	 * Gets all items from `oz_users` that match the given filters.
	 *
	 * @param array    $filters  the row filters
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 * @param bool|int $total    total rows without limit
	 *
	 * @throws \Throwable
	 *
	 * @return \OZONE\OZ\Db\OZUser[]
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
	 * @param null|int                $max    maximum row to retrieve
	 * @param int                     $offset first row offset
	 * @param bool|int                $total  total rows without limit
	 *
	 * @throws \Throwable
	 *
	 * @return \OZONE\OZ\Db\OZUser[]
	 */
	public function getAllItemsCustom(QueryBuilder $qb, $max = null, $offset = 0, &$total = false)
	{
		/** @var \OZONE\OZ\Db\OZUser[] $results */
		$results = parent::getAllItemsCustom($qb, $max, $offset, $total);

		return $results;
	}
}
