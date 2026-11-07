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

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Hooks\Events\ResponseHook;
use OZONE\Core\Hooks\MainBootHookReceiver;
use OZONE\Core\Http\ContentSecurityPolicy;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class SecurityHeadersTest.
 *
 * Tests for the `OZ_SECURITY_HEADERS` and the policy `OZ_CONTENT_SECURITY_POLICY` composes,
 * both added by {@see MainBootHookReceiver::onResponse()}.
 *
 * @internal
 */
#[CoversClass(MainBootHookReceiver::class)]
#[CoversClass(ContentSecurityPolicy::class)]
final class SecurityHeadersTest extends TestCase
{
	#[Override]
	protected function tearDown(): void
	{
		Settings::unset('oz.request', 'OZ_SECURITY_HEADERS');
		Settings::unset('oz.request', 'OZ_CONTENT_SECURITY_POLICY');
	}

	public function testDefaultHeaders(): void
	{
		$response = self::respond(self::context([]));

		self::assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
		self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
		self::assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
		// Nothing is sent until a project writes a policy: which origins a page may load is a fact
		// about a project, and a policy that breaks a working page is one a project switches off.
		self::assertFalse($response->hasHeader(ContentSecurityPolicy::HEADER));
		self::assertFalse($response->hasHeader('Strict-Transport-Security'));
	}

	public function testThePolicyIsComposedFromItsDirectives(): void
	{
		Settings::set('oz.request', 'OZ_CONTENT_SECURITY_POLICY', [
			'default-src' => ["'self'"],
			'object-src'  => ["'none'"],
		]);

		self::assertSame(
			"default-src 'self'; object-src 'none'",
			self::respond(self::context([]))->getHeaderLine(ContentSecurityPolicy::HEADER)
		);
	}

	public function testTheStrictBaselineIsOneLineForAProjectToSwitchOn(): void
	{
		Settings::set('oz.request', 'OZ_CONTENT_SECURITY_POLICY', ContentSecurityPolicy::STRICT);

		self::assertSame(
			"default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';"
				. " img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none';"
				. " base-uri 'self'; form-action 'self'; frame-ancestors 'self'",
			self::respond(self::context([]))->getHeaderLine(ContentSecurityPolicy::HEADER)
		);
	}

	public function testAPolicyWrittenOutInTheHeadersIsTheHeaderAsWritten(): void
	{
		Settings::set('oz.request', 'OZ_CONTENT_SECURITY_POLICY', ['default-src' => ["'self'"]]);
		Settings::set('oz.request', 'OZ_SECURITY_HEADERS', [
			ContentSecurityPolicy::HEADER => "default-src 'none'",
		]);

		self::assertSame(
			"default-src 'none'",
			self::respond(self::context([]))->getHeaderLine(ContentSecurityPolicy::HEADER)
		);
	}

	public function testAPolicySetByTheHandlerWins(): void
	{
		Settings::set('oz.request', 'OZ_CONTENT_SECURITY_POLICY', ['default-src' => ["'self'"]]);

		$context = self::context([]);
		$context->setResponse(
			$context->getResponse()->withHeader(ContentSecurityPolicy::HEADER, "default-src 'none'")
		);

		self::assertSame(
			"default-src 'none'",
			self::respond($context)->getHeaderLine(ContentSecurityPolicy::HEADER)
		);
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

	public function testAHandlersVaryIsKeptBesideTheOrigin(): void
	{
		$context = self::context([]);
		$context->setResponse($context->getResponse()->withHeader('Vary', 'Accept'));

		$vary = self::respond($context)->getHeader('Vary');

		self::assertContains('Accept', $vary);
		self::assertContains('Origin', $vary);
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
