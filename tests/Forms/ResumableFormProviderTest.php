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

use OZONE\Core\Forms\AbstractResumableFormProvider;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormResumeProgress;
use OZONE\Core\Http\Enums\RequestScope;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Class ResumableFormProviderTest.
 *
 * Tests for {@see AbstractResumableFormProvider}: interface defaults and instance().
 *
 * @internal
 *
 * @coversNothing
 */
final class ResumableFormProviderTest extends TestCase
{
	public function testGetNameReturnsExpectedString(): void
	{
		self::assertSame('test:simple', SimpleTestProvider::getName());
	}

	public function testNoRegistryMethodsExist(): void
	{
		self::assertFalse(\method_exists(AbstractResumableFormProvider::class, 'register'));
		self::assertFalse(\method_exists(AbstractResumableFormProvider::class, 'resolve'));
		self::assertFalse(\method_exists(AbstractResumableFormProvider::class, 'clearRegistry'));
	}

	public function testDefaultInitFormIsNull(): void
	{
		self::assertNull(SimpleTestProvider::initForm());
	}

	public function testDirectConstructionLeavesRiNull(): void
	{
		$provider = new SimpleTestProvider();

		$ri_prop = new ReflectionProperty(AbstractResumableFormProvider::class, 'ri');

		self::assertNull($ri_prop->getValue($provider));
	}

	public function testDefaultResumeTTLIs3600(): void
	{
		self::assertSame(3600, (new SimpleTestProvider())->resumeTTL());
	}

	public function testDefaultResumeScopeIsState(): void
	{
		self::assertSame(RequestScope::STATE, (new SimpleTestProvider())->resumeScope());
	}

	public function testDefaultIsReversibleIsFalse(): void
	{
		self::assertFalse((new SimpleTestProvider())->isReversible());
	}

	public function testDefaultTotalStepsIsNull(): void
	{
		self::assertNull((new SimpleTestProvider())->totalSteps());
	}

	public function testDefaultNotBeforeIsNull(): void
	{
		self::assertNull((new SimpleTestProvider())->notBefore());
	}

	public function testDefaultDeadlineIsNull(): void
	{
		self::assertNull((new SimpleTestProvider())->deadline());
	}

	public function testResumeScopeCanBeOverridden(): void
	{
		self::assertSame(RequestScope::HOST, (new HostScopedTestProvider())->resumeScope());
	}
}

/**
 * Minimal provider: no init form, returns one step at index 0, then done.
 *
 * @internal
 */
final class SimpleTestProvider extends AbstractResumableFormProvider
{
	public static function getName(): string
	{
		return 'test:simple';
	}

	public function nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form
	{
		if (0 === $progress->getStepIndex()) {
			$form = new Form();
			$form->string('answer');

			return $form;
		}

		return null;
	}
}

/**
 * Provider that overrides resumeScope to HOST.
 *
 * @internal
 */
final class HostScopedTestProvider extends AbstractResumableFormProvider
{
	public static function getName(): string
	{
		return 'test:host-scoped';
	}

	public function resumeScope(): RequestScope
	{
		return RequestScope::HOST;
	}

	public function nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form
	{
		return null;
	}
}
