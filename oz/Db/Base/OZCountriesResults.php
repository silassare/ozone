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

/**
 * Class OZCountriesResults.
 *
 * @extends \Gobl\ORM\ORMResults<\OZONE\Core\Db\OZCountry>
 */
abstract class OZCountriesResults extends \Gobl\ORM\ORMResults
{
	/**
	 * OZCountriesResults constructor.
	 */
	public function __construct(\Gobl\DBAL\Queries\QBSelect $query)
	{
		parent::__construct(
			\OZONE\Core\Db\OZCountry::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZCountry::TABLE_NAME,
			$query
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(\Gobl\DBAL\Queries\QBSelect $query): static
	{
		return new \OZONE\Core\Db\OZCountriesResults($query);
	}
}
