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

use Gobl\DBAL\Queries\QBSelect;
use Gobl\ORM\ORMResults;
use OZONE\Core\Db\OZDbStore;
use OZONE\Core\Db\OZDbStoresResults as OZDbStoresResultsReal;

/**
 * Class OZDbStoresResults.
 *
 * @extends \Gobl\ORM\ORMResults<\OZONE\Core\Db\OZDbStore>
 */
abstract class OZDbStoresResults extends ORMResults
{
	/**
	 * OZDbStoresResults constructor.
	 */
	public function __construct(QBSelect $query)
	{
		parent::__construct(
			OZDbStore::TABLE_NAMESPACE,
			OZDbStore::TABLE_NAME,
			$query
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(QBSelect $query): static
	{
		return new OZDbStoresResultsReal($query);
	}
}
