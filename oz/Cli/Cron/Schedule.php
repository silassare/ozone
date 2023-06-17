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

namespace OZONE\Core\Cli\Cron;

use Carbon\Carbon;
use Closure;
use Cron\CronExpression;
use DateTime;
use DateTimeZone;
use Exception;
use Stringable;

/**
 * Class Scheduler.
 */
final class Schedule implements Stringable
{
	public const SUNDAY = 0;

	public const MONDAY = 1;

	public const TUESDAY = 2;

	public const WEDNESDAY = 3;

	public const THURSDAY = 4;

	public const FRIDAY = 5;

	public const SATURDAY = 6;

	private string $minute;

	private string $hour;

	private string $dayOfMonth;

	private string $month;

	private string $dayOfWeek;

	private string|DateTimeZone $timezone = 'UTC';

	/** @var array<callable():bool> */
	private array $only_if = [];

	/**
	 * Scheduler constructor.
	 *
	 * @param string $expression the Cron expression representing the task's frequency
	 */
	public function __construct(string $expression = '* * * * *')
	{
		$segments = \preg_split('/\\s+/', $expression);

		$this->minute     = $segments[0] ?? '*';
		$this->hour       = $segments[1] ?? '*';
		$this->dayOfMonth = $segments[2] ?? '*';
		$this->month      = $segments[3] ?? '*';
		$this->dayOfWeek  = $segments[4] ?? '*';
	}

	/**
	 * {@inheritDoc}
	 */
	public function __toString()
	{
		return \implode(' ', [$this->minute, $this->hour, $this->dayOfMonth, $this->month, $this->dayOfWeek]);
	}

	/**
	 * Checks if the task is due to run based on the cron expression.
	 *
	 * @param int $time
	 *
	 * @return bool
	 *
	 * @throws Exception
	 */
	public function isDue(int $time): bool
	{
		$dt = new DateTime('@' . $time);

		return (new CronExpression((string) $this))->isDue($dt);
	}

