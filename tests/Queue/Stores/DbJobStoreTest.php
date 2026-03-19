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

namespace OZONE\Tests\Queue\Stores;

use OZONE\Core\Queue\Interfaces\JobStoreInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Stores\DbJobStore;

/**
 * DB-backed job store contract tests.
 *
 * @internal
 *
 * @coversNothing
 */
final class DbJobStoreTest extends AbstractJobStoreTest
{
	protected function makeStore(): JobStoreInterface
	{
		return JobsManager::getStore(DbJobStore::NAME);
	}
}
