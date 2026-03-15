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

use OZONE\Core\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Class ResponseTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class ResponseTest extends TestCase
{
	public function testDefaultStatusIs200(): void
	{
		$r = new Response();
		self::assertSame(200, $r->getStatusCode());
		self::assertSame('OK', $r->getReasonPhrase());
	}

	public function testWithStatusReturnsNewInstanceWithNewStatus(): void
	{
		$r  = new Response();
		$r2 = $r->withStatus(404);

		self::assertNotSame($r, $r2);
		self::assertSame(200, $r->getStatusCode());
		self::assertSame(404, $r2->getStatusCode());
		self::assertSame('Not Found', $r2->getReasonPhrase());
	}

	public function testWithStatusAcceptsCustomReasonPhrase(): void
	{
		$r = (new Response())->withStatus(418, "I'm a teapot");
		self::assertSame(418, $r->getStatusCode());
		self::assertSame("I'm a teapot", $r->getReasonPhrase());
	}

	public function testIsOkReturnsTrueFor200(): void
	{
		self::assertTrue((new Response(200))->isOk());
		self::assertFalse((new Response(201))->isOk());
	}

	public function testIsSuccessfulReturnsTrueFor2xxRange(): void
	{
		self::assertTrue((new Response(200))->isSuccessful());
		self::assertTrue((new Response(201))->isSuccessful());
		self::assertTrue((new Response(204))->isSuccessful());
		self::assertFalse((new Response(301))->isSuccessful());
		self::assertFalse((new Response(404))->isSuccessful());
	}

	public function testIsRedirectReturnsTrueFor301And302(): void
	{
		self::assertTrue((new Response(301))->isRedirect());
		self::assertTrue((new Response(302))->isRedirect());
	}

	public function testIsNotFoundReturnsTrueFor404(): void
	{
		self::assertTrue((new Response(404))->isNotFound());
		self::assertFalse((new Response(200))->isNotFound());
	}

	public function testIsForbiddenReturnsTrueFor403(): void
	{
		self::assertTrue((new Response(403))->isForbidden());
	}

	public function testIsClientErrorReturnsTrueFor4xxRange(): void
	{
		self::assertTrue((new Response(400))->isClientError());
		self::assertTrue((new Response(404))->isClientError());
		self::assertTrue((new Response(422))->isClientError());
		self::assertFalse((new Response(500))->isClientError());
	}

	public function testIsServerErrorReturnsTrueFor5xxRange(): void
	{
		self::assertTrue((new Response(500))->isServerError());
		self::assertTrue((new Response(503))->isServerError());
		self::assertFalse((new Response(200))->isServerError());
	}

	public function testIsEmptyReturnsTrueFor204(): void
	{
		self::assertTrue((new Response(204))->isEmpty());
	}

	public function testWithJsonSetsJsonBody(): void
	{
		$data = ['key' => 'value', 'count' => 3];
		$r    = (new Response())->withJson($data);

		self::assertSame('application/json;charset=utf-8', $r->getHeaderLine('Content-Type'));
		self::assertSame(\json_encode($data), (string) $r->getBody());
	}

	public function testWriteAppendsToBody(): void
	{
		$r = new Response();
		$r->write('Hello');
		$r->write(' World');
		self::assertSame('Hello World', (string) $r->getBody());
	}

	public function testWithRedirectSetsLocationHeader(): void
	{
		$r = (new Response())->withRedirect('https://example.com/redirect');
		self::assertSame('https://example.com/redirect', $r->getHeaderLine('Location'));
		self::assertSame(302, $r->getStatusCode());
	}

	public function testWithRedirectAcceptsCustomStatus(): void
	{
		$r = (new Response())->withRedirect('https://example.com/', 301);
		self::assertSame(301, $r->getStatusCode());
	}

	public function testStatusToReasonPhraseReturnsKnownPhrase(): void
	{
		self::assertSame('OK', Response::statusToReasonPhrase(200));
		self::assertSame('Not Found', Response::statusToReasonPhrase(404));
		self::assertSame('Internal Server Error', Response::statusToReasonPhrase(500));
	}

	public function testStatusToReasonPhraseReturnsNullForUnknown(): void
	{
		self::assertNull(Response::statusToReasonPhrase(999));
	}

	public function testStatusToReasonPhraseCoversAllKnownCodes(): void
	{
		$known = [
			100,
			101,
			102,
			200,
			201,
			202,
			203,
			204,
			205,
			206,
			207,
			208,
			226,
			300,
			301,
			302,
			303,
			304,
			307,
			308,
			400,
			401,
			402,
			403,
			404,
			405,
			406,
			408,
			409,
			410,
			411,
			412,
			413,
			414,
			415,
			416,
			417,
			418,
			422,
			423,
			424,
			426,
			428,
			429,
			431,
			451,
			500,
			501,
			502,
			503,
			504,
			505,
			507,
			508,
			510,
			511,
		];

		foreach ($known as $code) {
			self::assertNotNull(
				Response::statusToReasonPhrase($code),
				"Expected reason phrase for status {$code}"
			);
		}
	}
}
