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

namespace OZONE\Tests\App;

use InvalidArgumentException;
use OZONE\Core\App\Context;
use OZONE\Core\App\Navigator;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\RedirectPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class NavigatorRedirectTest.
 *
 * Tests that a redirection only sends a client to a host this server allows.
 *
 * @internal
 */
#[CoversClass(Navigator::class)]
#[CoversClass(RedirectPolicy::class)]
final class NavigatorRedirectTest extends TestCase
{
	private const string SETTING = 'OZ_REDIRECT_ALLOWED_HOSTS';

	protected function tearDown(): void
	{
		Settings::set('oz.request', self::SETTING, []);
	}

	/**
	 * A destination a route took from the request is how an open redirect is built: the request's
	 * own host is the only one allowed until a project says otherwise.
	 */
	#[DataProvider('provideADestinationOnAnotherHostIsRefusedCases')]
	public function testADestinationOnAnotherHostIsRefused(string $to): void
	{
		$context = self::context();

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('OZ_REDIRECT_HOST_NOT_ALLOWED');

		$context->redirect($to);
	}

	public static function provideADestinationOnAnotherHostIsRefusedCases(): iterable
	{
		return [
			'an absolute URL'              => ['https://evil.example/p'],
			'a host a client reads apart'  => ['http://evil.example\@localhost/p'],
			'the same host on another one' => ['https://localhost.evil.example/p'],
		];
	}

	/**
	 * A reference with no host keeps the request's own, so it is never refused.
	 */
	public function testAPathIsNotADestinationToRefuse(): void
	{
		$policy = RedirectPolicy::fromContext(self::context());

		self::assertTrue($policy->allows('/next'));
		self::assertTrue($policy->allows('https://localhost/next'));
	}

	/**
	 * The policy a redirection is checked against is the one the settings describe, read against
	 * the host of the request being served.
	 */
	public function testTheSettingOpensAHost(): void
	{
		Settings::set('oz.request', self::SETTING, ['partner.example']);

		$policy = RedirectPolicy::fromContext(self::context());

		self::assertSame(['localhost', 'partner.example'], $policy->hosts());
		self::assertTrue($policy->allows('https://partner.example/p'));
		self::assertFalse($policy->allows('https://evil.example/p'));
	}

	public function testTheWildcardSettingAllowsAnyHost(): void
	{
		Settings::set('oz.request', self::SETTING, [RedirectPolicy::ANY_HOST]);

		$policy = RedirectPolicy::fromContext(self::context());

		self::assertTrue($policy->allowsAnyHost());
		self::assertTrue($policy->allows('https://evil.example/p'));
	}

	/**
	 * A destination that is not a URL used to become a request for the site root.
	 */
	public function testADestinationThatIsNotAUrlIsRefused(): void
	{
		$context = self::context();

		$this->expectException(InvalidArgumentException::class);

		$context->redirect('https://');
	}

	private static function context(): Context
	{
		return new Context(HTTPEnvironment::mock([]), null, Context::root());
	}
}
