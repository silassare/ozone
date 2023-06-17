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

namespace OZONE\Core\Cli\Platforms;

use OZONE\Core\Cli\Platforms\Interfaces\PlatformInterface;
use OZONE\Core\Cli\Process;

/**
 * Class PlatformLinux.
 */
class PlatformLinux implements PlatformInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function kill(string $pid): bool
	{
		$cmd = 'kill -9 ' . $pid;

		$p = new Process($cmd);

		$p->open();
		$error = $p->readStderr();
		$p->close();

		return empty($error);
	}

	/**
	 * {@inheritDoc}
	 */
	public function format(string $command, bool $run_in_background = false): string
	{
		if ($run_in_background) {
			$command = \sprintf('nohup %s > /dev/null 2>&1 &', $command);
		}

		return $command;
	}
}
