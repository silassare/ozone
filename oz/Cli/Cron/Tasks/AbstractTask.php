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

use OZONE\Core\Cli\Cron\Interfaces\TaskInterface;
use OZONE\Core\Cli\Cron\Schedule;

/**
 * Class TaskBase.
 */
abstract class AbstractTask implements TaskInterface
{
	/**
	 * @var \OZONE\Core\Cli\Cron\Schedule[]
	 */
	protected array $schedules     = [];
	protected bool $in_background  = false;
	protected bool $one_at_a_time  = false;
	protected ?int $timeout        = null;
	protected array $result        = [];

	protected readonly string $cache_key;

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
		$this->cache_key = 'cron_task_' . $name;
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
	public function setDescription(string $description): self
	{
		$this->description = $description;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function addSchedule(Schedule $schedule): self
	{
		$this->schedules[] = $schedule;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function schedule(): Schedule
	{
		$this->addSchedule($schedule = new Schedule());

		return $schedule;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSchedules(): array
	{
		return $this->schedules;
	}

	/**
	 * {@inheritDoc}
	 */
	public function inBackground(): self
	{
		$this->in_background = true;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function shouldRunInBackground(): bool
	{
		return $this->in_background;
	}

	/**
	 * {@inheritDoc}
	 */
	public function oneAtATime(int $timeout = 0): self
	{
		$this->one_at_a_time = true;
		$this->timeout       = $timeout;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function shouldRunOneAtATime(): bool
	{
		return $this->one_at_a_time;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTimeout(): ?int
	{
		return $this->timeout;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setTimeout(?int $timeout = null): self
	{
		$this->timeout = $timeout;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getResult(): array
	{
		return $this->result;
	}
}
