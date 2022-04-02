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
 * Class OZUsersResults.
 *
 * @method null|\OZONE\OZ\Db\OZUser current()
 * @method null|\OZONE\OZ\Db\OZUser fetchClass(bool $strict = true)
 * @method \OZONE\OZ\Db\OZUser[]    fetchAllClass(bool $strict = true)
 */
abstract class OZUsersResults extends \Gobl\ORM\ORMResults
{
	/**
	 * OZUsersResults constructor.
	 *
	 * @param \Gobl\DBAL\Queries\QBSelect $query
	 */
	public function __construct(\Gobl\DBAL\Queries\QBSelect $query)
	{
		parent::__construct(\OZONE\OZ\Db\OZUser::TABLE_NAMESPACE, \OZONE\OZ\Db\OZUser::TABLE_NAME, $query);
	}
}
