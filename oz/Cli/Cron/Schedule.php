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

use Closure;
use DateTime;
use DateTimeZone;
use Exception;
use Override;
use OZONE\Core\Utils\DateTimeUtils;
use Stringable;

/**
 * Class Schedule.
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

	private DateTimeZone|string $timezone = 'UTC';

	/** @var array<callable():bool> */
	private array $only_if = [];

	/**
	 * Scheduler constructor.
	 *
	 * @param string $expression the Cron expression representing the task's frequency
	 */
	public function __construct(string $expression = '* * * * *')
	{
		$segments = \preg_split('/\s+/', $expression);

		$this->minute     = $segments[0] ?? '*';
		$this->hour       = $segments[1] ?? '*';
		$this->dayOfMonth = $segments[2] ?? '*';
		$this->month      = $segments[3] ?? '*';
		$this->dayOfWeek  = $segments[4] ?? '*';
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function __toString()
	{
		return \implode(' ', [$this->minute, $this->hour, $this->dayOfMonth, $this->month, $this->dayOfWeek]);
	}

	/**
	 * Checks if the task is due to run based on the cron expression.
	 *
	 * @param int $time the timestamp to check against
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
			if ($fn()) {
				continue;
			}

			return false;
		}

		return true;
	}

	/**
	 * Returns next run time.
	 *
	 * @param int $time_from the timestamp to calculate the next run time from
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
	 * @param string $startTime the start time in "H:i" format
	 * @param string $endTime   the end time in "H:i" format
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
	 * @param string $startTime the start time in "H:i" format
	 * @param string $endTime   the end time in "H:i" format
	 *
	 * @return $this
	 */
	public function notBetween(string $startTime, string $endTime): self
	{
		$predicate = $this->inTimeInterval($startTime, $endTime);

		return $this->onlyIf(static fn () => !$predicate());
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
	 * @param bool $atMinuteZeroAndThirty if true, the task will run at minute 0 and 30 of each hour; otherwise, it will run every 30 minutes starting from minute 0 (e.g., 0, 30)
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
	public function everyHour(): self
	{
		return $this->setPosition(1, 0);
	}

	/**
	 * Schedule the task to run hourly at a given offset in the hour.
	 *
	 * @param int|int[] $offset the minute(s) of the hour to run at (0-59)
	 *
	 * @return $this
	 */
	public function everyHourAt(array|int $offset): self
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
	 * @param string $time the time in "H:i" format
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
	 * @param int $first  the hour of the first run (0-23)
	 * @param int $second the hour of the second run (0-23)
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
	 * @param int $first  the hour of the first run (0-23)
	 * @param int $second the hour of the second run (0-23)
	 * @param int $offset the minute of the hour to run at (0-59)
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
	 * @param int|int[]|string $dayOfWeek the day(s) of the week to run on (0-6, where 0 is Sunday); accepts a single int constant, an array of int constants, or a pre-formatted cron field string (e.g. '1-5')
	 * @param string           $time      the time in "H:i" format
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
	 * @param int    $dayOfMonth the day of the month to run on (1-31)
	 * @param string $time       the time in "H:i" format
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
	 * @param int    $first  the day of the month for the first run (1-31)
	 * @param int    $second the day of the month for the second run (1-31)
	 * @param string $time   the time in "H:i" format
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
	 * The day-of-month field is set to `28-31` (all potential last days) and a
	 * lazy predicate is added so the task only fires when today's date equals
	 * the actual last day of the current month.
	 *
	 * @param string $time the time in "H:i" format
	 *
	 * @return $this
	 */
	public function lastDayOfMonth(string $time = '0:0'): self
	{
		$this->dailyAt($time);

		$this->setPosition(3, '28-31');

		return $this->onlyIf(function (): bool {
			$now = DateTimeUtils::now($this->timezone);

			return $now->getDay() === $now->endOfMonth()->getDay();
		});
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
	 * @param int|string $dayOfQuarter the day of the quarter to run on (1-31)
	 * @param string     $time         the time in "H:i" format
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
	 * @param int|string $month      the month to run on (1-12)
	 * @param int|string $dayOfMonth the day of the month to run on (1-31)
	 * @param string     $time       the time in "H:i" format
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
	 * @param int|int[]|string $days the day(s) of the week to run on (0-6, where 0 is Sunday); accepts a single int constant, an array of int constants, a pre-formatted cron field string (e.g. '1-5'), or multiple ints as separate arguments
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
	 * @param DateTimeZone|string $timezone the timezone to set (e.g., "UTC", "America/New_York", etc.)
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
	 * @param callable $fn the predicate function that determines if the task should run
	 *
	 * @return $this
	 */
	private function onlyIf(callable $fn): self
	{
		$this->only_if[] = $fn;

		return $this;
	}

	/**
	 * Returns a closure that checks whether the current time falls in the given interval.
	 *
	 * The closure is evaluated lazily: $now, $start, and $end are resolved each time
	 * it is called (i.e. at shouldRun() time), not at schedule-build time. This
	 * ensures between() / notBetween() always compare against the real current time.
	 *
	 * When $endTime < $startTime the interval wraps across midnight: $start is moved
	 * back one day (if $start > $now) or $end is moved forward one day.
	 *
	 * @param string $startTime the start time in "H:i" format
	 * @param string $endTime   the end time in "H:i" format
	 *
	 * @return Closure():bool
	 */
	private function inTimeInterval(string $startTime, string $endTime): Closure
	{
		return function () use ($startTime, $endTime): bool {
			$now   = DateTimeUtils::now($this->timezone);
			$start = DateTimeUtils::parse($startTime, $this->timezone);
			$end   = DateTimeUtils::parse($endTime, $this->timezone);

			if ($end->lessThan($start)) {
				if ($start->greaterThan($now)) {
					$start = $start->subDay();
				} else {
					$end = $end->addDay();
				}
			}

			return $now->between($start, $end);
		};
	}

	/**
	 * Set the position of the given value.
	 *
	 * @param int        $position the position to set (1 for minute, 2 for hour, 3 for day of month, 4 for month, 5 for day of week)
	 * @param int|string $value    the value to set at the given position
	 *
	 * @return $this
	 */
	private function setPosition(int $position, int|string $value): self
	{
		$str = (string) $value;

		if (1 === $position) {
			$this->minute = $str;
		} elseif (2 === $position) {
			$this->hour = $str;
		} elseif (3 === $position) {
			$this->dayOfMonth = $str;
		} elseif (4 === $position) {
			$this->month = $str;
		} elseif (5 === $position) {
			$this->dayOfWeek = $str;
		}

		return $this;
	}
}
