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

use DateTime;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Parses and evaluates 5-field cron expressions.
 *
 * Fields (positional): minute hour day-of-month month day-of-week
 *
 * Supported syntax per field:
 *   *          - any value
 *   n          - exact value
 *   n,m,...    - comma-separated list
 *   n-m        - inclusive range
 *   * /n       - step over the full field range (no space in actual expression)
 *   n-m/step   - range with step
 *   n/step     - from n, every step, up to field max
 *
 * Month field also accepts: JAN FEB MAR APR MAY JUN JUL AUG SEP OCT NOV DEC
 * Day-of-week field also accepts: SUN MON TUE WED THU FRI SAT (0 and 7 both = Sunday)
 *
 * Day-of-month + day-of-week follow traditional Unix OR semantics:
 * when both are restricted (non-wildcard), a minute matches if either field matches.
 */
final class CronExpression
{
    // Field positions
    private const MINUTE       = 0;

    private const HOUR         = 1;

    private const DAY_OF_MONTH = 2;

    private const MONTH        = 3;

    private const DAY_OF_WEEK  = 4;

    // Allowed [min, max] per field
    private const RANGES = [
        self::MINUTE       => [0, 59],
        self::HOUR         => [0, 23],
        self::DAY_OF_MONTH => [1, 31],
        self::MONTH        => [1, 12],
        self::DAY_OF_WEEK  => [0, 7],
    ];

    // Standard Unix named-value aliases (case-insensitive)
    private const MONTH_NAMES = [
        'jan' => 1,
        'feb' => 2,
        'mar' => 3,
        'apr' => 4,
        'may' => 5,
        'jun' => 6,
        'jul' => 7,
        'aug' => 8,
        'sep' => 9,
        'oct' => 10,
        'nov' => 11,
        'dec' => 12,
    ];

    private const DOW_NAMES = [
        'sun' => 0,
        'mon' => 1,
        'tue' => 2,
        'wed' => 3,
        'thu' => 4,
        'fri' => 5,
        'sat' => 6,
    ];

    private string $expression;

    /** @var string[] */
    private array $fields;

    /**
     * CronExpression constructor.
     *
     * @param string $expression 5-field cron expression
     */
    public function __construct(string $expression)
    {
        $expression = \trim($expression);
        $fields     = (array) \preg_split('/\s+/', $expression);

        if (5 !== \count($fields)) {
            throw new RuntimeException(\sprintf(
                'Invalid cron expression "%s": expected 5 fields, got %d.',
                $expression,
                \count($fields)
            ));
        }

        $this->expression = $expression;
        $this->fields     = $fields;

        // Resolve named aliases so all downstream matching is purely numeric.
        $this->fields[self::MONTH]       = $this->resolveNames($fields[self::MONTH], self::MONTH_NAMES);
        $this->fields[self::DAY_OF_WEEK] = $this->resolveNames($fields[self::DAY_OF_WEEK], self::DOW_NAMES);
    }

