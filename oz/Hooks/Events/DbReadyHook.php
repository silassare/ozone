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

namespace OZONE\Core\Hooks\Events;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use PHPUtils\Events\Event;

/**
 * Class DbReadyHook.
 *
 * This event is triggered when the database is loaded and ready to be used.
 */
final class DbReadyHook extends Event
{
	/**
	 * DbReadyHook constructor.
	 *
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $db
	 */
	public function __construct(protected RDBMSInterface $db)
	{
	}

	/**
	 * DbReadyHook destructor.
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
