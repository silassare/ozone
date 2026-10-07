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

use OZONE\Core\Utils\Hasher;
use PHPUnit\Framework\TestCase;

/**
 * Class HasherTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Utils\Hasher
 */
final class HasherTest extends TestCase
{
	public function testHash32ReturnsA32CharHexString(): void
	{
		$hash = Hasher::hash32('hello');
		self::assertSame(32, \strlen($hash));
		self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $hash);
	}

	public function testHash32IsDeterministicForSameInput(): void
	{
		self::assertSame(Hasher::hash32('ozone'), Hasher::hash32('ozone'));
	}

	public function testHash32ProducesDifferentHashForDifferentInput(): void
	{
		self::assertNotSame(Hasher::hash32('foo'), Hasher::hash32('bar'));
	}

	/**
	 * The empty string is a string: hashing it twice gives one hash, as any other input does. It used
	 * to give a random hash, so a caller hashing a value that may be absent -- a header a client did
	 * not send -- got a different hash on every call and could never compare two of them.
	 */
	public function testHash32OfTheEmptyStringIsAHashLikeAnyOther(): void
	{
		$hash = Hasher::hash32('');

		self::assertSame(32, \strlen($hash));
		self::assertSame($hash, Hasher::hash32(''));
		self::assertNotSame($hash, Hasher::hash32('ozone'));
	}

	public function testHash64ReturnsA64CharHexString(): void
	{
		$hash = Hasher::hash64('hello');
		self::assertSame(64, \strlen($hash));
		self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
	}

	public function testHash64IsDeterministicForSameInput(): void
	{
		self::assertSame(Hasher::hash64('ozone'), Hasher::hash64('ozone'));
	}

	public function testHash64ProducesDifferentHashForDifferentInput(): void
	{
		self::assertNotSame(Hasher::hash64('foo'), Hasher::hash64('bar'));
	}

	public function testHash64OfTheEmptyStringIsAHashLikeAnyOther(): void
	{
		$hash = Hasher::hash64('');

		self::assertSame(64, \strlen($hash));
		self::assertSame($hash, Hasher::hash64(''));
		self::assertNotSame($hash, Hasher::hash64('ozone'));
	}

	public function testShortenReturnsNonEmptyStringForNonZeroCrc(): void
	{
		$short = Hasher::shorten('https://example.com/some/long/path');
		self::assertNotEmpty($short);
	}

	public function testShortenIsDeterministic(): void
	{
		$url = 'https://example.com/some/path';
		self::assertSame(Hasher::shorten($url), Hasher::shorten($url));
	}

	public function testShortenProducesDifferentResultsForDifferentInputs(): void
	{
		self::assertNotSame(Hasher::shorten('url-a'), Hasher::shorten('url-b'));
	}

	public function testShortenOutputContainsOnlyAlphanumChars(): void
	{
		$short = Hasher::shorten('https://example.com/test');
		self::assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $short);
	}
}
