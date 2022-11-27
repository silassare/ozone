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

namespace OZONE\OZ\Cli\Cron\Traits;

use OZONE\OZ\Cli\Cron\Interfaces\TaskInterface;
use OZONE\OZ\Cli\Cron\Schedule;

/**
 * Class TaskBase.
 */
abstract class TaskBase implements TaskInterface
{
	/**
	 * TaskBase constructor.
	 *
	 * @param string   $name
	 * @param string   $description
	 */
	public function __construct(protected string $name, protected string $description = '')
	{
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
	 * @var \OZONE\OZ\Cli\Cron\Schedule[]
	 */
	private array $schedules = [];

	/**
	 * {@inheritDoc}
	 */
	public function addSchedule(Schedule $schedule): static
	{
		$this->schedules[] = $schedule;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function removeSchedule(Schedule $schedule): static
	{
		$this->schedules = array_filter($this->schedules, static fn($entry) => $entry !== $schedule);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSchedules(): array
	{
		return $this->schedules;
	}
}
