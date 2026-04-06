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

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Class RequestFormHeadersTest.
 *
 * Tests for {@see Request::isFormDiscoveryRequest()} and
 * {@see Request::isFormResumeRequest()}.
 *
 * @internal
 *
 * @coversNothing
 */
final class RequestFormHeadersTest extends TestCase
{
	private static string $discoveryHeaderName;

	private static string $resumeHeaderName;

	public static function setUpBeforeClass(): void
	{
		self::$discoveryHeaderName = Settings::get('oz.request', 'OZ_FORM_DISCOVERY_HEADER_NAME');
		self::$resumeHeaderName    = Settings::get('oz.request', 'OZ_FORM_RESUME_HEADER_NAME');
	}

	// -----------------------------------------------------------------------
	// isFormDiscoveryRequest()
	// -----------------------------------------------------------------------

	public function testIsFormDiscoveryReturnsFalseWhenSettingDisabled(): void
	{
		Settings::set('oz.request', 'OZ_FORM_DISCOVERY_HEADER_ALLOWED', false);

		try {
			$request = $this->makeRequest([self::serverKey(self::$discoveryHeaderName) => '?1']);
			self::assertFalse($request->isFormDiscoveryRequest());
		} finally {
			Settings::unset('oz.request', 'OZ_FORM_DISCOVERY_HEADER_ALLOWED');
		}
	}

	public function testIsFormDiscoveryReturnsFalseWhenHeaderAbsent(): void
	{
		$request = $this->makeRequest();
		self::assertFalse($request->isFormDiscoveryRequest());
	}

	public function testIsFormDiscoveryReturnsTrueWhenHeaderIsTrue(): void
	{
		$request = $this->makeRequest([self::serverKey(self::$discoveryHeaderName) => '?1']);
		self::assertTrue($request->isFormDiscoveryRequest());
	}

	public function testIsFormDiscoveryReturnsFalseWhenHeaderIsFalse(): void
	{
		$request = $this->makeRequest([self::serverKey(self::$discoveryHeaderName) => '?0']);
		self::assertFalse($request->isFormDiscoveryRequest());
	}

	// -----------------------------------------------------------------------
	// isFormResumeRequest()
	// -----------------------------------------------------------------------

	public function testIsFormResumeReturnsFalseWhenHeaderAbsent(): void
	{
		$request = $this->makeRequest();
		self::assertFalse($request->isFormResumeRequest());
	}

	public function testIsFormResumeReturnsTrueWhenHeaderIsTrue(): void
	{
		$request = $this->makeRequest([self::serverKey(self::$resumeHeaderName) => '?1']);
		self::assertTrue($request->isFormResumeRequest());
	}

	public function testIsFormResumeReturnsFalseWhenHeaderIsFalse(): void
	{
		$request = $this->makeRequest([self::serverKey(self::$resumeHeaderName) => '?0']);
		self::assertFalse($request->isFormResumeRequest());
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Converts a header name like "X-OZONE-Form-Discovery" to its $_SERVER key
	 * equivalent like "HTTP_X_OZONE_FORM_DISCOVERY".
	 */
	private static function serverKey(string $headerName): string
	{
		return 'HTTP_' . \strtoupper(\str_replace('-', '_', $headerName));
	}

	/**
	 * Creates a sub-context from the given environment and returns its Request.
	 */
	private function makeRequest(array $env = []): Request
	{
		$context = new Context(HTTPEnvironment::mock($env), null, Context::root());

		return $context->getRequest();
	}
}
