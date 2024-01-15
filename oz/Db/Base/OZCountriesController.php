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

use Gobl\ORM\ORMController;
use OZONE\Core\Db\OZCountriesController as OZCountriesControllerReal;
use OZONE\Core\Db\OZCountry;

/**
 * Class OZCountriesController.
 *
 * @extends \Gobl\ORM\ORMController<\OZONE\Core\Db\OZCountry, \OZONE\Core\Db\OZCountriesQuery, \OZONE\Core\Db\OZCountriesResults>
 */
abstract class OZCountriesController extends ORMController
{
	/**
	 * OZCountriesController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			OZCountry::TABLE_NAMESPACE,
			OZCountry::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(): static
	{
		return new OZCountriesControllerReal();
	}
}
