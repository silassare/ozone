<?php

/**
 * Auto generated file, please don't edit.
 *
 * With: Gobl v1.0.9
 * Time: 1586982104
 */

namespace OZONE\OZ\Db\Base;

use Gobl\DBAL\QueryBuilder;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMControllerBase;
use OZONE\OZ\Db\OZFile as OZFileReal;
use OZONE\OZ\Db\OZFilesResults as OZFilesResultsReal;
use OZONE\OZ\Db\OZFilesQuery as OZFilesQueryReal;

/**
 * Class OZFilesController
 *
 * @package OZONE\OZ\Db\Base
 */
abstract class OZFilesController extends ORMControllerBase
{
	/**
	 * OZFilesController constructor.
	 *
	 * @inheritdoc
	 */
	public function __construct()
	{
		parent::__construct(
			ORM::getDatabase('OZONE\OZ\Db'),
			OZFile::TABLE_NAME,
			OZFileReal::class,
			OZFilesQueryReal::class,
			OZFilesResultsReal::class
		);
	}

	/**
	 * Adds item to `oz_files`.
	 *
	 * @param array $values the row values
	 *
	 * @return \OZONE\OZ\Db\OZFile
	 * @throws \Throwable
	 */
	public function addItem(array $values = [])
	{
		/** @var \OZONE\OZ\Db\OZFile $result */
		$result = parent::addItem($values);

		return $result;
	}

	/**
	 * Updates one item in `oz_files`.
	 *
	 * The returned value will be:
	 * - `false` when the item was not found
	 * - `OZFile` when the item was successfully updated,
	 * when there is an error updating you can catch the exception
	 *
	 * @param array $filters    the row filters
	 * @param array $new_values the new values
	 *
	 * @return bool|\OZONE\OZ\Db\OZFile
	 * @throws \Throwable
	 */
	public function updateOneItem(array $filters, array $new_values)
	{
		return parent::updateOneItem($filters, $new_values);
	}

	/**
	 * Updates all items in `oz_files` that match the given item filters.
	 *
	 * @param array $filters    the row filters
	 * @param array $new_values the new values
	 *
	 * @return int Affected row count.
	 * @throws \Throwable
	 */
	public function updateAllItems(array $filters, array $new_values)
	{
		return parent::updateAllItems($filters, $new_values);
	}

	/**
	 * Deletes one item from `oz_files`.
	 *
	 * The returned value will be:
	 * - `false` when the item was not found
	 * - `OZFile` when the item was successfully deleted,
	 * when there is an error deleting you can catch the exception
	 *
	 * @param array $filters the row filters
	 *
	 * @return bool|\OZONE\OZ\Db\OZFile
	 * @throws \Throwable
	 */
	public function deleteOneItem(array $filters)
	{
		return parent::deleteOneItem($filters);
	}

	/**
	 * Deletes all items in `oz_files` that match the given item filters.
	 *
	 * @param array $filters the row filters
	 *
	 * @return int Affected row count.
	 * @throws \Throwable
	 */
	public function deleteAllItems(array $filters)
	{
		return parent::deleteAllItems($filters);
	}

	/**
	 * Gets item from `oz_files` that match the given filters.
	 *
	 * The returned value will be:
	 * - `null` when the item was not found
	 * - `OZFile` otherwise
	 *
	 * @param array $filters  the row filters
	 * @param array $order_by order by rules
	 *
	 * @return \OZONE\OZ\Db\OZFile|null
	 * @throws \Throwable
	 */
	public function getItem(array $filters, array $order_by = [])
	{
		/** @var \OZONE\OZ\Db\OZFile|null $result */
		$result = parent::getItem($filters, $order_by);

		return $result;
	}

	/**
	 * Gets all items from `oz_files` that match the given filters.
	 *
	 * @param array    $filters  the row filters
	 * @param int|null $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 * @param int|bool $total    total rows without limit
	 *
	 * @return \OZONE\OZ\Db\OZFile[]
	 * @throws \Throwable
	 */
	public function getAllItems(array $filters = [], $max = null, $offset = 0, array $order_by = [], &$total = false)
	{
		/** @var \OZONE\OZ\Db\OZFile[] $results */
		$results = parent::getAllItems($filters, $max, $offset, $order_by, $total);

		return $results;
	}

	/**
	 * Gets all items from `oz_files` with a custom query builder instance.
	 *
	 * @param \Gobl\DBAL\QueryBuilder $qb
	 * @param int|null                $max    maximum row to retrieve
	 * @param int                     $offset first row offset
	 * @param int|bool                $total  total rows without limit
	 *
	 * @return \OZONE\OZ\Db\OZFile[]
	 * @throws \Throwable
	 */
	public function getAllItemsCustom(QueryBuilder $qb, $max = null, $offset = 0, &$total = false)
	{
		/** @var \OZONE\OZ\Db\OZFile[] $results */
		$results = parent::getAllItemsCustom($qb, $max, $offset, $total);

		return $results;
	}
}
