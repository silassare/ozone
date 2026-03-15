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

use OZONE\Core\Http\Headers;
use PHPUnit\Framework\TestCase;

/**
 * Class HeadersTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class HeadersTest extends TestCase
{
	public function testNormalizeKeyStripsHttpPrefix(): void
	{
		$h = new Headers();
		self::assertSame('CONTENT_TYPE', $h->normalizeKey('HTTP_CONTENT_TYPE'));
		self::assertSame('ACCEPT', $h->normalizeKey('HTTP_ACCEPT'));
	}

	public function testNormalizeKeyStripsRedirectHttpPrefix(): void
	{
		$h = new Headers();
		self::assertSame('ACCEPT', $h->normalizeKey('REDIRECT_HTTP_ACCEPT'));
	}

	public function testNormalizeKeyConvertsHyphensToUnderscores(): void
	{
		$h = new Headers();
		self::assertSame('CONTENT_TYPE', $h->normalizeKey('content-type'));
		self::assertSame('X_CUSTOM_HEADER', $h->normalizeKey('X-Custom-Header'));
	}

	public function testNormalizeKeyUppercases(): void
	{
		$h = new Headers();
		self::assertSame('ACCEPT', $h->normalizeKey('accept'));
	}

	public function testSetAndGet(): void
	{
		$h = new Headers();
		$h->set('Content-Type', 'application/json');

		self::assertTrue($h->has('Content-Type'));
		self::assertTrue($h->has('content-type'));
		self::assertTrue($h->has('CONTENT_TYPE'));
		// Headers stores values as an array (multiple values per header are allowed).
		self::assertSame(['application/json'], $h->get('Content-Type'));
	}

	public function testGetOriginalKey(): void
	{
		$h = new Headers();
		$h->set('X-My-Header', 'value');
		self::assertSame('X-My-Header', $h->getOriginalKey('x-my-header'));
		self::assertSame('X-My-Header', $h->getOriginalKey('X_MY_HEADER'));
	}

	public function testGetOriginalKeyReturnsDefaultWhenAbsent(): void
	{
		$h = new Headers();
		self::assertNull($h->getOriginalKey('missing'));
		self::assertSame('default', $h->getOriginalKey('missing', 'default'));
	}

	public function testAddAppendsToExistingHeader(): void
	{
		$h = new Headers();
		$h->set('Accept', 'text/html');
		$h->add('Accept', 'application/json');

		$value = $h->get('Accept');
		self::assertIsArray($value);
		self::assertContains('text/html', $value);
		self::assertContains('application/json', $value);
	}

	public function testRemoveDeletesHeader(): void
	{
		$h = new Headers(['Content-Type' => 'text/plain']);
		$h->remove('Content-Type');
		self::assertFalse($h->has('Content-Type'));
	}

	public function testAllReturnsOriginalKeys(): void
	{
		$h   = new Headers(['Content-Type' => 'text/html']);
		$all = $h->all();
		self::assertArrayHasKey('Content-Type', $all);
	}

	public function testConstructorAcceptsInitialItems(): void
	{
		$h = new Headers([
			'Accept'       => 'application/json',
			'Content-Type' => 'text/html',
		]);
		self::assertTrue($h->has('Accept'));
		self::assertTrue($h->has('Content-Type'));
	}

	public function testGetReturnsDefaultWhenAbsent(): void
	{
		$h = new Headers();
		self::assertNull($h->get('X-Missing'));
		self::assertSame('fallback', $h->get('X-Missing', 'fallback'));
	}
}
