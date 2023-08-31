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

namespace OZONE\Core\Cli\Cron\Tasks;

use OZONE\Core\Cli\Process;

/**
 * Class CommandTask.
 */
class CommandTask extends AbstractTask
{
	/**
	 * CommandTask constructor.
	 *
	 * @param array|string $command
	 * @param string       $name
	 * @param string       $description
	 */
	public function __construct(
		protected array|string $command,
		string $name,
		string $description = ''
	) {
		parent::__construct($name, $description);
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): void
	{
		if (\is_string($this->command)) {
			$process = Process::fromShellCommandline($this->command);
		} else {
			$process = new Process($this->command);
		}

		if ($this->shouldRunInBackground()) {
			$process->start();
		} else {
			$process->run();
		}
	}
}
