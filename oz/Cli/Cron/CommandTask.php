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

use OZONE\OZ\Cli\Cron\Interfaces\TaskInterface;
use OZONE\OZ\Cli\Process;

class CommandTask implements TaskInterface
{
	private string $name;

	private string $command;

	private string $description;

	/**
	 * Task constructor.
	 *
	 * @param string $name
	 * @param string $command
	 * @param string $description
	 */
	public function __construct(string $name, string $command, string $description = '')
	{
		$this->name        = $name;
		$this->description = $description;
		$this->command     = $command;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDescription(): string
	{
		return $this->description;
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
