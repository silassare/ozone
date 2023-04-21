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

namespace OZONE\OZ\Hooks\Events;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use PHPUtils\Events\Event;

/**
 * Class DbBeforeLockHook.
 *
 * This event is triggered before locking the database.
 * The event may be triggered multiple times but only once per database instance.
 * And is useful when you want to be sure that required tables are added before you add relations.
 */
final class DbBeforeLockHook extends Event
{
	/**
	 * DbBeforeLockHook constructor.
	 *
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $db
	 */
	public function __construct(protected RDBMSInterface $db)
	{
	}

	/**
	 * DbBeforeLockHook destructor.
	 */
	public function __destruct()
	{
		unset($this->db);
	}

	/**
	 * Returns the database.
	 *
	 * @return \Gobl\DBAL\Interfaces\RDBMSInterface
	 */
	public function getDb(): RDBMSInterface
	{
		return $this->db;
	}
}
