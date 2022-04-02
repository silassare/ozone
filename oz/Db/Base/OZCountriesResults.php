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
 * Class OZCountriesResults.
 *
 * @method null|\OZONE\OZ\Db\OZCountry current()
 * @method null|\OZONE\OZ\Db\OZCountry fetchClass(bool $strict = true)
 * @method \OZONE\OZ\Db\OZCountry[]    fetchAllClass(bool $strict = true)
 */
abstract class OZCountriesResults extends \Gobl\ORM\ORMResults
{
	/**
	 * OZCountriesResults constructor.
	 *
	 * @param \Gobl\DBAL\Queries\QBSelect $query
	 */
	public function __construct(\Gobl\DBAL\Queries\QBSelect $query)
	{
		parent::__construct(\OZONE\OZ\Db\OZCountry::TABLE_NAMESPACE, \OZONE\OZ\Db\OZCountry::TABLE_NAME, $query);
	}
}
