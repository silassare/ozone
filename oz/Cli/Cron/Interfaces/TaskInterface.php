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

namespace OZONE\OZ\Cli\Cron\Interfaces;

use OZONE\OZ\Cli\Cron\Schedule;

/**
 * Interface TaskInterface.
 */
interface TaskInterface
{
	/**
	 * @return string
	 */
	public function getName(): string;

	/**
	 * @return string
	 */
	public function getDescription(): string;

	/**
	 * @param \OZONE\OZ\Cli\Cron\Schedule $schedule
	 *
	 * @return $this
	 */
	public function addSchedule(Schedule $schedule): static;

	/**
	 * @param \OZONE\OZ\Cli\Cron\Schedule $schedule
	 *
	 * @return $this
	 */
	public function removeSchedule(Schedule $schedule): static;

	/**
	 * @return Schedule[]
	 */
	public function getSchedules(): array;

	/**
	 * Runs the task.
	 */
	public function run(): void;
}
