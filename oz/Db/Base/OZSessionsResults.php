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

use Gobl\DBAL\Db;
use Gobl\DBAL\QueryBuilder;
use Gobl\ORM\ORMResultsBase;

/**
 * Class OZSessionsResults
 */
abstract class OZSessionsResults extends ORMResultsBase
{
	/**
	 * OZSessionsResults constructor.
	 *
	 * @param \Gobl\DBAL\Db           $db
	 * @param \Gobl\DBAL\QueryBuilder $query
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 */
	public function __construct(Db $db, QueryBuilder $query)
	{
		parent::__construct($db, $query, \OZONE\OZ\Db\OZSession::class);
	}

	/**
	 * This is to help editor infer type in loop (foreach or for...)
	 *
	 * @return null|array|\OZONE\OZ\Db\OZSession
	 */
	public function current()
	{
		return parent::current();
	}

	/**
	 * Fetches  the next row into table of the entity class instance.
	 *
	 * @param bool $strict
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return null|\OZONE\OZ\Db\OZSession
	 */
	public function fetchClass($strict = true)
	{
		return parent::fetchClass($strict);
	}

	/**
	 * Fetches  all rows and return array of the entity class instance.
	 *
	 * @param bool $strict
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return \OZONE\OZ\Db\OZSession[]
	 */
	public function fetchAllClass($strict = true)
	{
		return parent::fetchAllClass($strict);
	}
}
