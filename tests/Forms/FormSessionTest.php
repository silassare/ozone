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

namespace OZONE\Tests\Forms;

use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormDataClean;
use OZONE\Core\Forms\Resume\AbstractResumableFormProvider;
use OZONE\Core\Forms\Resume\Enums\FormResumePhase;
use OZONE\Core\Forms\Resume\FormResumeProgress;
use OZONE\Core\Forms\Resume\FormSession;
use OZONE\Core\Http\Enums\RequestScope;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Class FormSessionTest.
 *
 * Tests for the {@see FormSession} record: cache round-trip and the provider /
 * route binding enforced by {@see FormSession::assertUsableBy()}.
 *
 * @internal
 *
 * @covers \OZONE\Core\Forms\Resume\FormSession
 */
final class FormSessionTest extends TestCase
{
	public function testStandaloneSessionKeepsThePreviousCachedShape(): void
	{
		$data = self::session(StandaloneSessionTestProvider::class, 'wizard', null)->toArray();

		self::assertSame('wizard', $data['provider_name']);
		self::assertArrayNotHasKey('route', $data);
	}

	public function testRoundTripKeepsTheBinding(): void
	{
		$copy = FormSession::fromArray('ref1', self::session(RouteBoundSessionTestProvider::class, null, 'r|POST|/a')->toArray());

		self::assertNotNull($copy);
		self::assertSame(RouteBoundSessionTestProvider::class, $copy->provider_class);
		self::assertNull($copy->provider_name);
		self::assertSame('r|POST|/a', $copy->route);
		self::assertSame(['a' => 1], $copy->cleaned_fd->toArray());
	}

	public function testFromArrayRejectsANonProviderClass(): void
	{
		$data                   = self::session(StandaloneSessionTestProvider::class, 'wizard', null)->toArray();
		$data['provider_class'] = stdClass::class;

		self::assertNull(FormSession::fromArray('ref1', $data));
	}

	public function testSessionOfAnotherProviderIsRejected(): void
	{
		$this->expectException(ForbiddenException::class);

		self::session(StandaloneSessionTestProvider::class, 'wizard', null)
			->assertUsableBy(RouteBoundSessionTestProvider::class, null);
	}

	public function testStandaloneSessionIsUsableWhereverItsProviderIs(): void
	{
		$session = self::session(StandaloneSessionTestProvider::class, 'wizard', null);

		$session->assertUsableBy(StandaloneSessionTestProvider::class, null);
		$session->assertUsableBy(StandaloneSessionTestProvider::class, 'r|POST|/a');

		$this->addToAssertionCount(1);
	}

	public function testRouteBoundSessionIsUsableOnItsRoute(): void
	{
		self::session(RouteBoundSessionTestProvider::class, null, 'r|POST|/a')
			->assertUsableBy(RouteBoundSessionTestProvider::class, 'r|POST|/a');

		$this->addToAssertionCount(1);
	}

	public function testRouteBoundSessionIsRejectedOnAnotherRoute(): void
	{
		$this->expectException(ForbiddenException::class);

		self::session(RouteBoundSessionTestProvider::class, null, 'r|POST|/a')
			->assertUsableBy(RouteBoundSessionTestProvider::class, 'r|POST|/b');
	}

	public function testRouteBoundSessionIsRejectedOutsideARoute(): void
	{
		$this->expectException(ForbiddenException::class);

		self::session(RouteBoundSessionTestProvider::class, null, 'r|POST|/a')
			->assertUsableBy(RouteBoundSessionTestProvider::class, null);
	}

	public function testUnboundSessionOfARealContextProviderIsRejected(): void
	{
		// Such a provider never opens standalone sessions, so an unbound one is not trusted.
		$this->expectException(ForbiddenException::class);

		self::session(RouteBoundSessionTestProvider::class, null, null)
			->assertUsableBy(RouteBoundSessionTestProvider::class, 'r|POST|/a');
	}

	/**
	 * @param class-string<AbstractResumableFormProvider> $provider_class
	 */
	private static function session(string $provider_class, ?string $provider_name, ?string $route): FormSession
	{
		return new FormSession(
			'ref1',
			$provider_class,
			$provider_name,
			$route,
			RequestScope::HOST->value,
			'scope-1',
			\time(),
			null,
			FormResumePhase::DONE,
			new FormDataClean(['a' => 1]),
			new FormResumeProgress(),
		);
	}
}

/**
 * A provider reachable through the standalone endpoints.
 *
 * @internal
 */
final class StandaloneSessionTestProvider extends AbstractResumableFormProvider
{
	public static function getName(): string
	{
		return 'test:standalone-session';
	}

	public static function requiresRealContext(): bool
	{
		return false;
	}

	public function nextStep(FormDataClean $cleaned_fd, FormResumeProgress $progress): ?Form
	{
		return null;
	}
}

/**
 * A provider that requires real context (the default), so only routes open its sessions.
 *
 * @internal
 */
final class RouteBoundSessionTestProvider extends AbstractResumableFormProvider
{
	public static function getName(): string
	{
		return 'test:route-bound-session';
	}

	public function nextStep(FormDataClean $cleaned_fd, FormResumeProgress $progress): ?Form
	{
		return null;
	}
}
