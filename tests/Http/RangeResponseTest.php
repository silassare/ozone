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

use OZONE\Core\Http\Body;
use OZONE\Core\Http\Headers;
use OZONE\Core\Http\RangeResponse;
use OZONE\Core\Http\Request;
use OZONE\Core\Http\RequestBody;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\Uri;
use PHPUnit\Framework\TestCase;

/**
 * Class RangeResponseTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class RangeResponseTest extends TestCase
{
	public function testNon200ResponseIsReturnedUnchanged(): void
	{
		$request  = self::makeRequest('GET', ['Range' => 'bytes=0-4']);
		$response = (new Response())->withStatus(404);
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(404, $result->getStatusCode());
	}

	public function testPlainGetReturnsOriginal200(): void
	{
		$request  = self::makeRequest('GET');
		$response = self::makeResponse('Hello World');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(200, $result->getStatusCode());
		self::assertSame('Hello World', (string) $result->getBody());
	}

	public function testIfNoneMatchExactEtagReturns304(): void
	{
		$request  = self::makeRequest('GET', ['If-None-Match' => '"abc123"']);
		$response = self::makeResponse('Hello World', 'abc123');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(304, $result->getStatusCode());
		self::assertSame('', (string) $result->getBody());
		self::assertFalse($result->hasHeader('Content-Length'));
		self::assertFalse($result->hasHeader('Content-Type'));
	}

	public function testIfNoneMatchWildcardReturns304(): void
	{
		$request  = self::makeRequest('GET', ['If-None-Match' => '*']);
		$response = self::makeResponse('data', 'any-etag');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(304, $result->getStatusCode());
	}

	public function testIfNoneMatchWeakEtagReturns304(): void
	{
		$request  = self::makeRequest('GET', ['If-None-Match' => 'W/"abc"']);
		$response = self::makeResponse('data', 'abc');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(304, $result->getStatusCode());
	}

	public function testIfNoneMatchListContainingEtagReturns304(): void
	{
		$request  = self::makeRequest('GET', ['If-None-Match' => '"other", "abc123", "more"']);
		$response = self::makeResponse('data', 'abc123');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(304, $result->getStatusCode());
	}

	public function testIfNoneMatchMismatchReturns200(): void
	{
		$request  = self::makeRequest('GET', ['If-None-Match' => '"different"']);
		$response = self::makeResponse('data', 'abc123');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(200, $result->getStatusCode());
	}

	public function testIfNoneMatchOnHeadReturns304(): void
	{
		$request  = self::makeRequest('HEAD', ['If-None-Match' => '"abc"']);
		$response = self::makeResponse('data', 'abc');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(304, $result->getStatusCode());
	}

	public function testIfNoneMatchOnPostIsIgnored(): void
	{
		// Conditional requests are only honoured for GET / HEAD.
		$request  = self::makeRequest('POST', ['If-None-Match' => '*']);
		$response = self::makeResponse('data', 'abc');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(200, $result->getStatusCode());
	}

	public function testRangeExplicitStartAndEndReturns206(): void
	{
		// "Hello World" -> bytes 0-4 = "Hello"
		$request  = self::makeRequest('GET', ['Range' => 'bytes=0-4']);
		$response = self::makeResponse('Hello World');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(206, $result->getStatusCode());
		self::assertSame('bytes 0-4/11', $result->getHeaderLine('Content-Range'));
		self::assertSame('5', $result->getHeaderLine('Content-Length'));

		// Verify the body is seeked to the right offset; reading 5 bytes gives "Hello".
		$body = $result->getBody();
		self::assertSame('Hello', $body->read(5));
	}

	public function testRangeOpenEndedReturns206(): void
	{
		// "Hello World" -> bytes=6- = "World"
		$request  = self::makeRequest('GET', ['Range' => 'bytes=6-']);
		$response = self::makeResponse('Hello World');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(206, $result->getStatusCode());
		self::assertSame('bytes 6-10/11', $result->getHeaderLine('Content-Range'));
		self::assertSame('5', $result->getHeaderLine('Content-Length'));

		$body = $result->getBody();
		self::assertSame('World', $body->read(5));
	}

	public function testRangeSuffixReturns206(): void
	{
		// "Hello World" -> bytes=-5 = last 5 bytes = "World"
		$request  = self::makeRequest('GET', ['Range' => 'bytes=-5']);
		$response = self::makeResponse('Hello World');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(206, $result->getStatusCode());
		self::assertSame('bytes 6-10/11', $result->getHeaderLine('Content-Range'));
		self::assertSame('5', $result->getHeaderLine('Content-Length'));

		$body = $result->getBody();
		self::assertSame('World', $body->read(5));
	}

	public function testRangeSuffixLargerThanFileSizeClampedToStart(): void
	{
		// "Hi" (2 bytes), bytes=-100 -> start=0
		$request  = self::makeRequest('GET', ['Range' => 'bytes=-100']);
		$response = self::makeResponse('Hi');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(206, $result->getStatusCode());
		self::assertSame('bytes 0-1/2', $result->getHeaderLine('Content-Range'));
		self::assertSame('2', $result->getHeaderLine('Content-Length'));
	}

	public function testRangeEndClampedToLastByte(): void
	{
		// "Hello" (5 bytes), bytes=2-999 -> end capped at 4
		$request  = self::makeRequest('GET', ['Range' => 'bytes=2-999']);
		$response = self::makeResponse('Hello');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(206, $result->getStatusCode());
		self::assertSame('bytes 2-4/5', $result->getHeaderLine('Content-Range'));
		self::assertSame('3', $result->getHeaderLine('Content-Length'));

		$body = $result->getBody();
		self::assertSame('llo', $body->read(3));
	}

	public function testRangeSingleByteReturns206(): void
	{
		$request  = self::makeRequest('GET', ['Range' => 'bytes=3-3']);
		$response = self::makeResponse('Hello');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(206, $result->getStatusCode());
		self::assertSame('bytes 3-3/5', $result->getHeaderLine('Content-Range'));
		self::assertSame('1', $result->getHeaderLine('Content-Length'));

		$body = $result->getBody();
		self::assertSame('l', $body->read(1));
	}

	public function testRangeStartBeyondSizeReturns416(): void
	{
		$request  = self::makeRequest('GET', ['Range' => 'bytes=100-200']);
		$response = self::makeResponse('Hello');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(416, $result->getStatusCode());
		self::assertSame('bytes */5', $result->getHeaderLine('Content-Range'));
		self::assertFalse($result->hasHeader('Content-Length'));
		self::assertSame('', (string) $result->getBody());
	}

	public function testRangeStartGreaterThanEndReturns416(): void
	{
		$request  = self::makeRequest('GET', ['Range' => 'bytes=5-2']);
		$response = self::makeResponse('Hello');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(416, $result->getStatusCode());
	}

	public function testMultiRangeReturns416(): void
	{
		// Multi-range requests are not supported -> treat as unsatisfiable.
		$request  = self::makeRequest('GET', ['Range' => 'bytes=0-1,3-4']);
		$response = self::makeResponse('Hello');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(416, $result->getStatusCode());
	}

	public function testRangeSuffixZeroReturns416(): void
	{
		// bytes=-0 is meaningless.
		$request  = self::makeRequest('GET', ['Range' => 'bytes=-0']);
		$response = self::makeResponse('Hello');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(416, $result->getStatusCode());
	}

	public function testEmptyRangeSpecifierReturns416(): void
	{
		// bytes=- with both parts missing is invalid.
		$request  = self::makeRequest('GET', ['Range' => 'bytes=-']);
		$response = self::makeResponse('Hello');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(416, $result->getStatusCode());
	}

	public function testRangeHeaderOnPostIsIgnored(): void
	{
		$request  = self::makeRequest('POST', ['Range' => 'bytes=0-4']);
		$response = self::makeResponse('Hello World');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(200, $result->getStatusCode());
	}

	public function testRangeHeaderOnHeadIsIgnored(): void
	{
		$request  = self::makeRequest('HEAD', ['Range' => 'bytes=0-4']);
		$response = self::makeResponse('Hello World');
		$result   = RangeResponse::apply($request, $response);

		self::assertSame(200, $result->getStatusCode());
	}

	public function testRangeWithoutContentLengthReturnedUnchanged(): void
	{
		$request  = self::makeRequest('GET', ['Range' => 'bytes=0-4']);
		$body     = Body::fromString('Hello World');
		$response = (new Response())
			->withHeader('Accept-Ranges', 'bytes')
			// Intentionally no Content-Length header.
			->withBody($body);
		$result = RangeResponse::apply($request, $response);

		self::assertSame(200, $result->getStatusCode());
	}

	public function testConditionalCheckTakesPriorityOverRangeHeader(): void
	{
		$request = self::makeRequest('GET', [
			'If-None-Match' => '"abc"',
			'Range'         => 'bytes=0-4',
		]);
		$response = self::makeResponse('Hello World', 'abc');
		$result   = RangeResponse::apply($request, $response);

		// ETag matched -> 304, range is not evaluated.
		self::assertSame(304, $result->getStatusCode());
	}

	public function testEtagForSameFileIsDeterministic(): void
	{
		$id      = '42';
		$size    = 1234;
		$ts      = '1700000000';
		$etag    = \hash('xxh64', $id . ':' . $size . ':' . $ts);
		$etag2   = \hash('xxh64', $id . ':' . $size . ':' . $ts);

		self::assertSame($etag, $etag2);
		self::assertNotEmpty($etag);
	}

	public function testEtagChangesWhenSizeChanges(): void
	{
		$id    = '42';
		$ts    = '1700000000';
		$etag  = \hash('xxh64', $id . ':' . 1234 . ':' . $ts);
		$etag2 = \hash('xxh64', $id . ':' . 5678 . ':' . $ts);

		self::assertNotSame($etag, $etag2);
	}

	public function testEtagChangesWhenTimestampChanges(): void
	{
		$id    = '42';
		$size  = 1234;
		$etag  = \hash('xxh64', $id . ':' . $size . ':1700000000');
		$etag2 = \hash('xxh64', $id . ':' . $size . ':1700001000');

		self::assertNotSame($etag, $etag2);
	}

	/**
	 * Build a minimal GET or HEAD request, optionally with extra headers.
	 *
	 * @param string               $method  HTTP method
	 * @param array<string, mixed> $headers header name => value pairs
	 *
	 * @return Request
	 */
	private static function makeRequest(string $method = 'GET', array $headers = []): Request
	{
		$h = new Headers();

		foreach ($headers as $name => $value) {
			$h->set($name, [$value]);
		}

		return new Request(
			$method,
			Uri::createFromString('http://localhost/file.bin'),
			$h,
			[],
			[],
			new RequestBody(),
		);
	}

	/**
	 * Build a 200 response with the given body content, ETag, and Content-Length.
	 *
	 * @param string      $content full body content
	 * @param null|string $etag    optional ETag value (without quotes)
	 *
	 * @return Response
	 */
	private static function makeResponse(string $content, ?string $etag = null): Response
	{
		$body = Body::fromString($content);
		$r    = (new Response())
			->withHeader('Accept-Ranges', 'bytes')
			->withHeader('Content-Length', (string) \strlen($content))
			->withBody($body);

		if (null !== $etag) {
			$r = $r->withHeader('ETag', '"' . $etag . '"');
		}

		return $r;
	}
}
