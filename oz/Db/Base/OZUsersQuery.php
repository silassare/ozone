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

namespace OZONE\OZ\Db\Base;

/**
 * Class OZUsersQuery.
 *
 * @method \OZONE\OZ\Db\OZUsersResults find(int $max = null, int $offset = 0, array $order_by = [])
 */
abstract class OZUsersQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZUsersQuery constructor.
	 */
	public function __construct(\OZONE\OZ\Db\OZUsersFilters $table_scoped_filters)
	{
		parent::__construct(\OZONE\OZ\Db\OZUser::TABLE_NAMESPACE, \OZONE\OZ\Db\OZUser::TABLE_NAME, $table_scoped_filters);
	}
}
