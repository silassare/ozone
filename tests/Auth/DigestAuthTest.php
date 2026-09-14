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

namespace OZONE\Tests\Auth;

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\LoginThrottle;
use OZONE\Core\Auth\Methods\DigestAuth;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Router\Route;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * Class DigestAuthTest.
 *
 * Digest auth throttling ({@see LoginThrottle}, per auth key) and nonces (signed, expiring,
 * single use). The auth ref is unknown, so a digest that passes every nonce check is then
 * rejected with `Invalid auth ref.`.
 *
 * @internal
 *
 * @covers \OZONE\Core\Auth\Methods\DigestAuth
 */
final class DigestAuthTest extends TestCase
{
	public const REALM = 'test';

	private const AUTH_REF = 'digest-test-unknown-ref';
	private const SUBJECT  = 'digest:' . self::AUTH_REF;

	private static Route $route;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		db()->executeMulti(db()->getGenerator()->buildDatabase());

		$router = new Router();
		$router->get('/test-digest', static fn () => null)->name('test:digest');
		self::$route = $router->getRoute('test:digest');
	}

	protected function tearDown(): void
	{
		LoginThrottle::clear(self::SUBJECT);
	}

	public function testFailedAttemptsAreCounted(): void
	{
		self::assertSame('Invalid auth ref.', self::reason(self::digest(TestDigestAuth::nonceAt(\time()))));
		self::assertSame(1, LoginThrottle::failures(self::SUBJECT));
	}

	public function testLockedAuthKeyIsRejected(): void
	{
		$max = (int) Settings::get('oz.auth', 'OZ_AUTH_LOGIN_MAX_FAILURES');

		for ($i = 0; $i < $max; ++$i) {
			LoginThrottle::recordFailure(self::SUBJECT);
		}

		self::assertSame('OZ_AUTH_TOO_MUCH_ATTEMPT', self::reason(self::digest(TestDigestAuth::nonceAt(\time()))));
		// Rejected before any check: the attempt is not counted again.
		self::assertSame($max, LoginThrottle::failures(self::SUBJECT));
	}

	public function testForgedNonceIsRejected(): void
	{
		self::assertSame('Invalid digest nonce.', self::reason(self::digest(\time() . '.00.forged')));
		self::assertSame(1, LoginThrottle::failures(self::SUBJECT));
	}

	public function testExpiredNonceGetsAStaleChallenge(): void
	{
		$lifetime = (int) Settings::get('oz.auth', 'OZ_AUTH_DIGEST_NONCE_LIFETIME');
		$method   = self::digest(TestDigestAuth::nonceAt(\time() - $lifetime - 1));
		$caught   = null;

		try {
			$method->authenticate();
		} catch (UnauthorizedException $e) {
			$caught = $e;
		}

		self::assertInstanceOf(UnauthorizedException::class, $caught);
		self::assertStringContainsString(
			'stale=true',
			(string) $caught->getCustomResponse()?->getHeaderLine('WWW-Authenticate')
		);
		self::assertSame(0, LoginThrottle::failures(self::SUBJECT));
	}

	public function testUsedNonceIsRejected(): void
	{
		$nonce = TestDigestAuth::nonceAt(\time());
		TestDigestAuth::markUsed($nonce, 1);

		self::assertSame('Digest nonce already used.', self::reason(self::digest($nonce)));
	}

	public function testRfc2617AcceptsANonceOncePerIncreasingCount(): void
	{
		$nonce = TestDigestAuth::nonceAt(\time());
		TestDigestAuth::markUsed($nonce, 1);

		self::assertSame('Digest nonce already used.', self::reason(self::digest($nonce, '00000001')));
		self::assertSame('Invalid auth ref.', self::reason(self::digest($nonce, '00000002')));
	}

	public function testUsernameWithoutAuthRefIsRejected(): void
	{
		self::assertSame(
			'Invalid username.',
			self::reason(self::digest(TestDigestAuth::nonceAt(\time()), null, 'no-auth-ref'))
		);
	}

	/**
	 * The `_reason` of the rejection of a digest.
	 */
	private static function reason(DigestAuth $method): ?string
	{
		$caught = null;

		try {
			$method->authenticate();
		} catch (ForbiddenException $e) {
			$caught = $e;
		}

		self::assertInstanceOf(ForbiddenException::class, $caught);

		return $caught->getData(true)['_reason'] ?? null;
	}

	/**
	 * A digest method whose request carries an `Authorization: Digest` header; with `$nc`,
	 * an RFC 2617 one (unquoted `nc` and `qop`, as clients send them).
	 */
	private static function digest(string $nonce, ?string $nc = null, ?string $username = null): TestDigestAuth
	{
		$header = \sprintf(
			'Digest username="%s", nonce="%s", uri="/test-digest", response="%s"',
			$username ?? 'user:' . self::AUTH_REF,
			$nonce,
			\str_repeat('0', 32)
		);

		if (null !== $nc) {
			$header .= \sprintf(', nc=%s, cnonce="c", qop=auth', $nc);
		}

		$context = new Context(HTTPEnvironment::mock(['HTTP_AUTHORIZATION' => $header]), null, Context::root());
		$method  = new TestDigestAuth(new RouteInfo($context, self::$route, []), null !== $nc);

		self::assertTrue($method->satisfied());

		return $method;
	}
}

/**
 * Exposes nonce building and the used-nonce store to the tests.
 *
 * @internal
 */
final class TestDigestAuth extends DigestAuth
{
	public function __construct(RouteInfo $ri, bool $rfc2617 = false)
	{
		parent::__construct($ri, DigestAuthTest::REALM, $rfc2617);
	}

	public static function nonceAt(int $issued_at): string
	{
		return self::buildNonce(DigestAuthTest::REALM, $issued_at);
	}

	public static function markUsed(string $nonce, int $nc): void
	{
		self::nonceStore()->set(self::nonceKey($nonce), $nc, 60);
	}
}
