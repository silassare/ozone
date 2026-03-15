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

namespace OZONE\Tests\CRUD;

use OZONE\Core\CRUD\AllowCheckResult;
use OZONE\Core\Lang\I18nMessage;
use PHPUnit\Framework\TestCase;

/**
 * Class AllowCheckResultTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class AllowCheckResultTest extends TestCase
{
    public function testAllowCreatesAllowedResult(): void
    {
        $reason = new I18nMessage('ALLOWED_KEY');
        $result = AllowCheckResult::allow($reason);

        self::assertTrue($result->isAllowed());
        self::assertSame($reason, $result->getReason());
    }

    public function testRejectCreatesRejectedResult(): void
    {
        $reason = new I18nMessage('DENIED_KEY');
        $result = AllowCheckResult::reject($reason);

        self::assertFalse($result->isAllowed());
        self::assertSame($reason, $result->getReason());
    }

    public function testConstructorSetsAllowedAndReason(): void
    {
        $reason = new I18nMessage('CAUSE');
        $result = new AllowCheckResult(true, $reason);

        self::assertTrue($result->isAllowed());
        self::assertSame($reason, $result->getReason());
    }

    public function testToArrayAllowedSnippet(): void
    {
        $result = AllowCheckResult::allow(new I18nMessage('ACCESS_GRANTED'));
        $arr    = $result->toArray();
        self::assertTrue($arr['allowed']);
        self::assertEquals(new I18nMessage('ACCESS_GRANTED'), $arr['reason']);
    }

    public function testToArrayRejectedSnippet(): void
    {
        $result = AllowCheckResult::reject(new I18nMessage('ACCESS_DENIED', ['resource' => 'posts']));
        $arr    = $result->toArray();
        self::assertFalse($arr['allowed']);
        self::assertEquals(new I18nMessage('ACCESS_DENIED', ['resource' => 'posts']), $arr['reason']);
    }
}
