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
 * Class OZAuthsController.
 *
 * @extends \Gobl\ORM\ORMController<\OZONE\Core\Db\OZAuth, \OZONE\Core\Db\OZAuthsQuery, \OZONE\Core\Db\OZAuthsResults>
 */
abstract class OZAuthsController extends \Gobl\ORM\ORMController
{
	/**
	 * OZAuthsController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZAuth::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZAuth::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\Core\Db\OZAuthsController();
	}
}
