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

namespace OZONE\Core\Cli\Server\Interfaces;

use OZONE\Core\Cli\Server\ProvisionPlan;

/**
 * Interface ShellRunnerInterface.
 *
 * How a {@see ProvisionPlan} reaches the host.
 *
 * It exists so that building a plan and running it are separate concerns: a plan can be printed,
 * inspected and asserted without a shell, and only running it needs one.
 */
interface ShellRunnerInterface
{
	/**
	 * Runs a command, and returns its exit code.
	 *
	 * @param string $command the shell command
	 * @param string $output  receives the combined output
	 *
	 * @return int the exit code
	 */
	public function run(string $command, string &$output = ''): int;
}
