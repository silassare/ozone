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

use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\AbstractResumableFormProvider;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Http\Enums\RequestScope;
use PHPUnit\Framework\TestCase;

/**
 * Class ResumableFormProviderTest.
 *
 * Tests for {@see AbstractResumableFormProvider}: registry, resolution, and defaults.
 *
 * @internal
 *
 * @coversNothing
 */
final class ResumableFormProviderTest extends TestCase
{
	protected function setUp(): void
	{
		AbstractResumableFormProvider::clearRegistry();
	}

	protected function tearDown(): void
	{
		AbstractResumableFormProvider::clearRegistry();
	}

	// -----------------------------------------------------------------------
	// register + resolve
	// -----------------------------------------------------------------------

	public function testRegisterAndResolveReturnsInstance(): void
	{
		AbstractResumableFormProvider::register(SimpleTestProvider::class);

		$provider = AbstractResumableFormProvider::resolve('test:simple');

		self::assertInstanceOf(SimpleTestProvider::class, $provider);
	}

	public function testResolveUnknownRefThrowsNotFoundException(): void
	{
		$this->expectException(NotFoundException::class);

		AbstractResumableFormProvider::resolve('test:nonexistent');
	}

	public function testRegisterSameClassTwiceIsIdempotent(): void
	{
		AbstractResumableFormProvider::register(SimpleTestProvider::class);
		AbstractResumableFormProvider::register(SimpleTestProvider::class); // must not throw

		$provider = AbstractResumableFormProvider::resolve('test:simple');

		self::assertInstanceOf(SimpleTestProvider::class, $provider);
	}

	public function testRegisterDifferentClassUnderSameRefThrowsRuntimeException(): void
	{
		AbstractResumableFormProvider::register(SimpleTestProvider::class);

		$this->expectException(RuntimeException::class);

		AbstractResumableFormProvider::register(ConflictingTestProvider::class);
	}

	// -----------------------------------------------------------------------
	// Defaults
	// -----------------------------------------------------------------------

	public function testDefaultInitFormIsNull(): void
	{
		self::assertNull((new SimpleTestProvider())->initForm());
	}

	public function testDefaultResumeTTLIs3600(): void
	{
		self::assertSame(3600, (new SimpleTestProvider())->resumeTTL());
	}

	public function testDefaultResumeScopeIsState(): void
	{
		self::assertSame(RequestScope::STATE, (new SimpleTestProvider())->resumeScope());
	}

	public function testResumeScopeCanBeOverridden(): void
	{
		self::assertSame(RequestScope::HOST, (new HostScopedTestProvider())->resumeScope());
	}

	// -----------------------------------------------------------------------
	// clearRegistry (test isolation utility)
	// -----------------------------------------------------------------------

	public function testClearRegistryMakesProviderUnresolvable(): void
	{
		AbstractResumableFormProvider::register(SimpleTestProvider::class);
		AbstractResumableFormProvider::clearRegistry();

		$this->expectException(NotFoundException::class);

		AbstractResumableFormProvider::resolve('test:simple');
	}
}

// ---------------------------------------------------------------------------
// Inline test-only provider fixtures
// ---------------------------------------------------------------------------

/**
 * Minimal provider: no init form, returns one step, then done.
 *
 * @internal
 */
final class SimpleTestProvider extends AbstractResumableFormProvider
{
	public static function providerRef(): string
	{
		return 'test:simple';
	}

	public function nextStep(FormData $progress): ?Form
	{
		if ($progress->has('step1_done')) {
			return null;
		}

		$form = new Form();
		$form->field('step1_done'); // optional — validates with empty data

		return $form;
	}
}

/**
 * Provider with same providerRef as SimpleTestProvider — used to test
 * duplicate-registration conflict detection.
 *
 * @internal
 */
final class ConflictingTestProvider extends AbstractResumableFormProvider
{
	public static function providerRef(): string
	{
		return 'test:simple'; // deliberately conflicts with SimpleTestProvider
	}

	public function nextStep(FormData $progress): ?Form
	{
		return null;
	}
}

/**
 * Provider that overrides resumeScope to HOST — useful for unit tests that
 * cannot rely on a stateful auth context.
 *
 * @internal
 */
final class HostScopedTestProvider extends AbstractResumableFormProvider
{
	public static function providerRef(): string
	{
		return 'test:host-scoped';
	}

	public function resumeScope(): RequestScope
	{
		return RequestScope::HOST;
	}

	public function nextStep(FormData $progress): ?Form
	{
		return null;
	}
}
