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

namespace OZONE\Tests\Hooks;

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Hooks\Events\ResponseHook;
use OZONE\Core\Hooks\MainBootHookReceiver;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Class SecurityHeadersTest.
 *
 * Tests for the `OZ_SECURITY_HEADERS` added by {@see MainBootHookReceiver::onResponse()}.
 *
 * @internal
 *
 * @covers \OZONE\Core\Hooks\MainBootHookReceiver
 */
final class SecurityHeadersTest extends TestCase
{
	protected function tearDown(): void
	{
		Settings::unset('oz.request', 'OZ_SECURITY_HEADERS');
	}

	public function testDefaultHeaders(): void
	{
		$response = self::respond(self::context([]));

		self::assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
		self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
		self::assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
		self::assertFalse($response->hasHeader('Content-Security-Policy'));
		self::assertFalse($response->hasHeader('Strict-Transport-Security'));
	}

	public function testHeaderSetByTheHandlerWins(): void
	{
		$context = self::context([]);
		$context->setResponse($context->getResponse()->withHeader('X-Frame-Options', 'DENY'));

		self::assertSame('DENY', self::respond($context)->getHeaderLine('X-Frame-Options'));
	}

	public function testHstsIsOnlySentOverHttps(): void
	{
		Settings::set('oz.request', 'OZ_SECURITY_HEADERS', ['Strict-Transport-Security' => 'max-age=60']);

		self::assertFalse(self::respond(self::context([]))->hasHeader('Strict-Transport-Security'));
		self::assertSame(
			'max-age=60',
			self::respond(self::context(['HTTPS' => 'on']))->getHeaderLine('Strict-Transport-Security')
		);
	}

	private static function respond(Context $context): Response
	{
		MainBootHookReceiver::onResponse(new ResponseHook($context));

		return $context->getResponse();
	}

	private static function context(array $env): Context
	{
		return new Context(HTTPEnvironment::mock($env), null, Context::root());
	}
}
