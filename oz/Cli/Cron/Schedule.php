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

use Stringable;

/**
 * Class Scheduler.
 */
final class Schedule implements Stringable
{
	private string $minute = '*';

	private string $hour = '*';

	private string $day = '*';

	private string $week = '*';

	private string $month = '*';

	private string $year = '*';

	/**
	 * {@inheritDoc}
	 */
	public function __toString()
	{
		return '';
	}

	public function everyYear(): self
	{
		return $this;
	}

	public function year(int $year): self
	{
		return $this;
	}

	public function everyMonth(): self
	{
		return $this;
	}

	public function month(int $month): self
	{
		return $this;
	}

	public function everyWeek(): self
	{
		return $this;
	}

	public function week(int $week): self
	{
		return $this;
	}

	public function everyDay(): self
	{
		return $this;
	}

	public function day(int $day): self
	{
		return $this;
	}

	public function everyHour(): self
	{
		return $this;
	}

	public function hour(int $hour): self
	{
		return $this;
	}

	public function everyMinute(): self
	{
		return $this;
	}

	public function minute(int $minute): self
	{
		return $this;
	}

	public function validate(int $time): bool
	{
		return true;
	}

	/**
	 * @param string $schedule
	 */
	public static function parse(string $schedule): self
	{
		# TODO
		# daemon's notion of time and timezones.
		#
		# Output of the crontab jobs (including errors) is sent through
		# email to the user the crontab file belongs to (unless redirected).
		#
		# For example, you can run a backup of all your user accounts
		# at 5 a.m every week with:
		# 0 5 * * 1 tar -zcf /var/backups/home.tgz /home/
		#
		# For more information see the manual pages of crontab(5) and cron(8)
		#
		# m h  dom mon dow   command

		# */2 * * * * oz project backup /mnt/backup-drive/
		# */5 * * * * oz db backup /mnt/backup-drive/

		$sc = new self();

		return $sc->everyYear()
			->month(2)
			->day(5);
	}
}
