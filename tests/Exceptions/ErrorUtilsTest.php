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

namespace OZONE\Tests\Exceptions;

use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\Utils\ErrorUtils;
use OZONE\Core\OZone;
use PHPUnit\Framework\TestCase;

/**
 * Class ErrorUtilsTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Exceptions\Utils\ErrorUtils
 */
final class ErrorUtilsTest extends TestCase
{
	protected function setUp(): void
	{
		Settings::set('oz.logs', 'OZ_REDACT_SENSITIVE_DATA', true);
	}

	protected function tearDown(): void
	{
		Settings::unset('oz.logs', 'OZ_REDACT_SENSITIVE_DATA');
	}

	public function testRedactionCanBeDisabled(): void
	{
		Settings::set('oz.logs', 'OZ_REDACT_SENSITIVE_DATA', false);

		self::assertFalse(ErrorUtils::isRedactionEnabled());
		self::assertSame(['api_key' => 'k'], ErrorUtils::redactSensitiveData(['api_key' => 'k']));
	}

	public function testRedactionDefaultsToProductionMode(): void
	{
		Settings::unset('oz.logs', 'OZ_REDACT_SENSITIVE_DATA');

		self::assertSame(OZone::inProductionMode(), ErrorUtils::isRedactionEnabled());
	}

	public function testRedactsSecretLookingKeysRecursively(): void
	{
		$data = [
			'_token'  => 'abc',
			'field'   => 'email',
			'api_key' => 'k',
			'nested'  => [
				'password' => 'p',
				'pass_new' => 'n',
				'ok'       => 1,
			],
			0         => 'kept',
		];

		self::assertSame([
			'_token'  => ErrorUtils::REDACTED,
			'field'   => 'email',
			'api_key' => ErrorUtils::REDACTED,
			'nested'  => [
				'password' => ErrorUtils::REDACTED,
				'pass_new' => ErrorUtils::REDACTED,
				'ok'       => 1,
			],
			0         => 'kept',
		], ErrorUtils::redactSensitiveData($data));
	}

	public function testLoggedExceptionTextHasNoSecret(): void
	{
		$e = new ForbiddenException(null, ['_reason' => 'r', '_api_key' => 'super-secret-key']);

		self::assertStringNotContainsString('super-secret-key', ForbiddenException::throwableToString($e));
	}
}
