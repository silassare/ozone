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

use OZONE\Core\Cli\Cmd\CronCmd;
use OZONE\Core\Cli\Cmd\DbCmd;
use OZONE\Core\Cli\Cmd\ProjectCmd;
use OZONE\Core\Cli\Cmd\ScopesCmd;
use OZONE\Core\Cli\Cmd\ServicesCmd;
use OZONE\Core\Migrations\Cli\MigrationsCmd;
use OZONE\Core\Queue\Cli\JobsCmd;
use OZONE\Core\Users\Cli\UsersCmd;

return [
	'project'    => ProjectCmd::class,
	'scopes'     => ScopesCmd::class,
	'db'         => DbCmd::class,
	'migrations' => MigrationsCmd::class,
	'services'   => ServicesCmd::class,
	'users'      => UsersCmd::class,
	'cron'       => CronCmd::class,
	'jobs'       => JobsCmd::class,
];
