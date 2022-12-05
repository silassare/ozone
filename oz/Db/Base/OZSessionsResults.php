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
 * Class OZSessionsResults.
 *
 * @method null|\OZONE\OZ\Db\OZSession current()
 * @method null|\OZONE\OZ\Db\OZSession fetchClass(bool $strict = true)
 * @method \OZONE\OZ\Db\OZSession[]    fetchAllClass(bool $strict = true)
 * @method null|\OZONE\OZ\Db\OZSession updateOneItem(array $filters, array $new_values)
 */
abstract class OZSessionsResults extends \Gobl\ORM\ORMResults
{
	/**
	 * OZSessionsResults constructor.
	 */
	public function __construct(\Gobl\DBAL\Queries\QBSelect $query)
	{
		parent::__construct(
			\OZONE\OZ\Db\OZSession::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZSession::TABLE_NAME,
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
		return new \OZONE\OZ\Db\OZSessionsResults($query);
	}
}
