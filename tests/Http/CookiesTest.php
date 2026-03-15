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

namespace OZONE\Tests\Http;

use OZONE\Core\Http\Cookie;
use OZONE\Core\Http\Cookies;
use PHPUnit\Framework\TestCase;

/**
 * Class CookiesTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class CookiesTest extends TestCase
{
    // -----------------------------------------------------------
    // Cookies (collection) tests
    // -----------------------------------------------------------

    public function testGetRequestCookiesReturnsInitialCookies(): void
    {
        $cookies = new Cookies(['session' => 'abc123', 'lang' => 'fr']);
        self::assertSame(['session' => 'abc123', 'lang' => 'fr'], $cookies->getRequestCookies());
    }

    public function testGetRequestCookieReturnsCookieValue(): void
    {
        $cookies = new Cookies(['user' => 'john']);
        self::assertSame('john', $cookies->getRequestCookie('user'));
        self::assertNull($cookies->getRequestCookie('missing'));
    }

    public function testAddAndGetResponseCookie(): void
    {
        $cookie  = new Cookie('my_cookie', 'cookie_value');
        $cookies = new Cookies();
        $cookies->add($cookie);

        self::assertSame($cookie, $cookies->get('my_cookie'));
        self::assertNull($cookies->get('other'));
    }

    public function testGetAllReturnsAllResponseCookies(): void
    {
        $a = new Cookie('a', '1');
        $b = new Cookie('b', '2');

        $cookies = new Cookies();
        $cookies->add($a)->add($b);

        $all = $cookies->getAll();
        self::assertCount(2, $all);
        self::assertArrayHasKey('a', $all);
        self::assertArrayHasKey('b', $all);
    }

    public function testToResponseHeadersReturnsSetCookieStrings(): void
    {
        $cookie  = new Cookie('session', 'tok123');
        $cookies = new Cookies();
        $cookies->add($cookie);

        $headers = $cookies->toResponseHeaders();
        self::assertCount(1, $headers);
        self::assertStringContainsString('session=tok123', $headers[0]);
    }

    public function testParseIncomingRequestCookieHeaderString(): void
    {
        $header  = 'session=abc123; lang=fr; user=john%20doe';
        $cookies = Cookies::parseIncomingRequestCookieHeaderString($header);

        self::assertSame('abc123', $cookies['session']);
        self::assertSame('fr', $cookies['lang']);
        self::assertSame('john doe', $cookies['user']);
    }

    public function testParseIncomingRequestCookieHandlesEmptyHeader(): void
    {
        $cookies = Cookies::parseIncomingRequestCookieHeaderString('');
        self::assertSame([], $cookies);
    }

    public function testParseIncomingRequestCookieIgnoresMalformedPairs(): void
    {
        $header  = 'valid=ok; noequals; also=good';
        $cookies = Cookies::parseIncomingRequestCookieHeaderString($header);

        self::assertArrayHasKey('valid', $cookies);
        self::assertArrayHasKey('also', $cookies);
        self::assertArrayNotHasKey('noequals', $cookies);
    }

    public function testParseIncomingRequestCookieKeepsFirstValueForDuplicateKeys(): void
    {
        $header  = 'key=first; key=second';
        $cookies = Cookies::parseIncomingRequestCookieHeaderString($header);

        self::assertSame('first', $cookies['key']);
    }

    // -----------------------------------------------------------
    // Cookie (single) __toString snapshot tests
    // -----------------------------------------------------------

    public function testCookieToStringSimple(): void
    {
        $cookie = new Cookie('name', 'value');
        self::assertSame('name=value', (string) $cookie);
    }

    public function testCookieToStringWithDomainAndPath(): void
    {
        $cookie         = new Cookie('session', 'tok');
        $cookie->domain = 'example.com';
        $cookie->path   = '/app';
        self::assertSame('session=tok; Domain=example.com; Path=/app', (string) $cookie);
    }

    public function testCookieToStringSecureHttpOnly(): void
    {
        $cookie           = new Cookie('secure_c', 'v');
        $cookie->secure   = true;
        $cookie->httponly = true;
        self::assertSame('secure_c=v; Secure; HttpOnly', (string) $cookie);
    }

    public function testCookieToStringWithSameSite(): void
    {
        $cookie           = new Cookie('c', 'v');
        $cookie->samesite = 'Strict';
        self::assertSame('c=v; SameSite=Strict', (string) $cookie);
    }

    public function testCookieToStringWithPartitioned(): void
    {
        $cookie              = new Cookie('c', 'v');
        $cookie->partitioned = true;
        self::assertSame('c=v; Partitioned', (string) $cookie);
    }

    public function testCookieToStringWithMaxAge(): void
    {
        $cookie          = new Cookie('c', 'v');
        $cookie->max_age = 3600;
        self::assertSame('c=v; Max-Age=3600', (string) $cookie);
    }

    public function testCookieHostPrefixForcesSecureAndRootPath(): void
    {
        $cookie = new Cookie('__Host-session', 'tok');
        $str    = (string) $cookie;

        self::assertStringContainsString('Secure', $str);
        self::assertStringContainsString('Path=/', $str);
    }

    public function testCookieSecurePrefixForcesSecure(): void
    {
        $cookie = new Cookie('__Secure-id', 'val');
        $str    = (string) $cookie;

        self::assertStringContainsString('Secure', $str);
    }

    public function testDropSetsCookieValueToEmptyAndExpiresInPast(): void
    {
        $cookie          = new Cookie('session', 'tok123');
        $cookie->max_age = 3600;
        $cookie->drop();

        self::assertSame('', $cookie->value);
        self::assertSame(0, $cookie->max_age);
        self::assertLessThan(\time(), $cookie->expires);
    }
}