	/**
	 * Checks if all conditions are met for the current schedule.
	 *
	 * @return bool
	 */
	public function shouldRun(): bool
	{
		foreach ($this->only_if as $fn) {
			if (!$fn()) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns next run time.
	 *
	 * @throws Exception
	 */
	public function getNextRunTime(int $time_from): int
	{
		$dt   = new DateTime('@' . $time_from);
		$cron = new CronExpression((string) $this);

		return $cron->getNextRunDate($dt)
			->getTimestamp();
	}

	/**
	 * Schedule the task to run between start and end time.
	 *
	 * @param string $startTime
	 * @param string $endTime
	 *
	 * @return $this
	 */
	public function between(string $startTime, string $endTime): self
	{
		return $this->onlyIf($this->inTimeInterval($startTime, $endTime));
	}

	/**
	 * Schedule the task to not run between start and end time.
	 *
	 * @param string $startTime
	 * @param string $endTime
	 *
	 * @return $this
	 */
	public function notBetween(string $startTime, string $endTime): self
	{
		return $this->onlyIf(function () use ($endTime, $startTime) {
			$predicate = $this->inTimeInterval($startTime, $endTime);

			return !$predicate();
		});
	}

	/**
	 * Schedule the task to run every minute.
	 *
	 * @return $this
	 */
	public function everyMinute(): self
	{
		return $this->setPosition(1, '*');
	}

	/**
	 * Schedule the task to run every two minutes.
	 *
	 * @return $this
	 */
	public function everyTwoMinutes(): self
	{
		return $this->setPosition(1, '*/2');
	}

	/**
	 * Schedule the task to run every three minutes.
	 *
	 * @return $this
	 */
	public function everyThreeMinutes(): self
	{
		return $this->setPosition(1, '*/3');
	}

	/**
	 * Schedule the task to run every four minutes.
	 *
	 * @return $this
	 */
	public function everyFourMinutes(): self
	{
		return $this->setPosition(1, '*/4');
	}

	/**
	 * Schedule the task to run every five minutes.
	 *
	 * @return $this
	 */
	public function everyFiveMinutes(): self
	{
		return $this->setPosition(1, '*/5');
	}

	/**
	 * Schedule the task to run every ten minutes.
	 *
	 * @return $this
	 */
	public function everyTenMinutes(): self
	{
		return $this->setPosition(1, '*/10');
	}

	/**
	 * Schedule the task to run every fifteen minutes.
	 *
	 * @return $this
	 */
	public function everyFifteenMinutes(): self
	{
		return $this->setPosition(1, '*/15');
	}

	/**
	 * Schedule the task to run every thirty minutes.
	 *
	 * @return $this
	 */
	public function everyThirtyMinutes(bool $atMinuteZeroAndThirty = false): self
	{
		return $this->setPosition(1, $atMinuteZeroAndThirty ? '0,30' : '*/30');
	}

	/**
	 * Schedule the task to run hourly.
	 *
	 * @return $this
	 */
	public function hourly(): self
	{
		return $this->setPosition(1, 0);
	}

	/**
	 * Schedule the task to run hourly at a given offset in the hour.
	 *
	 * @param int|int[] $offset
	 *
	 * @return $this
	 */
	public function hourlyAt(array|int $offset): self
	{
		return $this->setPosition(1, \is_array($offset) ? \implode(',', $offset) : $offset);
	}

	/**
	 * Schedule the task to run every odd hour.
	 *
	 * @return $this
	 */
	public function everyOddHour(): self
	{
		return $this->setPosition(1, 0)
			->setPosition(2, '1-23/2');
	}

	/**
	 * Schedule the task to run every two hours.
	 *
	 * @return $this
	 */
	public function everyTwoHours(): self
	{
		return $this->setPosition(1, 0)
			->setPosition(2, '*/2');
	}

	/**
	 * Schedule the task to run every three hours.
	 *
	 * @return $this
	 */
	public function everyThreeHours(): self
	{
		return $this->setPosition(1, 0)
			->setPosition(2, '*/3');
	}

	/**
	 * Schedule the task to run every four hours.
	 *
	 * @return $this
	 */
	public function everyFourHours(): self
	{
		return $this->setPosition(1, 0)
			->setPosition(2, '*/4');
	}

	/**
	 * Schedule the task to run every six hours.
	 *
	 * @return $this
	 */
	public function everySixHours(): self
	{
		return $this->setPosition(1, 0)
			->setPosition(2, '*/6');
	}

	/**
	 * Schedule the task to run daily.
	 *
	 * @return $this
	 */
	public function daily(): self
	{
		return $this->setPosition(1, 0)
			->setPosition(2, 0);
	}

	/**
	 * Schedule the task at a given time.
	 *
	 * @param string $time
	 *
	 * @return $this
	 */
	public function at(string $time): self
	{
		return $this->dailyAt($time);
	}

	/**
	 * Schedule the task to run daily at a given time (10:00, 19:30, etc).
	 *
	 * @param string $time
	 *
	 * @return $this
	 */
	public function dailyAt(string $time): self
	{
		$segments = \explode(':', $time);

		return $this->setPosition(2, (int) $segments[0])
			->setPosition(1, 2 === \count($segments) ? (int) $segments[1] : '0');
	}

	/**
	 * Schedule the task to run twice daily.
	 *
	 * @param int $first
	 * @param int $second
	 *
	 * @return $this
	 */
	public function twiceDaily(int $first = 1, int $second = 13): self
	{
		return $this->twiceDailyAt($first, $second);
	}

	/**
	 * Schedule the task to run twice daily at a given offset.
	 *
	 * @param int $first
	 * @param int $second
	 * @param int $offset
	 *
	 * @return $this
	 */
	public function twiceDailyAt(int $first = 1, int $second = 13, int $offset = 0): self
	{
		$hours = $first . ',' . $second;

		return $this->setPosition(1, $offset)
			->setPosition(2, $hours);
	}

	/**
	 * Schedule the task to run only on weekdays.
	 *
	 * @return $this
	 */
	public function weekdays(): self
	{
		return $this->days(self::MONDAY . '-' . self::FRIDAY);
	}

	/**
	 * Schedule the task to run only on weekends.
	 *
	 * @return $this
	 */
	public function weekends(): self
	{
		return $this->days(self::SATURDAY . ',' . self::SUNDAY);
	}

	/**
	 * Schedule the task to run only on Mondays.
	 *
	 * @return $this
	 */
	public function mondays(): self
	{
		return $this->days(self::MONDAY);
	}

	/**
	 * Schedule the task to run only on Tuesdays.
	 *
	 * @return $this
	 */
	public function tuesdays(): self
	{
		return $this->days(self::TUESDAY);
	}

	/**
	 * Schedule the task to run only on Wednesdays.
	 *
	 * @return $this
	 */
	public function wednesdays(): self
	{
		return $this->days(self::WEDNESDAY);
	}

	/**
	 * Schedule the task to run only on Thursdays.
	 *
	 * @return $this
	 */
	public function thursdays(): self
	{
		return $this->days(self::THURSDAY);
	}

	/**
	 * Schedule the task to run only on Fridays.
	 *
	 * @return $this
	 */
	public function fridays(): self
	{
		return $this->days(self::FRIDAY);
	}

	/**
	 * Schedule the task to run only on Saturdays.
	 *
	 * @return $this
	 */
	public function saturdays(): self
	{
		return $this->days(self::SATURDAY);
	}

	/**
	 * Schedule the task to run only on Sundays.
	 *
	 * @return $this
	 */
	public function sundays(): self
	{
		return $this->days(self::SUNDAY);
	}

	/**
	 * Schedule the task to run weekly.
	 *
	 * @return $this
	 */
	public function weekly(): self
	{
		return $this->setPosition(1, 0)
			->setPosition(2, 0)
			->setPosition(5, 0);
	}

	/**
	 * Schedule the task to run weekly on a given day and time.
	 *
	 * @param array|mixed $dayOfWeek
	 * @param string      $time
	 *
	 * @return $this
	 */
	public function weeklyOn(mixed $dayOfWeek, string $time = '0:0'): self
	{
		$this->dailyAt($time);

		return $this->days($dayOfWeek);
	}

	/**
	 * Schedule the task to run monthly.
	 *
	 * @return $this
	 */
	public function monthly(): self
	{
		return $this->setPosition(1, 0)
			->setPosition(2, 0)
			->setPosition(3, 1);
	}

	/**
	 * Schedule the task to run monthly on a given day and time.
	 *
	 * @param int    $dayOfMonth
	 * @param string $time
	 *
	 * @return $this
	 */
	public function monthlyOn(int $dayOfMonth = 1, string $time = '0:0'): self
	{
		$this->dailyAt($time);

		return $this->setPosition(3, $dayOfMonth);
	}

	/**
	 * Schedule the task to run twice monthly at a given time.
	 *
	 * @param int    $first
	 * @param int    $second
	 * @param string $time
	 *
	 * @return $this
	 */
	public function twiceMonthly(int $first = 1, int $second = 16, string $time = '0:0'): self
	{
		$daysOfMonth = $first . ',' . $second;

		$this->dailyAt($time);

		return $this->setPosition(3, $daysOfMonth);
	}

	/**
	 * Schedule the task to run on the last day of the month.
	 *
	 * @param string $time
	 *
	 * @return $this
	 */
	public function lastDayOfMonth(string $time = '0:0'): self
	{
		$this->dailyAt($time);

		return $this->setPosition(3, Carbon::now()
			->endOfMonth()->day);
	}

	/**
	 * Schedule the task to run quarterly.
	 *
	 * @return $this
	 */
	public function quarterly(): self
	{
		return $this->setPosition(1, 0)
			->setPosition(2, 0)
			->setPosition(3, 1)
			->setPosition(4, '1-12/3');
	}

	/**
	 * Schedule the task to run quarterly on a given day and time.
	 *
	 * @param int|string $dayOfQuarter
	 * @param string     $time
	 *
	 * @return $this
	 */
	public function quarterlyOn(int|string $dayOfQuarter = 1, string $time = '0:0'): self
	{
		$this->dailyAt($time);

		return $this->setPosition(3, $dayOfQuarter)
			->setPosition(4, '1-12/3');
	}

	/**
	 * Schedule the task to run yearly.
	 *
	 * @return $this
	 */
	public function yearly(): self
	{
		return $this->setPosition(1, 0)
			->setPosition(2, 0)
			->setPosition(3, 1)
			->setPosition(4, 1);
	}

	/**
	 * Schedule the task to run yearly on a given month, day, and time.
	 *
	 * @param int|string $month
	 * @param int|string $dayOfMonth
	 * @param string     $time
	 *
	 * @return $this
	 */
	public function yearlyOn(int|string $month = 1, int|string $dayOfMonth = 1, string $time = '0:0'): self
	{
		$this->dailyAt($time);

		return $this->setPosition(3, $dayOfMonth)
			->setPosition(4, $month);
	}

	/**
	 * Set the days of the week the task should run on.
	 *
	 * @param array|mixed $days
	 *
	 * @return $this
	 */
	public function days(mixed $days): self
	{
		$days = \is_array($days) ? $days : \func_get_args();

		return $this->setPosition(5, \implode(',', $days));
	}

	/**
	 * Set the timezone the date should be evaluated on.
	 *
	 * @param DateTimeZone|string $timezone
	 *
	 * @return $this
	 */
	public function timezone(DateTimeZone|string $timezone): self
	{
		$this->timezone = $timezone;

		return $this;
	}

	/**
	 * Add runs predicate.
	 *
	 * @param callable $fn
	 *
	 * @return $this
	 */
	private function onlyIf(callable $fn): self
	{
		$this->only_if[] = $fn;

		return $this;
	}

	/**
	 * Schedule the task to run between start and end time.
	 *
	 * @param string $startTime
	 * @param string $endTime
	 *
	 * @return Closure
	 */
	private function inTimeInterval(string $startTime, string $endTime): Closure
	{
		[$now, $start, $end] = [
			Carbon::now($this->timezone),
			Carbon::parse($startTime, $this->timezone),
			Carbon::parse($endTime, $this->timezone),
		];

		if ($end->lessThan($start)) {
			if ($start->greaterThan($now)) {
				$start = $start->subDay();
			} else {
				$end = $end->addDay();
			}
		}

		return static function () use ($now, $start, $end) {
			return $now->between($start, $end);
		};
	}

	/**
	 * Set the position of the given value.
	 *
	 * @param int        $position
	 * @param int|string $value
	 *
	 * @return $this
	 */
	private function setPosition(int $position, string|int $value): self
	{
		if (1 === $position) {
			$this->minute = $value;
		} elseif (2 === $position) {
			$this->hour = $value;
		} elseif (3 === $position) {
			$this->dayOfMonth = $value;
		} elseif (4 === $position) {
			$this->month = $value;
		} elseif (5 === $position) {
			$this->dayOfWeek = $value;
		}

		return $this;
	}
}
