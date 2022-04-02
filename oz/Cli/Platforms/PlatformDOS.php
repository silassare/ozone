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

namespace OZONE\OZ\Cli\Platforms;

use OZONE\OZ\Cli\Platforms\Interfaces\PlatformInterface;
use OZONE\OZ\Cli\Process;

/**
 * Class PlatformDOS.
 */
class PlatformDOS implements PlatformInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function kill(string $pid): bool
	{
		$cmd = 'taskkill /T /F /PID ' . $pid;

		$p = new Process($cmd);

		$p->open();
		$error = $p->readStderr();
		$p->close();

		return empty($error);
	}

	/**
	 * {@inheritDoc}
	 */
	public function format(string $command): string
	{
		$command = \str_replace("\n", ' ', $command);

		return \sprintf('cmd /V:ON /E:ON /D /C (%s)', $command);
	}
}
