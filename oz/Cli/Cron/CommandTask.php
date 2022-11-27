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

namespace OZONE\OZ\Cli\Cron;

use OZONE\OZ\Cli\Cron\Traits\TaskBase;
use OZONE\OZ\Cli\Process;

/**
 * Class CommandTask.
 */
class CommandTask extends TaskBase
{
	private string $command;

	/**
	 * CommandTask constructor.
	 *
	 * @param string $name
	 * @param string $command
	 * @param string $description
	 */
	public function __construct(string $name, string $command, string $description = '')
	{
		parent::__construct($name, $description);

		$this->command = $command;
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): void
	{
		$process = new Process($this->command);

		$process->open();
	}

}
