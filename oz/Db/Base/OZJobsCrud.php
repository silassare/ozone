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
 * Class OZJobsCrud.
 *
 * @extends \Gobl\ORM\ORMEntityCRUD<\OZONE\Core\Db\OZJob>
 */
class OZJobsCrud extends \Gobl\ORM\ORMEntityCRUD
{
	/**
	 * OZJobsCrud constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZJob::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZJob::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(): static
	{
		return new \OZONE\Core\Db\OZJobsCrud();
	}
}
