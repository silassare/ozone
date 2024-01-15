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
use OZONE\Core\Db\OZJob;
use OZONE\Core\Db\OZJobsController as OZJobsControllerReal;

/**
 * Class OZJobsController.
 *
 * @extends \Gobl\ORM\ORMController<\OZONE\Core\Db\OZJob, \OZONE\Core\Db\OZJobsQuery, \OZONE\Core\Db\OZJobsResults>
 */
abstract class OZJobsController extends ORMController
{
	/**
	 * OZJobsController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			OZJob::TABLE_NAMESPACE,
			OZJob::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(): static
	{
		return new OZJobsControllerReal();
	}
}
