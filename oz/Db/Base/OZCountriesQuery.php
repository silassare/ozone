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
 * Class OZCountriesQuery.
 *
 * @method \OZONE\OZ\Db\OZCountriesResults find(int $max = null, int $offset = 0, array $order_by = [])
 */
abstract class OZCountriesQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZCountriesQuery constructor.
	 */
	public function __construct(\OZONE\OZ\Db\OZCountriesFilters $table_scoped_filters)
	{
		parent::__construct(\OZONE\OZ\Db\OZCountry::TABLE_NAMESPACE, \OZONE\OZ\Db\OZCountry::TABLE_NAME, $table_scoped_filters);
	}
}
