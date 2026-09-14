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

namespace OZONE\Core\Cli\Server;

use Override;
use OZONE\Core\Cli\Server\Interfaces\ShellRunnerInterface;
use Symfony\Component\Process\Process;

/**
 * Class ShellRunner.
 *
 * Runs provisioning commands on this host, through a shell so that the commands can carry pipes and
 * environment prefixes as written.
 */
final class ShellRunner implements ShellRunnerInterface
{
	/**
	 * @param float $timeout seconds a single command may take; package installs are slow
	 */
	public function __construct(private readonly float $timeout = 900.0) {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function run(string $command, string &$output = ''): int
	{
		$process = Process::fromShellCommandline($command, null, null, null, $this->timeout);

		$process->run();

		$output = $process->getOutput() . $process->getErrorOutput();

		return (int) $process->getExitCode();
	}
}
