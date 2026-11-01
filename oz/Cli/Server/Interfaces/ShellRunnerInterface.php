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
use OZONE\Core\Cli\Server\ProvisionStep;

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
	 * The exit code of a command that was killed for taking longer than it was given, as
	 * `timeout(1)` reports one.
	 *
	 * A runner answers it instead of raising: a caller reads one failure the same way whether the
	 * command failed or was never allowed to finish, and {@see ProvisionPlan} reports both as the
	 * step they belong to.
	 */
	public const int TIMED_OUT = 124;

	/**
	 * Runs a command, and returns its exit code.
	 *
	 * A command that takes longer than this runner allows is killed and reported as
	 * {@see self::TIMED_OUT}, with whatever it printed until then.
	 *
	 * @param string $command the shell command
	 * @param string $output  receives the combined output
	 *
	 * @return int the exit code
	 */
	public function run(string $command, string &$output = ''): int;

	/**
	 * The same runner, allowing one command the seconds given; 0 for no limit.
	 *
	 * The runner is left as it is: a step that needs longer, or must not be cut short, runs on the
	 * answer and nothing else changes bound ({@see ProvisionStep::$timeout}).
	 */
	public function withTimeout(float $timeout): static;
}
