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
 * Class OZSessionsController.
 *
 * @extends \Gobl\ORM\ORMController<\OZONE\Core\Db\OZSession, \OZONE\Core\Db\OZSessionsQuery, \OZONE\Core\Db\OZSessionsResults>
 */
abstract class OZSessionsController extends \Gobl\ORM\ORMController
{
	/**
	 * OZSessionsController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZSession::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZSession::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\Core\Db\OZSessionsController();
	}
}
