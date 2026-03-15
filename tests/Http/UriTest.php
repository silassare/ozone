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

use OZONE\Core\Http\Uri;
use PHPUnit\Framework\TestCase;

/**
 * Class UriTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class UriTest extends TestCase
{
	public function testCreateFromStringParsesFullUri(): void
	{
		$uri = Uri::createFromString('https://user:pass@example.com:8080/path/to?foo=bar&baz=1#section');

		self::assertSame('https', $uri->getScheme());
		self::assertSame('example.com', $uri->getHost());
		self::assertSame(8080, $uri->getPort());
		self::assertSame('/path/to', $uri->getPath());
		self::assertSame('foo=bar&baz=1', $uri->getQuery());
		self::assertSame('section', $uri->getFragment());
		self::assertSame('user:pass', $uri->getUserInfo());
	}

	public function testCreateFromStringParsesSimpleUri(): void
	{
		$uri = Uri::createFromString('http://localhost/');

		self::assertSame('http', $uri->getScheme());
		self::assertSame('localhost', $uri->getHost());
		self::assertNull($uri->getPort());
		self::assertSame('/', $uri->getPath());
		self::assertSame('', $uri->getQuery());
		self::assertSame('', $uri->getFragment());
	}

	public function testToStringReconstructsUri(): void
	{
		$original = 'https://example.com/foo/bar?a=1&b=2#anchor';
		$uri      = Uri::createFromString($original);

		self::assertSame('https://example.com/foo/bar?a=1&b=2#anchor', (string) $uri);
	}

	public function testToStringWithoutOptionalParts(): void
	{
		$uri = Uri::createFromString('http://example.com/path');
		self::assertSame('http://example.com/path', (string) $uri);
	}

	public function testWithSchemeReturnsNewInstance(): void
	{
		$uri  = Uri::createFromString('http://example.com/');
		$uri2 = $uri->withScheme('https');

		self::assertNotSame($uri, $uri2);
		self::assertSame('https', $uri2->getScheme());
		self::assertSame('http', $uri->getScheme());
	}

	public function testWithHostReturnsNewInstance(): void
	{
		$uri  = Uri::createFromString('http://example.com/path');
		$uri2 = $uri->withHost('other.com');

		self::assertNotSame($uri, $uri2);
		self::assertSame('other.com', $uri2->getHost());
		self::assertSame('example.com', $uri->getHost());
	}

	public function testWithPortReturnsNewInstance(): void
	{
		$uri  = Uri::createFromString('http://example.com/');
		$uri2 = $uri->withPort(9000);

		self::assertSame(9000, $uri2->getPort());
		self::assertNull($uri->getPort());
	}

	public function testWithPortNullRemovesPort(): void
	{
		$uri  = Uri::createFromString('http://example.com:8080/');
		$uri2 = $uri->withPort(null);

		self::assertNull($uri2->getPort());
	}

	public function testWithPathReturnsNewInstance(): void
	{
		$uri  = Uri::createFromString('http://example.com/foo');
		$uri2 = $uri->withPath('/bar');

		self::assertSame('/bar', $uri2->getPath());
		self::assertSame('/foo', $uri->getPath());
	}

	public function testWithQueryReturnsNewInstance(): void
	{
		$uri  = Uri::createFromString('http://example.com/?a=1');
		$uri2 = $uri->withQuery('b=2');

		self::assertSame('b=2', $uri2->getQuery());
		self::assertSame('a=1', $uri->getQuery());
	}

	public function testWithQueryArrayBuildsQueryString(): void
	{
		$uri  = Uri::createFromString('http://example.com/');
		$uri2 = $uri->withQueryArray(['x' => '1', 'y' => '2']);

		self::assertSame('x=1&y=2', $uri2->getQuery());
	}

	public function testWithFragmentReturnsNewInstance(): void
	{
		$uri  = Uri::createFromString('http://example.com/#old');
		$uri2 = $uri->withFragment('new');

		self::assertSame('new', $uri2->getFragment());
		self::assertSame('old', $uri->getFragment());
	}

	public function testWithUserInfoSetsUserAndPassword(): void
	{
		$uri  = Uri::createFromString('http://example.com/');
		$uri2 = $uri->withUserInfo('alice', 'secret');

		self::assertSame('alice:secret', $uri2->getUserInfo());
	}

	public function testWithUserInfoWithoutPassword(): void
	{
		$uri  = Uri::createFromString('http://example.com/');
		$uri2 = $uri->withUserInfo('alice');

		self::assertSame('alice', $uri2->getUserInfo());
	}

	public function testWithBasePathSetsBasePath(): void
	{
		$uri  = Uri::createFromString('http://example.com/path');
		$uri2 = $uri->withBasePath('/api/v1');

		self::assertSame('/api/v1', $uri2->getBasePath());
	}

	public function testGetAuthorityIncludesUserInfoAndPort(): void
	{
		$uri = Uri::createFromString('https://alice:pw@example.com:8443/');
		self::assertSame('alice:pw@example.com:8443', $uri->getAuthority());
	}

	public function testGetBaseUrlExcludesPath(): void
	{
		$uri = Uri::createFromString('http://example.com:8080/path/to');
		self::assertSame('http://example.com:8080', $uri->getBaseUrl());
	}

	public function testSchemeIsNormalizedToLowercase(): void
	{
		$uri = new Uri('HTTPS', 'example.com');
		self::assertSame('https', $uri->getScheme());
	}

	public function testPathAlwaysStartsWithSlash(): void
	{
		$uri = new Uri('http', 'example.com', null, 'no-leading-slash');
		self::assertSame('/no-leading-slash', $uri->getPath());
	}
}
