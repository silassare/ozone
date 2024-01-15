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
use OZONE\Core\Db\OZFile;
use OZONE\Core\Db\OZFilesCrud as OZFilesCrudReal;

/**
 * Class OZFilesCrud.
 *
 * @extends \Gobl\ORM\ORMEntityCRUD<\OZONE\Core\Db\OZFile>
 */
class OZFilesCrud extends ORMEntityCRUD
{
	/**
	 * OZFilesCrud constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			OZFile::TABLE_NAMESPACE,
			OZFile::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(): static
	{
		return new OZFilesCrudReal();
	}
}
