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
 * Class OZClientsResults.
 *
 * @method null|\OZONE\OZ\Db\OZClient current()
 * @method null|\OZONE\OZ\Db\OZClient fetchClass(bool $strict = true)
 * @method \OZONE\OZ\Db\OZClient[]    fetchAllClass(bool $strict = true)
 */
abstract class OZClientsResults extends \Gobl\ORM\ORMResults
{
	/**
	 * OZClientsResults constructor.
	 *
	 * @param \Gobl\DBAL\Queries\QBSelect $query
	 */
	public function __construct(\Gobl\DBAL\Queries\QBSelect $query)
	{
		parent::__construct(\OZONE\OZ\Db\OZClient::TABLE_NAMESPACE, \OZONE\OZ\Db\OZClient::TABLE_NAME, $query);
	}
}
