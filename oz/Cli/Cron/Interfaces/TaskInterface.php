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
use OZONE\Core\Utils\JSONResult;

/**
 * Interface TaskInterface.
 */
interface TaskInterface
{
	/**
	 * Get task name.
	 */
	public function getName(): string;

	/**
	 * Get task description.
	 */
	public function getDescription(): string;

	/**
	 * Set task description.
	 */
	public function setDescription(string $description): static;

	/**
	 * Add a schedule to the task.
	 */
	public function addSchedule(Schedule $schedule): static;

	/**
	 * Create a new schedule.
	 *
	 * This is a shortcut to {@link addSchedule()}.
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
	 */
	public function getResult(): JSONResult;

	/**
	 * Mark the task as background task.
	 */
	public function inBackground(): static;

	/**
	 * Should run in background?
	 */
	public function shouldRunInBackground(): bool;

	/**
	 * Mark the task as one at a time.
	 *
	 * @param int $timeout maximum execution time in seconds (0 = unlimited)
	 */
	public function oneAtATime(int $timeout = 0): static;

	/**
	 * Gets the task timeout in seconds.
	 */
	public function getTimeout(): ?int;

	/**
	 * Sets the task timeout in seconds.
	 */
	public function setTimeout(?int $timeout = null): static;

	/**
	 * Should run one at a time?
	 */
	public function shouldRunOneAtATime(): bool;
}