    /**
     * Check whether the expression is due at the given DateTime.
     *
     * @param DateTime $dt
     *
     * @return bool
     */
    public function isDue(DateTime $dt): bool
    {
        $minute     = (int) $dt->format('i');
        $hour       = (int) $dt->format('G');
        $dayOfMonth = (int) $dt->format('j');
        $month      = (int) $dt->format('n');
        $dayOfWeek  = (int) $dt->format('w'); // 0 = Sunday

        if (!$this->matchesField(self::MINUTE, $minute)) {
            return false;
        }

        if (!$this->matchesField(self::HOUR, $hour)) {
            return false;
        }

        if (!$this->matchesField(self::MONTH, $month)) {
            return false;
        }

        // Day matching: if both day-of-month and day-of-week are restricted (not *), use OR logic.
        // Matches if either the day-of-month OR the day-of-week matches (traditional Unix cron behavior).
        $domField = $this->fields[self::DAY_OF_MONTH];
        $dowField = $this->fields[self::DAY_OF_WEEK];
        $domWild  = '*' === $domField || '?' === $domField;
        $dowWild  = '*' === $dowField || '?' === $dowField;

        if ($domWild && $dowWild) {
            // both wildcards - always matches
        } elseif ($domWild) {
            if (!$this->matchesField(self::DAY_OF_WEEK, $dayOfWeek)) {
                return false;
            }
        } elseif ($dowWild) {
            if (!$this->matchesField(self::DAY_OF_MONTH, $dayOfMonth)) {
                return false;
            }
        } else {
            // Both restricted: OR logic
            $domMatch = $this->matchesField(self::DAY_OF_MONTH, $dayOfMonth);
            $dowMatch = $this->matchesField(self::DAY_OF_WEEK, $dayOfWeek);

            if (!$domMatch && !$dowMatch) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the next DateTime at which the expression will be due, after $from.
     *
     * @param DateTime $from start point (exclusive)
     *
     * @return DateTime
     */
    public function getNextRunDate(DateTime $from): DateTime
    {
        $dt = clone $from;

        // Start from the next whole minute.
        $dt->modify('+1 minute');
        $dt->setTime((int) $dt->format('G'), (int) $dt->format('i'), 0);

        $limit = clone $from;
        $limit->modify('+4 years');

        while ($dt <= $limit) {
            if ($this->isDue($dt)) {
                return $dt;
            }

            $dt->modify('+1 minute');
        }

        throw new RuntimeException(\sprintf(
            'Unable to determine next run date for cron expression "%s".',
            $this->expression
        ));
    }

    /**
     * Check whether a value matches a single cron field expression.
     *
     * @param int $position field position constant
     * @param int $value    actual time component value
     *
     * @return bool
     */
    private function matchesField(int $position, int $value): bool
    {
        $field       = $this->fields[$position];
        [$min, $max] = self::RANGES[$position];

        // Normalize Sunday: day-of-week 7 is equivalent to 0.
        if (self::DAY_OF_WEEK === $position && 7 === $value) {
            $value = 0;
        }

        if ('*' === $field || '?' === $field) {
            return true;
        }

        foreach (\explode(',', $field) as $part) {
            if ($this->matchesPart($part, $value, $min, $max, $position)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a value matches one part of a comma-separated field (range, step, or literal).
     *
     * @param string $part     the field part to evaluate
     * @param int    $value    actual time component value
     * @param int    $min      minimum allowed value for the field
     * @param int    $max      maximum allowed value for the field
     * @param int    $position field position constant
     *
     * @return bool
     */
    private function matchesPart(string $part, int $value, int $min, int $max, int $position): bool
    {
        // Normalize 7 -> 0 for Sunday, but only within the day-of-week field.
        if (self::DAY_OF_WEEK === $position) {
            $part = (string) \preg_replace('/(?<![0-9])7(?![0-9])/', '0', $part);
        }

        if (\str_contains($part, '/')) {
            [$range, $stepStr] = \explode('/', $part, 2);
            $step = (int) $stepStr;

            if ($step < 1) {
                return false;
            }

            if ('*' === $range) {
                $rangeMin = $min;
                $rangeMax = $max;
            } elseif (\str_contains($range, '-')) {
                [$rangeMin, $rangeMax] = \array_map('intval', \explode('-', $range, 2));
            } else {
                $rangeMin = (int) $range;
                $rangeMax = $max;
            }

            if ($value < $rangeMin || $value > $rangeMax) {
                return false;
            }

            return 0 === ($value - $rangeMin) % $step;
        }

        if (\str_contains($part, '-')) {
            [$start, $end] = \array_map('intval', \explode('-', $part, 2));

            return $value >= $start && $value <= $end;
        }

        return $value === (int) $part;
    }

    /**
     * Replace named aliases (e.g. JAN, SUN) with their numeric equivalents in a field string.
     *
     * Works transparently within all field syntaxes: literals, ranges, steps, and comma-separated lists.
     *
     * @param string             $field
     * @param array<string, int> $aliases lowercase-name -> int map
     *
     * @return string
     */
    private function resolveNames(string $field, array $aliases): string
    {
        return (string) \preg_replace_callback(
            '/[a-zA-Z]+/',
            static function (array $m) use ($aliases): string {
                $key = \strtolower($m[0]);

                return isset($aliases[$key]) ? (string) $aliases[$key] : $m[0];
            },
            $field
        );
    }
}
