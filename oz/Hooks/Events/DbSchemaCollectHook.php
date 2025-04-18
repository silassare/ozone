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
 * Class DbSchemaCollectHook.
 *
 * This event is triggered to collect db schema to a given database.
 * The event may be triggered multiple times but only once per database instance.
 * And all the hook listeners should add their tables to the provided db instance.
 * And is useful when you want to add tables to a database from a module/plugin.
 */
final class DbSchemaCollectHook extends Event
{
	/**
	 * DbCollectHook constructor.
	 *
	 * @param RDBMSInterface $db the database instance
	 */
	public function __construct(public readonly RDBMSInterface $db) {}
}
