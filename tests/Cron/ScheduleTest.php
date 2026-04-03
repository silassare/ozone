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

namespace OZONE\Tests\Cron;

use DateTime;
use DateTimeZone;
use OZONE\Core\Cli\Cron\Schedule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ScheduleTest extends TestCase
{
	public function testDefaultExpressionIsEveryMinute(): void
	{
		self::assertSame('* * * * *', (string) new Schedule());
	}

	public function testConstructorPreservesExpression(): void
	{
		self::assertSame('30 9 * * 1', (string) new Schedule('30 9 * * 1'));
	}

	public function testEveryMinute(): void
	{
		self::assertSame('* * * * *', (string) (new Schedule())->everyMinute());
	}

	public function testEveryTwoMinutes(): void
	{
		self::assertSame('*/2 * * * *', (string) (new Schedule())->everyTwoMinutes());
	}

	public function testEveryThreeMinutes(): void
	{
		self::assertSame('*/3 * * * *', (string) (new Schedule())->everyThreeMinutes());
	}

	public function testEveryFourMinutes(): void
	{
		self::assertSame('*/4 * * * *', (string) (new Schedule())->everyFourMinutes());
	}

	public function testEveryFiveMinutes(): void
	{
		self::assertSame('*/5 * * * *', (string) (new Schedule())->everyFiveMinutes());
	}

	public function testEveryTenMinutes(): void
	{
		self::assertSame('*/10 * * * *', (string) (new Schedule())->everyTenMinutes());
	}

	public function testEveryFifteenMinutes(): void
	{
		self::assertSame('*/15 * * * *', (string) (new Schedule())->everyFifteenMinutes());
	}

	public function testEveryThirtyMinutesDefault(): void
	{
		self::assertSame('*/30 * * * *', (string) (new Schedule())->everyThirtyMinutes());
	}

	public function testEveryThirtyMinutesAtFixedSlots(): void
	{
		self::assertSame('0,30 * * * *', (string) (new Schedule())->everyThirtyMinutes(true));
	}

	public function testEveryHour(): void
	{
		self::assertSame('0 * * * *', (string) (new Schedule())->everyHour());
	}

	public function testEveryHourAtSingleOffset(): void
	{
		self::assertSame('15 * * * *', (string) (new Schedule())->everyHourAt(15));
	}

	public function testEveryHourAtMultipleOffsets(): void
	{
		self::assertSame('0,30 * * * *', (string) (new Schedule())->everyHourAt([0, 30]));
	}

	public function testEveryTwoHours(): void
	{
		self::assertSame('0 */2 * * *', (string) (new Schedule())->everyTwoHours());
	}

	public function testEverySixHours(): void
	{
		self::assertSame('0 */6 * * *', (string) (new Schedule())->everySixHours());
	}

	public function testEveryOddHour(): void
	{
		self::assertSame('0 1-23/2 * * *', (string) (new Schedule())->everyOddHour());
	}

	public function testDaily(): void
	{
		self::assertSame('0 0 * * *', (string) (new Schedule())->daily());
	}

	public function testDailyAt(): void
	{
		self::assertSame('30 10 * * *', (string) (new Schedule())->dailyAt('10:30'));
	}

	public function testDailyAtHourOnly(): void
	{
		// Single segment -> minute defaults to '0'
		self::assertSame('0 8 * * *', (string) (new Schedule())->dailyAt('8'));
	}

	public function testAt(): void
	{
		// at() is an alias for dailyAt()
		self::assertSame('0 14 * * *', (string) (new Schedule())->at('14:00'));
	}

	public function testTwiceDaily(): void
	{
		self::assertSame('0 1,13 * * *', (string) (new Schedule())->twiceDaily());
	}

	public function testTwiceDailyAt(): void
	{
		self::assertSame('15 2,14 * * *', (string) (new Schedule())->twiceDailyAt(2, 14, 15));
	}

	public function testWeekdays(): void
	{
		self::assertSame('* * * * 1-5', (string) (new Schedule())->weekdays());
	}

	public function testWeekends(): void
	{
		self::assertSame('* * * * 6,0', (string) (new Schedule())->weekends());
	}

	public function testMondaysExpression(): void
	{
		self::assertSame('* * * * 1', (string) (new Schedule())->mondays());
	}

	public function testFridaysExpression(): void
	{
		self::assertSame('* * * * 5', (string) (new Schedule())->fridays());
	}

	public function testWeekly(): void
	{
		self::assertSame('0 0 * * 0', (string) (new Schedule())->weekly());
	}

	public function testWeeklyOn(): void
	{
		self::assertSame('30 10 * * 2', (string) (new Schedule())->weeklyOn(Schedule::TUESDAY, '10:30'));
	}

	public function testMonthly(): void
	{
		self::assertSame('0 0 1 * *', (string) (new Schedule())->monthly());
	}

	public function testMonthlyOn(): void
	{
		self::assertSame('0 9 15 * *', (string) (new Schedule())->monthlyOn(15, '9:0'));
	}

	public function testTwiceMonthly(): void
	{
		self::assertSame('0 0 1,16 * *', (string) (new Schedule())->twiceMonthly());
	}

	public function testQuarterly(): void
	{
		self::assertSame('0 0 1 1-12/3 *', (string) (new Schedule())->quarterly());
	}

	public function testQuarterlyOn(): void
	{
		self::assertSame('30 8 5 1-12/3 *', (string) (new Schedule())->quarterlyOn(5, '8:30'));
	}

	public function testYearly(): void
	{
		self::assertSame('0 0 1 1 *', (string) (new Schedule())->yearly());
	}

	public function testYearlyOn(): void
	{
		self::assertSame('0 10 15 3 *', (string) (new Schedule())->yearlyOn(3, 15, '10:0'));
	}

	public function testDaysArray(): void
	{
		self::assertSame('* * * * 1,3,5', (string) (new Schedule())->days([1, 3, 5]));
	}

	public function testDaysVariadic(): void
	{
		self::assertSame('* * * * 1,3,5', (string) (new Schedule())->days(1, 3, 5));
	}

	public function testLastDayOfMonthExpression(): void
	{
		// Day-of-month is clamped to 28-31; runtime predicate checks the actual last day.
		self::assertSame('0 0 28-31 * *', (string) (new Schedule())->lastDayOfMonth());
	}

	public function testLastDayOfMonthCustomTime(): void
	{
		self::assertSame('30 22 28-31 * *', (string) (new Schedule())->lastDayOfMonth('22:30'));
	}

	public function testLastDayOfMonthShouldRunMatchesToday(): void
	{
		$s = (new Schedule())->lastDayOfMonth();
		// Schedule defaults to UTC; compute expected value against the same timezone.
		$now    = new DateTime('now', new DateTimeZone('UTC'));
		$isLast = (int) $now->format('j') === (int) $now->format('t');

		self::assertSame($isLast, $s->shouldRun());
	}

	public function testWildcardIsDueForCurrentTime(): void
	{
		$s = new Schedule('* * * * *');

		self::assertTrue($s->isDue(\time()));
	}

	public function testNonMatchingExpressionIsNotDue(): void
	{
		// 31st of February never exists -> never due.
		$s = new Schedule('0 0 31 2 *');

		self::assertFalse($s->isDue((new DateTime('2025-06-15 00:00:00'))->getTimestamp()));
	}

	public function testGetNextRunTimeForEveryMinuteSchedule(): void
	{
		// Use a UTC minute boundary so +1 minute is exactly +60 s.
		$base = (int) (new DateTime('2025-01-15 10:00:00', new DateTimeZone('UTC')))->getTimestamp();

		self::assertSame($base + 60, (new Schedule())->getNextRunTime($base));
	}

	public function testShouldRunWithNoPredicatesIsAlwaysTrue(): void
	{
		self::assertTrue((new Schedule())->shouldRun());
	}

	/**
	 * A window covering the entire day must always yield shouldRun() = true.
	 * This also verifies that the window is evaluated at call time (lazy), not
	 * at the time between() was configured.
	 */
	public function testBetweenAlwaysInsideWindowReturnsTrue(): void
	{
		$s = (new Schedule())->between('00:00', '23:59');

		self::assertTrue($s->shouldRun());
	}

	/**
	 * notBetween() over a full-day window must always yield shouldRun() = false.
	 */
	public function testNotBetweenAlwaysInsideWindowReturnsFalse(): void
	{
		$s = (new Schedule())->notBetween('00:00', '23:59');

		self::assertFalse($s->shouldRun());
	}

	/**
	 * between() and notBetween() with the same window must be exact inverses.
	 */
	public function testBetweenAndNotBetweenAreInverses(): void
	{
		$window = '08:00';
		$end    = '18:00';

		$inWindow  = (new Schedule())->between($window, $end)->shouldRun();
		$outWindow = (new Schedule())->notBetween($window, $end)->shouldRun();

		self::assertNotSame($inWindow, $outWindow);
	}

	/**
	 * Multiple between() calls act as AND - all must be satisfied.
	 */
	public function testMultipleBetweenCallsAreConjunctive(): void
	{
		// First is always true, second is always false -> overall false.
		$s = (new Schedule())
			->between('00:00', '23:59')
			->notBetween('00:00', '23:59');

		self::assertFalse($s->shouldRun());
	}

	public function testTimezoneReturnsSelf(): void
	{
		$s = new Schedule();

		self::assertSame($s, $s->timezone('UTC'));
	}

	// -------------------------------------------------------------------------
	// skipIfLate() tests
	// -------------------------------------------------------------------------

	public function testSkipIfLateReturnsSelf(): void
	{
		$s = new Schedule();

		self::assertSame($s, $s->skipIfLate());
	}

	/**
	 * everyMinute() is always due exactly 60 seconds ago. With grace=1 min,
	 * 60 <= 60 so the schedule should still run.
	 */
	public function testSkipIfLateWithEveryMinuteRunsWithinGrace(): void
	{
		$s = (new Schedule())->everyMinute()->skipIfLate(1);

		self::assertTrue($s->shouldRun());
	}

	/**
	 * With grace=0 minutes, any non-zero elapsed time exceeds the grace window.
	 * everyMinute() last fired 60 s ago, 60 > 0 -> the schedule must be skipped.
	 */
	public function testSkipIfLateWithEveryMinuteSkipsWhenGraceExceeded(): void
	{
		$s = (new Schedule())->everyMinute()->skipIfLate(0);

		self::assertFalse($s->shouldRun());
	}

	/**
	 * When the cron expression is never satisfiable (e.g. Feb 31), no previous
	 * slot is ever found. skipIfLate() should fall through and allow the run.
	 */
	public function testSkipIfLateAlwaysRunsWhenNoPreviousSlotFound(): void
	{
		// '0 0 31 2 *' -- Feb 31 never exists -> isDue() never true
		$s = (new Schedule('0 0 31 2 *'))->skipIfLate(0);

		self::assertTrue($s->shouldRun());
	}
}
