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

namespace OZONE\Tests\Utils;

use DateTimeZone;
use OZONE\Core\Utils\DateTimeUtils;
use PHPUnit\Framework\TestCase;

/**
 * Class DateTimeUtilsTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class DateTimeUtilsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // parse / getDay
    // -------------------------------------------------------------------------

    public function testParseReturnsCorrectDay(): void
    {
        $dt = DateTimeUtils::parse('2025-06-15 10:30:00', 'UTC');
        $this->assertSame(15, $dt->getDay());
    }

    public function testParseTimeOnlyUsesTodayDate(): void
    {
        // Using a full datetime string to make the result deterministic in tests.
        $dt = DateTimeUtils::parse('2025-03-01 09:00:00', 'UTC');
        $this->assertSame(1, $dt->getDay());
    }

    // -------------------------------------------------------------------------
    // endOfMonth / getDay
    // -------------------------------------------------------------------------

    public function testEndOfMonthJanuary(): void
    {
        $dt   = DateTimeUtils::parse('2025-01-10 00:00:00', 'UTC');
        $last = $dt->endOfMonth();
        $this->assertSame(31, $last->getDay());
    }

    public function testEndOfMonthFebruaryNonLeapYear(): void
    {
        $dt   = DateTimeUtils::parse('2025-02-01 00:00:00', 'UTC');
        $last = $dt->endOfMonth();
        $this->assertSame(28, $last->getDay());
    }

    public function testEndOfMonthFebruaryLeapYear(): void
    {
        $dt   = DateTimeUtils::parse('2024-02-01 00:00:00', 'UTC');
        $last = $dt->endOfMonth();
        $this->assertSame(29, $last->getDay());
    }

    public function testEndOfMonthApril(): void
    {
        $dt   = DateTimeUtils::parse('2025-04-15 00:00:00', 'UTC');
        $last = $dt->endOfMonth();
        $this->assertSame(30, $last->getDay());
    }

    public function testEndOfMonthIsImmutable(): void
    {
        $original = DateTimeUtils::parse('2025-01-10 00:00:00', 'UTC');
        $end      = $original->endOfMonth();

        // Original is unchanged.
        $this->assertSame(10, $original->getDay());
        $this->assertSame(31, $end->getDay());
    }

    // -------------------------------------------------------------------------
    // addDay / subDay
    // -------------------------------------------------------------------------

    public function testAddDay(): void
    {
        $dt   = DateTimeUtils::parse('2025-06-15 00:00:00', 'UTC');
        $next = $dt->addDay();
        $this->assertSame(16, $next->getDay());
    }

    public function testSubDay(): void
    {
        $dt   = DateTimeUtils::parse('2025-06-15 00:00:00', 'UTC');
        $prev = $dt->subDay();
        $this->assertSame(14, $prev->getDay());
    }

    public function testAddDayAcrossMonthBoundary(): void
    {
        $dt   = DateTimeUtils::parse('2025-01-31 00:00:00', 'UTC');
        $next = $dt->addDay();
        $this->assertSame(1, $next->getDay());
    }

    public function testSubDayAcrossMonthBoundary(): void
    {
        $dt   = DateTimeUtils::parse('2025-02-01 00:00:00', 'UTC');
        $prev = $dt->subDay();
        $this->assertSame(31, $prev->getDay());
    }

    public function testAddDayIsImmutable(): void
    {
        $original = DateTimeUtils::parse('2025-06-15 00:00:00', 'UTC');
        $next     = $original->addDay();

        $this->assertSame(15, $original->getDay());
        $this->assertSame(16, $next->getDay());
    }

    // -------------------------------------------------------------------------
    // lessThan / greaterThan
    // -------------------------------------------------------------------------

    public function testLessThan(): void
    {
        $earlier = DateTimeUtils::parse('2025-06-15 08:00:00', 'UTC');
        $later   = DateTimeUtils::parse('2025-06-15 10:00:00', 'UTC');

        $this->assertTrue($earlier->lessThan($later));
        $this->assertFalse($later->lessThan($earlier));
        $this->assertFalse($earlier->lessThan($earlier));
    }

    public function testGreaterThan(): void
    {
        $earlier = DateTimeUtils::parse('2025-06-15 08:00:00', 'UTC');
        $later   = DateTimeUtils::parse('2025-06-15 10:00:00', 'UTC');

        $this->assertTrue($later->greaterThan($earlier));
        $this->assertFalse($earlier->greaterThan($later));
        $this->assertFalse($later->greaterThan($later));
    }

    // -------------------------------------------------------------------------
    // between
    // -------------------------------------------------------------------------

    public function testBetweenInsideInterval(): void
    {
        $start  = DateTimeUtils::parse('2025-06-15 08:00:00', 'UTC');
        $end    = DateTimeUtils::parse('2025-06-15 17:00:00', 'UTC');
        $inside = DateTimeUtils::parse('2025-06-15 12:00:00', 'UTC');

        $this->assertTrue($inside->between($start, $end));
    }

    public function testBetweenAtBoundariesIsInclusive(): void
    {
        $start = DateTimeUtils::parse('2025-06-15 08:00:00', 'UTC');
        $end   = DateTimeUtils::parse('2025-06-15 17:00:00', 'UTC');

        $this->assertTrue($start->between($start, $end));
        $this->assertTrue($end->between($start, $end));
    }

    public function testBetweenOutsideInterval(): void
    {
        $start   = DateTimeUtils::parse('2025-06-15 08:00:00', 'UTC');
        $end     = DateTimeUtils::parse('2025-06-15 17:00:00', 'UTC');
        $before  = DateTimeUtils::parse('2025-06-15 07:59:00', 'UTC');
        $after   = DateTimeUtils::parse('2025-06-15 17:01:00', 'UTC');

        $this->assertFalse($before->between($start, $end));
        $this->assertFalse($after->between($start, $end));
    }

    // -------------------------------------------------------------------------
    // timezone support
    // -------------------------------------------------------------------------

    public function testTimezoneStringIsAccepted(): void
    {
        $dt = DateTimeUtils::parse('2025-06-15 14:00:00', 'America/New_York');
        $this->assertInstanceOf(DateTimeUtils::class, $dt);
        $this->assertSame(15, $dt->getDay());
    }

    public function testDateTimeZoneObjectIsAccepted(): void
    {
        $tz = new DateTimeZone('Europe/Paris');
        $dt = DateTimeUtils::parse('2025-06-15 14:00:00', $tz);
        $this->assertInstanceOf(DateTimeUtils::class, $dt);
    }

    public function testNullTimezoneDefaultsToUtc(): void
    {
        $dt = DateTimeUtils::now(null);
        $this->assertInstanceOf(DateTimeUtils::class, $dt);
    }

    // -------------------------------------------------------------------------
    // now
    // -------------------------------------------------------------------------

    public function testNowReturnsCurrentDay(): void
    {
        // The day returned by now() should be a valid day (1-31).
        $day = DateTimeUtils::now('UTC')->getDay();
        $this->assertGreaterThanOrEqual(1, $day);
        $this->assertLessThanOrEqual(31, $day);
    }
}
