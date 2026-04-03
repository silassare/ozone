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
	 */
	public function between(string $startTime, string $endTime): static
	{
		return $this->onlyIf($this->inTimeInterval($startTime, $endTime));
	}

	/**
	 * Schedule the task to not run between start and end time.
	 *
	 * @param string $startTime the start time in "H:i" format
	 * @param string $endTime   the end time in "H:i" format
	 */
	public function notBetween(string $startTime, string $endTime): static
	{
		$predicate = $this->inTimeInterval($startTime, $endTime);

		return $this->onlyIf(static fn () => !$predicate());
	}

	/**
	 * Schedule the task to run every minute.
	 */
	public function everyMinute(): static
	{
		return $this->setPosition(1, '*');
	}

	/**
	 * Schedule the task to run every two minutes.
	 */
	public function everyTwoMinutes(): static
	{
		return $this->setPosition(1, '*/2');
	}

	/**
	 * Schedule the task to run every three minutes.
	 */
	public function everyThreeMinutes(): static
	{
		return $this->setPosition(1, '*/3');
	}

	/**
	 * Schedule the task to run every four minutes.
	 */
	public function everyFourMinutes(): static
	{
		return $this->setPosition(1, '*/4');
	}

	/**
	 * Schedule the task to run every five minutes.
	 */
	public function everyFiveMinutes(): static
	{
		return $this->setPosition(1, '*/5');
	}

	/**
	 * Schedule the task to run every ten minutes.
	 */
	public function everyTenMinutes(): static
	{
		return $this->setPosition(1, '*/10');
	}

	/**
	 * Schedule the task to run every fifteen minutes.
	 */
	public function everyFifteenMinutes(): static
	{
		return $this->setPosition(1, '*/15');
	}

	/**
	 * Schedule the task to run every thirty minutes.
	 *
	 * @param bool $atMinuteZeroAndThirty if true, the task will run at minute 0 and 30 of each hour; otherwise, it will run every 30 minutes starting from minute 0 (e.g., 0, 30)
	 */
	public function everyThirtyMinutes(bool $atMinuteZeroAndThirty = false): static
	{
		return $this->setPosition(1, $atMinuteZeroAndThirty ? '0,30' : '*/30');
	}

	/**
	 * Schedule the task to run hourly.
	 */
	public function everyHour(): static
	{
		return $this->setPosition(1, 0);
	}

	/**
	 * Schedule the task to run hourly at a given offset in the hour.
	 *
	 * @param int|int[] $offset the minute(s) of the hour to run at (0-59)
	 */
	public function everyHourAt(array|int $offset): static
	{
		return $this->setPosition(1, \is_array($offset) ? \implode(',', $offset) : $offset);
	}

	/**
	 * Schedule the task to run every odd hour.
	 */
	public function everyOddHour(): static
	{
		return $this->setPosition(1, 0)
			->setPosition(2, '1-23/2');
	}

	/**
	 * Schedule the task to run every two hours.
	 */
	public function everyTwoHours(): static
	{
		return $this->setPosition(1, 0)
			->setPosition(2, '*/2');
	}

	/**
	 * Schedule the task to run every three hours.
	 */
	public function everyThreeHours(): static
	{
		return $this->setPosition(1, 0)
			->setPosition(2, '*/3');
	}

	/**
	 * Schedule the task to run every four hours.
	 */
	public function everyFourHours(): static
	{
		return $this->setPosition(1, 0)
			->setPosition(2, '*/4');
	}

	/**
	 * Schedule the task to run every six hours.
	 */
	public function everySixHours(): static
	{
		return $this->setPosition(1, 0)
			->setPosition(2, '*/6');
	}

	/**
	 * Schedule the task to run daily.
	 */
	public function daily(): static
	{
		return $this->setPosition(1, 0)
			->setPosition(2, 0);
	}

	/**
	 * Schedule the task at a given time.
	 *
	 * @param string $time
	 */
	public function at(string $time): static
	{
		return $this->dailyAt($time);
	}

	/**
	 * Schedule the task to run daily at a given time (10:00, 19:30, etc).
	 *
	 * @param string $time the time in "H:i" format
	 */
	public function dailyAt(string $time): static
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
	 */
	public function twiceDaily(int $first = 1, int $second = 13): static
	{
		return $this->twiceDailyAt($first, $second);
	}

	/**
	 * Schedule the task to run twice daily at a given offset.
	 *
	 * @param int $first  the hour of the first run (0-23)
	 * @param int $second the hour of the second run (0-23)
	 * @param int $offset the minute of the hour to run at (0-59)
	 */
	public function twiceDailyAt(int $first = 1, int $second = 13, int $offset = 0): static
	{
		$hours = $first . ',' . $second;

		return $this->setPosition(1, $offset)
			->setPosition(2, $hours);
	}

	/**
	 * Schedule the task to run only on weekdays.
	 */
	public function weekdays(): static
	{
		return $this->days(self::MONDAY . '-' . self::FRIDAY);
	}

	/**
	 * Schedule the task to run only on weekends.
	 */
	public function weekends(): static
	{
		return $this->days(self::SATURDAY . ',' . self::SUNDAY);
	}

	/**
	 * Schedule the task to run only on Mondays.
	 */
	public function mondays(): static
	{
		return $this->days(self::MONDAY);
	}

	/**
	 * Schedule the task to run only on Tuesdays.
	 */
	public function tuesdays(): static
	{
		return $this->days(self::TUESDAY);
	}

	/**
	 * Schedule the task to run only on Wednesdays.
	 */
	public function wednesdays(): static
	{
		return $this->days(self::WEDNESDAY);
	}

	/**
	 * Schedule the task to run only on Thursdays.
	 */
	public function thursdays(): static
	{
		return $this->days(self::THURSDAY);
	}

	/**
	 * Schedule the task to run only on Fridays.
	 */
	public function fridays(): static
	{
		return $this->days(self::FRIDAY);
	}

	/**
	 * Schedule the task to run only on Saturdays.
	 */
	public function saturdays(): static
	{
		return $this->days(self::SATURDAY);
	}

	/**
	 * Schedule the task to run only on Sundays.
	 */
	public function sundays(): static
	{
		return $this->days(self::SUNDAY);
	}

	/**
	 * Schedule the task to run weekly.
	 */
	public function weekly(): static
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
	 */
	public function weeklyOn(mixed $dayOfWeek, string $time = '0:0'): static
	{
		$this->dailyAt($time);

		return $this->days($dayOfWeek);
	}

	/**
	 * Schedule the task to run monthly.
	 */
	public function monthly(): static
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
	 */
	public function monthlyOn(int $dayOfMonth = 1, string $time = '0:0'): static
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
	 */
	public function twiceMonthly(int $first = 1, int $second = 16, string $time = '0:0'): static
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
	 */
	public function lastDayOfMonth(string $time = '0:0'): static
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
	 */
	public function quarterly(): static
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
	 */
	public function quarterlyOn(int|string $dayOfQuarter = 1, string $time = '0:0'): static
	{
		$this->dailyAt($time);

		return $this->setPosition(3, $dayOfQuarter)
			->setPosition(4, '1-12/3');
	}

	/**
	 * Schedule the task to run yearly.
	 */
	public function yearly(): static
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
	 */
	public function yearlyOn(int|string $month = 1, int|string $dayOfMonth = 1, string $time = '0:0'): static
	{
		$this->dailyAt($time);

		return $this->setPosition(3, $dayOfMonth)
			->setPosition(4, $month);
	}

	/**
	 * Set the days of the week the task should run on.
	 *
	 * @param int|int[]|string $days the day(s) of the week to run on (0-6, where 0 is Sunday); accepts a single int constant, an array of int constants, a pre-formatted cron field string (e.g. '1-5'), or multiple ints as separate arguments
	 */
	public function days(mixed $days): static
	{
		$days = \is_array($days) ? $days : \func_get_args();

		return $this->setPosition(5, \implode(',', $days));
	}

	/**
	 * Set the timezone the date should be evaluated on.
	 *
	 * @param DateTimeZone|string $timezone the timezone to set (e.g., "UTC", "America/New_York", etc.)
	 */
	public function timezone(DateTimeZone|string $timezone): static
	{
		$this->timezone = $timezone;

		return $this;
	}

	/**
	 * Skip this execution if the task is more than `$grace_minutes` late.
	 *
	 * Useful for tasks that should not run if the cron daemon was offline and
	 * the scheduled window has already passed. The check is evaluated lazily at
	 * {@link shouldRun()} time.
	 *
	 * @param int $grace_minutes how many minutes past the scheduled time are acceptable (>= 1)
	 *
	 * @return static
	 */
	public function skipIfLate(int $grace_minutes = 5): static
	{
		return $this->onlyIf(function () use ($grace_minutes): bool {
			$now = \time();

			// Approximate the most recent due time by searching one full
			// day backward in one-minute steps until we find a slot that
			// isDue(). We look in 60-second windows for up to 1440 minutes.
			$prev_due = null;

			for ($offset = 1; $offset <= 1440; ++$offset) {
				$candidate = $now - ($offset * 60);

				if ($this->isDue($candidate)) {
					$prev_due = $candidate;

					break;
				}
			}

			if (null === $prev_due) {
				return true; // no previous slot found - let it run
			}

			return ($now - $prev_due) <= ($grace_minutes * 60);
		});
	}

	/**
	 * Add runs predicate.
	 *
	 * @param callable $fn the predicate function that determines if the task should run
	 */
	private function onlyIf(callable $fn): static
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
	 */
	private function setPosition(int $position, int|string $value): static
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
