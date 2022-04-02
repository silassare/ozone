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
 * Class OZSessionsQuery.
 *
 * @method \OZONE\OZ\Db\OZSessionsResults find(int $max = null, int $offset = 0, array $order_by = [])
 */
abstract class OZSessionsQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZSessionsQuery constructor.
	 */
	public function __construct(\OZONE\OZ\Db\OZSessionsFilters $table_scoped_filters)
	{
		parent::__construct(\OZONE\OZ\Db\OZSession::TABLE_NAMESPACE, \OZONE\OZ\Db\OZSession::TABLE_NAME, $table_scoped_filters);
	}
}
