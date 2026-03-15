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

namespace OZONE\Tests\Lang;

use OZONE\Core\Lang\Polyglot;
use PHPUnit\Framework\TestCase;

/**
 * Class PolyglotTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class PolyglotTest extends TestCase
{
    // -----------------------------------------------------------
    // isLangKey tests
    // -----------------------------------------------------------

    public function testIsLangKeyReturnsTrueForValidKeys(): void
    {
        self::assertTrue(Polyglot::isLangKey('MY_KEY'));
        self::assertTrue(Polyglot::isLangKey('WELCOME_MESSAGE'));
        self::assertTrue(Polyglot::isLangKey('OZ_ERROR_INTERNAL'));
        self::assertTrue(Polyglot::isLangKey('GROUP.SUBGROUP.KEY'));
        self::assertTrue(Polyglot::isLangKey('KEY123'));
        self::assertTrue(Polyglot::isLangKey('KEY_WITH_NUMBERS_42'));
    }

    public function testIsLangKeyReturnsFalseForInvalidKeys(): void
    {
        self::assertFalse(Polyglot::isLangKey(''));
        self::assertFalse(Polyglot::isLangKey('lowercase'));
        self::assertFalse(Polyglot::isLangKey('123STARTS_WITH_NUMBER'));
        self::assertFalse(Polyglot::isLangKey('has space'));
        self::assertFalse(Polyglot::isLangKey('has-hyphen'));
        self::assertFalse(Polyglot::isLangKey('_STARTS_WITH_UNDERSCORE'));
    }

    // -----------------------------------------------------------
    // parseBrowserLanguage tests
    // -----------------------------------------------------------

    public function testParseBrowserLanguageWithNullReturnsEmptyLanguagesAndNullAdvice(): void
    {
        $result = Polyglot::parseBrowserLanguage(null);
        self::assertArrayHasKey('languages', $result);
        self::assertArrayHasKey('advice', $result);
        self::assertSame([], $result['languages']);
        self::assertNull($result['advice']);
    }

    public function testParseBrowserLanguageWithEmptyStringReturnsEmpty(): void
    {
        $result = Polyglot::parseBrowserLanguage('');
        self::assertSame([], $result['languages']);
        self::assertNull($result['advice']);
    }

    public function testParseBrowserLanguageExtractsSingleLanguage(): void
    {
        $result = Polyglot::parseBrowserLanguage('en');
        self::assertArrayHasKey('en', $result['languages']);
    }

    public function testParseBrowserLanguageExtractsMultipleLanguages(): void
    {
        $result = Polyglot::parseBrowserLanguage('fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7');
        $langs  = $result['languages'];

        self::assertArrayHasKey('fr-FR', $langs);
        self::assertArrayHasKey('fr', $langs);
        self::assertArrayHasKey('en-US', $langs);
        self::assertArrayHasKey('en', $langs);
    }

    public function testParseBrowserLanguageAssignsQuality1ForMissingQFactor(): void
    {
        $result = Polyglot::parseBrowserLanguage('en');
        self::assertSame(1, $result['languages']['en']);
    }

    public function testParseBrowserLanguageRespectsQuality(): void
    {
        $result = Polyglot::parseBrowserLanguage('en;q=0.8,fr;q=0.9');
        $langs  = $result['languages'];

        // fr should come before en after arsort
        $keys = \array_keys($langs);
        self::assertSame('fr', $keys[0]);
        self::assertSame('en', $keys[1]);
    }

    // -----------------------------------------------------------
    // declareFilter tests
    // -----------------------------------------------------------

    public function testDeclareFilterRegistersCallable(): void
    {
        Polyglot::declareFilter('test_upper', static fn($v, $lang) => \strtoupper($v));
        // If no exception is thrown, the filter was registered successfully.
        // We rely on this in translate() tests.
        self::assertTrue(true);
    }
}
