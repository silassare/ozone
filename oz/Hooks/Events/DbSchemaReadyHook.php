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
 * Class DbSchemaReadyHook.
 *
 * This event is triggered when the database schema is ready.
 * And is useful when you want to be sure that all tables are loaded.
 */
final class DbSchemaReadyHook extends Event
{
	/**
	 * DbSchemaReadyHook constructor.
	 *
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $db
	 */
	public function __construct(protected RDBMSInterface $db)
	{
	}

	/**
	 * DbSchemaReadyHook destructor.
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
