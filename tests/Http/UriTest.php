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

use InvalidArgumentException;
use OZONE\Core\Http\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class UriTest.
 *
 * @internal
 */
#[CoversClass(Uri::class)]
final class UriTest extends TestCase
{
	// =========================================================================
	// What a browser would do, and what no parser should have to guess
	// =========================================================================

	/**
	 * The host is the one a client sent here would connect to.
	 *
	 * Taking the authority apart by hand reads `http://evil.com\@good.com/` as a request to
	 * `good.com`, because a backslash is not a delimiter it knows; a browser treats it as a
	 * slash and goes to `evil.com`. An allow-list decision made on the first answer protects
	 * the wrong host, so the parse has to agree with the client.
	 */
	#[DataProvider('provideTheHostIsTheOneAClientWouldReachCases')]
	public function testTheHostIsTheOneAClientWouldReach(string $url, string $host): void
	{
		self::assertSame($host, Uri::createFromString($url)->getHost());
	}

	/**
	 * @return iterable<string, array{0: string, 1: string}>
	 */
	public static function provideTheHostIsTheOneAClientWouldReachCases(): iterable
	{
		return [
			'a backslash before the at sign' => ['http://evil.com\@good.com/', 'evil.com'],
			'a tab inside the host'          => ["http://evil.com\t.good.com/", 'evil.com.good.com'],
			'an upper case host'             => ['HTTPS://EXAMPLE.COM/A', 'example.com'],
			'a host with no scheme'          => ['//evil.com/p', 'evil.com'],
		];
	}

	/**
	 * A malformed URL is refused, where every component used to default and leave the root.
	 */
	#[DataProvider('provideAMalformedUrlIsRefusedCases')]
	public function testAMalformedUrlIsRefused(string $url): void
	{
		$this->expectException(InvalidArgumentException::class);

		Uri::createFromString($url);
	}

	/**
	 * @return iterable<string, array{0: string}>
	 */
	public static function provideAMalformedUrlIsRefusedCases(): iterable
	{
		return [
			'a port that is not a number' => ['http://host:80x/p'],
			'a space in the host'         => ['https://exa mple.com/p'],
			'a port above the range'      => ['https://example.com:99999/p'],
			'a scheme and no host'        => ['https://'],
		];
	}

	/**
	 * A scheme outside {@see Uri::SCHEMES} is refused: these are scripts and local files, not
	 * addresses to follow.
	 */
	#[DataProvider('provideASchemeOutsideTheListIsRefusedCases')]
	public function testASchemeOutsideTheListIsRefused(string $url): void
	{
		$this->expectException(InvalidArgumentException::class);

		Uri::createFromString($url);
	}

	/**
	 * @return iterable<string, array{0: string}>
	 */
	public static function provideASchemeOutsideTheListIsRefusedCases(): iterable
	{
		return [
			'javascript' => ['javascript://comment%0Aalert(1)'],
			'data'       => ['data:text/html,<script>alert(1)</script>'],
			'file'       => ['file:///etc/passwd'],
		];
	}

	/**
	 * One origin spelled two ways compares equal: the default port is dropped and an
	 * internationalised host is in the ASCII form that goes on the wire.
	 */
	public function testOneOriginHasOneSpelling(): void
	{
		self::assertNull(Uri::createFromString('https://example.com:443/p')->getPort());
		self::assertNull(Uri::createFromString('http://example.com:80/p')->getPort());
		self::assertSame(8080, Uri::createFromString('https://example.com:8080/p')->getPort());
		self::assertSame('xn--r8jz45g.jp', Uri::createFromString('http://例え.jp/p')->getHost());
		self::assertSame('/b', Uri::createFromString('https://example.com/a/../b')->getPath());
	}

	/**
	 * A per cent sign that is not an escape is left as it is, instead of being encoded a second
	 * time: `/%zz` used to come back as `/%25zz`, a different path from the one requested.
	 */
	public function testAPerCentThatIsNotAnEscapeIsNotEncodedAgain(): void
	{
		self::assertSame('/%zz', Uri::createFromString('https://example.com/%zz')->getPath());
		self::assertSame('/a%20b', Uri::createFromString('https://example.com/a%20b')->getPath());
	}

	/**
	 * A reference with no scheme keeps its parts: a redirect to a path stays a path.
	 */
	public function testAReferenceWithNoSchemeKeepsItsParts(): void
	{
		$uri = Uri::createFromString('/next');
		self::assertSame('', $uri->getScheme());
		self::assertSame('', $uri->getHost());
		self::assertSame('/next', $uri->getPath());

		self::assertSame('q=1', Uri::createFromString('?q=1')->getQuery());
		self::assertSame('f', Uri::createFromString('#f')->getFragment());
	}

	/**
	 * What a stored value is asked, which is not what a browser is asked: a URL whose meaning
	 * depends on which parser reads it is refused rather than normalised into one reading.
	 */
	#[DataProvider('provideIsStrictUrlCases')]
	public function testIsStrictUrl(string $url, bool $accepted): void
	{
		self::assertSame($accepted, Uri::isStrictUrl($url));
	}

	/**
	 * @return iterable<string, array{0: string, 1: bool}>
	 */
	public static function provideIsStrictUrlCases(): iterable
	{
		return [
			'an ordinary url'            => ['https://example.com/p', true],
			'an upper case scheme'       => ['HTTPS://EXAMPLE.COM/A', true],
			'a javascript url'           => ['javascript://comment%0Aalert(1)', false],
			'a data url'                 => ['data://text/html;base64,x', false],
			'another scheme'             => ['ftp://example.com/f', false],
			'a stray per cent'           => ['http://example.com/%zz', false],
			'a unicode host'             => ['http://例え.jp/p', false],
			'a path on its own'          => ['/just/a/path', false],
			'an ambiguous authority'     => ['http://evil.com\@good.com/', false],
		];
	}

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
