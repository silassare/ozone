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

namespace OZONE\Core\Cli\Cron\Tasks;

use Override;
use OZONE\Core\Cli\Cron\Interfaces\TaskInterface;
use OZONE\Core\Cli\Cron\Schedule;
use OZONE\Core\Utils\JSONResult;

/**
 * Class AbstractTask.
 *
 * Base class for all cron tasks.
 *
 * A task defines what to execute (overriding {@link run()}) and when, via one or more
 * {@link Schedule} instances attached through {@link schedule()} or {@link addSchedule()}.
 *
 * Key configuration flags:
 * - {@link inBackground()} - whether the task should run in a background process (maps to
 *   the `cron:async` queue when dispatched by {@link Cron::runDues()}).
 * - {@link oneAtATime()} - skip execution if a previous instance is still running
 *   (enforced via DB job state check on the oz_jobs table).
 * - {@link setTimeout()} - maximum allowed execution time in seconds.
 *
 * After {@link run()} completes, results are exposed via {@link getResult()} and stored
 * in the job record by {@link CronTaskWorker}.
 */
abstract class AbstractTask implements TaskInterface
{
	/**
	 * @var Schedule[]
	 */
	protected array $schedules     = [];
	protected bool $in_background  = false;
	protected bool $one_at_a_time  = false;
	protected ?int $timeout        = null;
	protected JSONResult $result;

	/**
	 * TaskBase constructor.
	 *
	 * @param string $name
	 * @param string $description
	 */
	public function __construct(
		protected readonly string $name,
		protected string $description = ''
	) {
		$this->result = new JSONResult();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setDescription(string $description): static
	{
		$this->description = $description;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function addSchedule(Schedule $schedule): static
	{
		$this->schedules[] = $schedule;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function schedule(): Schedule
	{
		$this->addSchedule($schedule = new Schedule());

		return $schedule;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getSchedules(): array
	{
		return $this->schedules;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function inBackground(): static
	{
		$this->in_background = true;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function shouldRunInBackground(): bool
	{
		return $this->in_background;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function oneAtATime(int $timeout = 0): static
	{
		$this->one_at_a_time = true;
		$this->timeout       = $timeout;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function shouldRunOneAtATime(): bool
	{
		return $this->one_at_a_time;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getTimeout(): ?int
	{
		return $this->timeout;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setTimeout(?int $timeout = null): static
	{
		$this->timeout = $timeout;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getResult(): JSONResult
	{
		return $this->result;
	}
}
