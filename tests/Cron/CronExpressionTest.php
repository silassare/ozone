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
use OZONE\Core\Cli\Cron\CronExpression;
use OZONE\Core\Exceptions\RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Class CronExpressionTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class CronExpressionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constructor / validation
    // -------------------------------------------------------------------------

    public function testConstructorRejectsFewerThanFiveFields(): void
    {
        $this->expectException(RuntimeException::class);
        new CronExpression('* * * *');
    }

    public function testConstructorRejectsMoreThanFiveFields(): void
    {
        $this->expectException(RuntimeException::class);
        new CronExpression('* * * * * *');
    }

    public function testConstructorAcceptsValidExpression(): void
    {
        $expr = new CronExpression('* * * * *');
        $this->assertInstanceOf(CronExpression::class, $expr);
    }

    // -------------------------------------------------------------------------
    // isDue - wildcard
    // -------------------------------------------------------------------------

    public function testWildcardMatchesEveryMinute(): void
    {
        $expr = new CronExpression('* * * * *');

        // Any arbitrary timestamps should all be due.
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:37:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-01-01 00:00:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-12-31 23:59:00')));
    }

    // -------------------------------------------------------------------------
    // isDue - exact minute/hour
    // -------------------------------------------------------------------------

    public function testExactMinuteMatch(): void
    {
        $expr = new CronExpression('30 * * * *');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:30:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 14:29:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 14:31:00')));
    }

    public function testExactHourMatch(): void
    {
        $expr = new CronExpression('0 9 * * *');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 09:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 10:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 09:01:00')));
    }

    // -------------------------------------------------------------------------
    // isDue - comma-separated lists
    // -------------------------------------------------------------------------

    public function testMinuteList(): void
    {
        $expr = new CronExpression('0,15,30,45 * * * *');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:00:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:15:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:30:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:45:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 14:01:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 14:46:00')));
    }

    // -------------------------------------------------------------------------
    // isDue - ranges
    // -------------------------------------------------------------------------

    public function testHourRange(): void
    {
        $expr = new CronExpression('0 9-17 * * *');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 09:00:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 13:00:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 17:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 08:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 18:00:00')));
    }

    // -------------------------------------------------------------------------
    // isDue - step syntax
    // -------------------------------------------------------------------------

    public function testStepEveryFiveMinutes(): void
    {
        $expr = new CronExpression('*/5 * * * *');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:00:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:05:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:55:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 14:01:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 14:04:00')));
    }

    public function testStepEveryTwoHours(): void
    {
        $expr = new CronExpression('0 */2 * * *');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 00:00:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 02:00:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 22:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 01:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 03:00:00')));
    }

    public function testRangeWithStep(): void
    {
        $expr = new CronExpression('10-50/10 * * * *');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:10:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:20:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:50:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 14:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 14:55:00')));
    }

    public function testStepFromN(): void
    {
        // 5/15 -> 5, 20, 35, 50
        $expr = new CronExpression('5/15 * * * *');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:05:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:20:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:35:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 14:50:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 14:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 14:04:00')));
    }

    // -------------------------------------------------------------------------
    // isDue - day-of-month
    // -------------------------------------------------------------------------

    public function testDayOfMonthExact(): void
    {
        $expr = new CronExpression('0 0 15 * *');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 00:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-14 00:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-16 00:00:00')));
    }

    // -------------------------------------------------------------------------
    // isDue - month
    // -------------------------------------------------------------------------

    public function testMonthExact(): void
    {
        $expr = new CronExpression('0 0 1 6 *');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-01 00:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-05-01 00:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-07-01 00:00:00')));
    }

    // -------------------------------------------------------------------------
    // isDue - day-of-week
    // -------------------------------------------------------------------------

    public function testDayOfWeekMonday(): void
    {
        // 2025-06-16 is a Monday (dow=1)
        $expr = new CronExpression('0 0 * * 1');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-16 00:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-15 00:00:00'))); // Sunday
        $this->assertFalse($expr->isDue(new DateTime('2025-06-17 00:00:00'))); // Tuesday
    }

    public function testSundayCanBeZeroOrSeven(): void
    {
        // 2025-06-15 is a Sunday
        $exprZero  = new CronExpression('0 0 * * 0');
        $exprSeven = new CronExpression('0 0 * * 7');

        $this->assertTrue($exprZero->isDue(new DateTime('2025-06-15 00:00:00')));
        $this->assertTrue($exprSeven->isDue(new DateTime('2025-06-15 00:00:00')));
    }

    // -------------------------------------------------------------------------
    // isDue - named month aliases
    // -------------------------------------------------------------------------

    public function testNamedMonths(): void
    {
        $expr = new CronExpression('0 0 1 JAN *');

        $this->assertTrue($expr->isDue(new DateTime('2025-01-01 00:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-02-01 00:00:00')));
    }

    public function testNamedMonthRange(): void
    {
        $expr = new CronExpression('0 0 1 JUN-AUG *');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-01 00:00:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-07-01 00:00:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-08-01 00:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-05-01 00:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-09-01 00:00:00')));
    }

    public function testNamedMonthList(): void
    {
        $expr = new CronExpression('0 0 1 JAN,JUL,DEC *');

        $this->assertTrue($expr->isDue(new DateTime('2025-01-01 00:00:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-07-01 00:00:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-12-01 00:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-02-01 00:00:00')));
    }

    // -------------------------------------------------------------------------
    // isDue - named day-of-week aliases
    // -------------------------------------------------------------------------

    public function testNamedDayOfWeek(): void
    {
        // 2025-06-16 is Monday
        $expr = new CronExpression('0 0 * * MON');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-16 00:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-17 00:00:00'))); // Tuesday
    }

    public function testNamedDayOfWeekSun(): void
    {
        // 2025-06-15 is Sunday
        $expr = new CronExpression('0 0 * * SUN');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 00:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-16 00:00:00'))); // Monday
    }

    public function testNamedDayOfWeekRange(): void
    {
        $expr = new CronExpression('0 0 * * MON-FRI');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-16 00:00:00')));  // Monday
        $this->assertTrue($expr->isDue(new DateTime('2025-06-20 00:00:00')));  // Friday
        $this->assertFalse($expr->isDue(new DateTime('2025-06-21 00:00:00'))); // Saturday
        $this->assertFalse($expr->isDue(new DateTime('2025-06-22 00:00:00'))); // Sunday
    }

    // -------------------------------------------------------------------------
    // isDue - DOM/DOW OR logic
    // -------------------------------------------------------------------------

    public function testDomAndDowOrLogic(): void
    {
        // Run on day 15 OR on Mondays
        // 2025-06-15 is a Sunday -> matches dom=15
        // 2025-06-16 is a Monday -> matches dow=1
        // 2025-06-17 is a Tuesday, dom != 15 -> no match
        $expr = new CronExpression('0 0 15 * 1');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 00:00:00')));  // dom match
        $this->assertTrue($expr->isDue(new DateTime('2025-06-16 00:00:00')));  // dow match
        $this->assertFalse($expr->isDue(new DateTime('2025-06-17 00:00:00'))); // neither
    }

    public function testDomWildcardWithDow(): void
    {
        // * in DOM -> only DOW is checked
        $expr = new CronExpression('0 0 * * 3'); // Wednesdays

        // 2025-06-18 is Wednesday
        $this->assertTrue($expr->isDue(new DateTime('2025-06-18 00:00:00')));
        $this->assertFalse($expr->isDue(new DateTime('2025-06-17 00:00:00'))); // Tuesday
    }

    // -------------------------------------------------------------------------
    // isDue - question-mark wildcard (?)
    // -------------------------------------------------------------------------

    public function testQuestionMarkActsAsWildcard(): void
    {
        $expr = new CronExpression('0 0 ? * ?');

        $this->assertTrue($expr->isDue(new DateTime('2025-06-15 00:00:00')));
        $this->assertTrue($expr->isDue(new DateTime('2025-01-01 00:00:00')));
    }

    // -------------------------------------------------------------------------
    // isDue - case-insensitivity of named aliases
    // -------------------------------------------------------------------------

    public function testNamedAliasesAreCaseInsensitive(): void
    {
        $lower  = new CronExpression('0 0 * * mon');
        $upper  = new CronExpression('0 0 * * MON');
        $mixed  = new CronExpression('0 0 * * Mon');

        // 2025-06-16 is Monday
        $dt = new DateTime('2025-06-16 00:00:00');
        $this->assertTrue($lower->isDue($dt));
        $this->assertTrue($upper->isDue($dt));
        $this->assertTrue($mixed->isDue($dt));
    }

    // -------------------------------------------------------------------------
    // getNextRunDate
    // -------------------------------------------------------------------------

    public function testNextRunDateEveryMinute(): void
    {
        $expr = new CronExpression('* * * * *');
        $from = new DateTime('2025-06-15 14:00:00');
        $next = $expr->getNextRunDate($from);

        $this->assertSame('2025-06-15 14:01:00', $next->format('Y-m-d H:i:s'));
    }

    public function testNextRunDateHourly(): void
    {
        $expr = new CronExpression('0 * * * *');
        $from = new DateTime('2025-06-15 14:30:00');
        $next = $expr->getNextRunDate($from);

        $this->assertSame('2025-06-15 15:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function testNextRunDateDaily(): void
    {
        $expr = new CronExpression('0 0 * * *');
        $from = new DateTime('2025-06-15 00:01:00');
        $next = $expr->getNextRunDate($from);

        $this->assertSame('2025-06-16 00:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function testNextRunDateAcrossMidnight(): void
    {
        $expr = new CronExpression('0 3 * * *');
        $from = new DateTime('2025-06-15 03:01:00');
        $next = $expr->getNextRunDate($from);

        $this->assertSame('2025-06-16 03:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function testNextRunDateIsExclusive(): void
    {
        // $from is exactly on a trigger minute - next run should be the following one
        $expr = new CronExpression('*/5 * * * *');
        $from = new DateTime('2025-06-15 14:00:00');
        $next = $expr->getNextRunDate($from);

        $this->assertSame('2025-06-15 14:05:00', $next->format('Y-m-d H:i:s'));
    }

    public function testNextRunDateWithNamedMonth(): void
    {
        $expr = new CronExpression('0 0 1 JAN *');
        $from = new DateTime('2025-01-01 00:01:00');
        $next = $expr->getNextRunDate($from);

        $this->assertSame('2026-01-01 00:00:00', $next->format('Y-m-d H:i:s'));
    }
}
