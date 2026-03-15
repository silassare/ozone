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

namespace OZONE\Core\Utils;

use DateTimeImmutable;
use DateTimeZone;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Lightweight immutable date/time helper.
 */
final class DateTimeUtils
{
    private DateTimeImmutable $dt;

    private function __construct(DateTimeImmutable $dt)
    {
        $this->dt = $dt;
    }

    /**
     * Return a DateTimeUtils representing the current moment.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return self
     */
    public static function now(DateTimeZone|string|null $timezone = null): self
    {
        return new self(new DateTimeImmutable('now', self::resolveTimezone($timezone)));
    }

    /**
     * Parse a date/time string into a DateTimeUtils instance.
     *
     * @param string                   $time
     * @param DateTimeZone|string|null $timezone
     *
     * @return self
     */
    public static function parse(string $time, DateTimeZone|string|null $timezone = null): self
    {
        $tz = self::resolveTimezone($timezone);
        $dt = new DateTimeImmutable($time, $tz);

        if (false === $dt) {
            throw new RuntimeException(\sprintf('Unable to parse date/time string "%s".', $time));
        }

        return new self($dt);
    }

    /**
     * Get the day of the month (1-31).
     *
     * @return int
     */
    public function getDay(): int
    {
        return (int) $this->dt->format('j');
    }

    /**
     * Return a new instance pointing to the last day of the current month.
     *
     * @return self
     */
    public function endOfMonth(): self
    {
        // 't' returns number of days in the current month
        $lastDay = (int) $this->dt->format('t');

        return new self($this->dt->setDate(
            (int) $this->dt->format('Y'),
            (int) $this->dt->format('n'),
            $lastDay
        )->setTime(23, 59, 59));
    }

    /**
     * Return a new instance one day in the future.
     *
     * @return self
     */
    public function addDay(): self
    {
        return new self($this->dt->modify('+1 day'));
    }

    /**
     * Return a new instance one day in the past.
     *
     * @return self
     */
    public function subDay(): self
    {
        return new self($this->dt->modify('-1 day'));
    }

    /**
     * Check whether this instance is strictly before another.
     *
     * @param self $other
     *
     * @return bool
     */
    public function lessThan(self $other): bool
    {
        return $this->dt < $other->dt;
    }

    /**
     * Check whether this instance is strictly after another.
     *
     * @param self $other
     *
     * @return bool
     */
    public function greaterThan(self $other): bool
    {
        return $this->dt > $other->dt;
    }

    /**
     * Check whether this instance falls within [$start, $end] (inclusive).
     *
     * @param self $start
     * @param self $end
     *
     * @return bool
     */
    public function between(self $start, self $end): bool
    {
        return $this->dt >= $start->dt && $this->dt <= $end->dt;
    }

    /**
     * Resolve a timezone argument to a DateTimeZone instance.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return DateTimeZone
     */
    private static function resolveTimezone(DateTimeZone|string|null $timezone): DateTimeZone
    {
        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        if (\is_string($timezone)) {
            return new DateTimeZone($timezone);
        }

        return new DateTimeZone('UTC');
    }
}
