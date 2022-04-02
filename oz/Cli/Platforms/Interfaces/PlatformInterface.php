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

namespace OZONE\OZ\Cli\Platforms\Interfaces;

/**
 * Interface PlatformInterface.
 */
interface PlatformInterface
{
	/**
	 * Kill a process with a given PID.
	 *
	 * @param string $pid
	 *
	 * @return bool
	 */
	public function kill(string $pid): bool;

	/**
	 * Command format helper.
	 *
	 * @param string $command
	 *
	 * @return string
	 */
	public function format(string $command): string;
}
