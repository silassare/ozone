<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace OZONE\Core\Db\Base;

use Generator;

/**
 * Class OZJobsResults.
 *
 * @method null|\OZONE\Core\Db\OZJob       current()
 * @method null|\OZONE\Core\Db\OZJob       fetchClass(bool $strict = true)
 * @method \OZONE\Core\Db\OZJob[]          fetchAllClass(bool $strict = true)
 * @method Generator<\OZONE\Core\Db\OZJob> lazy(bool $strict = true, int $max = 100)
 * @method null|\OZONE\Core\Db\OZJob       updateOneItem(array $filters, array $new_values)
 */
abstract class OZJobsResults extends \Gobl\ORM\ORMResults
{
	/**
	 * OZJobsResults constructor.
	 */
	public function __construct(\Gobl\DBAL\Queries\QBSelect $query)
	{
		parent::__construct(
			\OZONE\Core\Db\OZJob::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZJob::TABLE_NAME,
			$query
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(\Gobl\DBAL\Queries\QBSelect $query): static
	{
		return new \OZONE\Core\Db\OZJobsResults($query);
	}
}
