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

use Gobl\ORM\ORMEntityCRUD;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Db\OZAuthsCrud as OZAuthsCrudReal;

/**
 * Class OZAuthsCrud.
 *
 * @extends \Gobl\ORM\ORMEntityCRUD<\OZONE\Core\Db\OZAuth>
 */
class OZAuthsCrud extends ORMEntityCRUD
{
	/**
	 * OZAuthsCrud constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			OZAuth::TABLE_NAMESPACE,
			OZAuth::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(): static
	{
		return new OZAuthsCrudReal();
	}
}
