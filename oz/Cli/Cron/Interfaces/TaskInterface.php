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

namespace OZONE\Core\Cli\Cron\Interfaces;

use OZONE\Core\Cli\Cron\Schedule;

/**
 * Interface TaskInterface.
 */
interface TaskInterface
{
	/**
	 * Get task name.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Get task description.
	 *
	 * @return string
	 */
	public function getDescription(): string;

	/**
	 * Set task description.
	 *
	 * @return $this
	 */
	public function setDescription(string $description): self;

	/**
	 * Add a schedule to the task.
	 *
	 * @param \OZONE\Core\Cli\Cron\Schedule $schedule
	 *
	 * @return $this
	 */
	public function addSchedule(Schedule $schedule): self;

	/**
	 * Create a new schedule.
	 *
	 * This is a shortcut to {@link addSchedule()}.
	 *
	 * @return \OZONE\Core\Cli\Cron\Schedule
	 */
	public function schedule(): Schedule;

	/**
	 * Get all schedules.
	 *
	 * @return Schedule[]
	 */
	public function getSchedules(): array;

	/**
	 * Runs the task.
	 */
	public function run(): void;

	/**
	 * Get the task result.
	 *
	 * @return array
	 */
	public function getResult(): array;

	/**
	 * Mark the task as background task.
	 *
	 * @return $this
	 */
	public function inBackground(): self;

	/**
	 * Should run in background?
	 */
	public function shouldRunInBackground(): bool;

	/**
	 * Mark the task as one at a time.
	 *
	 * @return $this
	 */
	public function oneAtATime(): self;

	/**
	 * Gets the task timeout in seconds.
	 *
	 * @return null|int
	 */
	public function getTimeout(): ?int;

	/**
	 * Sets the task timeout in seconds.
	 *
	 * @param null|int $timeout
	 *
	 * @return $this
	 */
	public function setTimeout(?int $timeout = null): self;

	/**
	 * Should run one at a time?
	 */
	public function shouldRunOneAtATime(): bool;
}
