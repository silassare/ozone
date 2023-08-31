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
 * Class OZUsersResults.
 *
 * @extends \Gobl\ORM\ORMResults<\OZONE\Core\Db\OZUser>
 */
abstract class OZUsersResults extends \Gobl\ORM\ORMResults
{
	/**
	 * OZUsersResults constructor.
	 */
	public function __construct(\Gobl\DBAL\Queries\QBSelect $query)
	{
		parent::__construct(
			\OZONE\Core\Db\OZUser::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZUser::TABLE_NAME,
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
		return new \OZONE\Core\Db\OZUsersResults($query);
	}
}
