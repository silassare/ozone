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

namespace OZONE\Tests\Router;

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\AbstractResumableFormProvider;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormResumeProgress;
use OZONE\Core\Router\Enums\RouteFormDocPolicy;
use OZONE\Core\Router\RouteFormDeclaration;
use PHPUnit\Framework\TestCase;

/**
 * Class RouteFormDeclarationTest.
 *
 * Tests for {@see RouteFormDeclaration} — arity detection, policy resolution,
 * and doc-gen behaviour.  Runtime resolution (resolve()) is excluded here
 * as it requires a live RouteInfo/Context.
 *
 * @internal
 *
 * @coversNothing
 */
final class RouteFormDeclarationTest extends TestCase
{
	public function testMakeWithStaticFactoryDoesNotEagerlyRegister(): void
	{
		// Zero-arg factory — make() does NOT call the factory at declaration time.
		$registered = false;
		RouteFormDeclaration::make(static function () use (&$registered): Form {
			$registered = true;

			return new Form();
		});

		self::assertFalse($registered, 'Static factory must not be called eagerly by make().');
	}

	public function testMakeWithFormInstanceIsNotDynamic(): void
	{
		$form = new Form();
		$decl = RouteFormDeclaration::make($form);

		self::assertFalse($decl->isDynamic());
	}

	public function testMakeWithFormInstanceGetPolicyIsStatic(): void
	{
		$form = new Form();
		$decl = RouteFormDeclaration::make($form);

		self::assertSame(RouteFormDocPolicy::STATIC, $decl->getPolicy());
	}

	public function testMakeWithFormInstanceGetDocFormReturnsForm(): void
	{
		$form = new Form();
		$decl = RouteFormDeclaration::make($form);

		self::assertSame($form, $decl->getDocForm());
	}

	public function testMakeWithZeroArgCallableIsNotDynamic(): void
	{
		$form = new Form();
		$decl = RouteFormDeclaration::make(static fn () => $form);

		self::assertFalse($decl->isDynamic());
	}

	public function testMakeWithZeroArgCallableGetDocFormCallsFactory(): void
	{
		$form  = new Form();
		$calls = 0;
		$decl  = RouteFormDeclaration::make(static function () use ($form, &$calls): Form {
			++$calls;

			return $form;
		});

		$result = $decl->getDocForm();

		self::assertSame($form, $result);
		self::assertSame(1, $calls);
	}

	public function testMakeWithZeroArgCallableGetPolicyIsStatic(): void
	{
		$decl = RouteFormDeclaration::make(static fn () => new Form());

		self::assertSame(RouteFormDocPolicy::STATIC, $decl->getPolicy());
	}

	public function testMakeWithOneArgCallableIsDynamic(): void
	{
		$decl = RouteFormDeclaration::make(static fn ($ri) => new Form());

		self::assertTrue($decl->isDynamic());
	}

	public function testMakeWithOneArgCallableGetPolicyIsDynamic(): void
	{
		$decl = RouteFormDeclaration::make(static fn ($ri) => new Form());

		self::assertSame(RouteFormDocPolicy::DYNAMIC, $decl->getPolicy());
	}

	public function testMakeWithFormAndOpaquePolicy(): void
	{
		$decl = RouteFormDeclaration::make(new Form(), RouteFormDocPolicy::OPAQUE);

		self::assertSame(RouteFormDocPolicy::OPAQUE, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testMakeWithFormAndDynamicPolicy(): void
	{
		$decl = RouteFormDeclaration::make(new Form(), RouteFormDocPolicy::DYNAMIC);

		self::assertSame(RouteFormDocPolicy::DYNAMIC, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testMakeWithZeroArgCallableAndOpaquePolicy(): void
	{
		$decl = RouteFormDeclaration::make(static fn () => new Form(), RouteFormDocPolicy::OPAQUE);

		self::assertSame(RouteFormDocPolicy::OPAQUE, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testDynamicWithoutPreviewIsDynamic(): void
	{
		$decl = RouteFormDeclaration::dynamic(static fn ($ri) => new Form());

		self::assertTrue($decl->isDynamic());
	}

	public function testDynamicWithoutPreviewGetDocFormReturnsNull(): void
	{
		$decl = RouteFormDeclaration::dynamic(static fn ($ri) => new Form());

		self::assertNull($decl->getDocForm());
	}

	public function testDynamicWithoutPreviewGetPolicyIsDynamic(): void
	{
		// dynamic() always sets DYNAMIC policy — no promotion logic needed.
		$decl = RouteFormDeclaration::dynamic(static fn ($ri) => new Form());

		self::assertSame(RouteFormDocPolicy::DYNAMIC, $decl->getPolicy());
	}

	public function testDynamicWithPreviewGetDocFormReturnsNull(): void
	{
		// DYNAMIC policy -> getDocForm() always returns null (no requestBody embedding).
		$decl = RouteFormDeclaration::dynamic(
			static fn ($ri) => new Form(),
			static fn () => new Form()
		);

		self::assertNull($decl->getDocForm());
	}

	public function testGetDocPreviewFormReturnsNullWhenNoPreview(): void
	{
		$decl = RouteFormDeclaration::dynamic(static fn ($ri) => new Form());

		self::assertNull($decl->getDocPreviewForm());
	}

	public function testDynamicWithPreviewGetDocPreviewFormCallsPreview(): void
	{
		$preview = new Form();
		$calls   = 0;
		$decl    = RouteFormDeclaration::dynamic(
			static fn ($ri) => new Form(),
			static function () use ($preview, &$calls): Form {
				++$calls;

				return $preview;
			}
		);

		$result = $decl->getDocPreviewForm();

		self::assertSame($preview, $result);
		self::assertSame(1, $calls);
	}

	public function testDynamicWithPreviewGetPolicyIsDynamic(): void
	{
		$decl = RouteFormDeclaration::dynamic(
			static fn ($ri) => new Form(),
			static fn () => new Form()
		);

		// dynamic() always returns DYNAMIC regardless of whether a preview is provided.
		self::assertSame(RouteFormDocPolicy::DYNAMIC, $decl->getPolicy());
	}

	public function testOpaqueWithFormHidesDocForm(): void
	{
		$decl = RouteFormDeclaration::opaque(new Form());

		self::assertSame(RouteFormDocPolicy::OPAQUE, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testOpaqueWithZeroArgCallableHidesDocForm(): void
	{
		$decl = RouteFormDeclaration::opaque(static fn () => new Form());

		self::assertSame(RouteFormDocPolicy::OPAQUE, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testDynamicWithFormHidesDocForm(): void
	{
		$decl = RouteFormDeclaration::dynamic(static fn ($ri) => new Form());

		self::assertSame(RouteFormDocPolicy::DYNAMIC, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testGetPolicyStaticForStaticForm(): void
	{
		$decl = RouteFormDeclaration::make(new Form());

		self::assertSame(RouteFormDocPolicy::STATIC, $decl->getPolicy());
	}

	public function testGetPolicyStaticForStaticFactory(): void
	{
		$decl = RouteFormDeclaration::make(static fn () => new Form());

		self::assertSame(RouteFormDocPolicy::STATIC, $decl->getPolicy());
	}

	public function testGetPolicyDynamicForDynamicWithPreview(): void
	{
		$decl = RouteFormDeclaration::dynamic(
			static fn ($ri) => new Form(),
			static fn () => new Form()
		);

		self::assertSame(RouteFormDocPolicy::DYNAMIC, $decl->getPolicy());
	}

	public function testGetPolicyDynamicForDynamicWithoutPreview(): void
	{
		$decl = RouteFormDeclaration::dynamic(static fn ($ri) => new Form());

		self::assertSame(RouteFormDocPolicy::DYNAMIC, $decl->getPolicy());
	}

	public function testGetDocFormNullForOpaque(): void
	{
		$form = new Form();
		$decl = RouteFormDeclaration::make($form, RouteFormDocPolicy::OPAQUE);

		self::assertNull($decl->getDocForm());
	}

	public function testGetDocFormNullForDynamic(): void
	{
		$form = new Form();
		$decl = RouteFormDeclaration::make($form, RouteFormDocPolicy::DYNAMIC);

		self::assertNull($decl->getDocForm());
	}

	public function testGetDocFormNullForDynamicFactoryWithNoPreview(): void
	{
		$decl = RouteFormDeclaration::dynamic(static fn ($ri) => new Form());

		self::assertNull($decl->getDocForm());
	}

	public function testIsDynamicFalseForStaticForm(): void
	{
		self::assertFalse(RouteFormDeclaration::make(new Form())->isDynamic());
	}

	public function testIsDynamicFalseForStaticFactory(): void
	{
		self::assertFalse(RouteFormDeclaration::make(static fn () => new Form())->isDynamic());
	}

	public function testIsDynamicTrueForDynamicFactory(): void
	{
		self::assertTrue(RouteFormDeclaration::dynamic(static fn ($ri) => new Form())->isDynamic());
	}

	public function testIsDynamicTrueForOneArgMakeCallable(): void
	{
		self::assertTrue(RouteFormDeclaration::make(static fn ($ri) => new Form())->isDynamic());
	}

	// -----------------------------------------------------------------------
	// provider()
	// -----------------------------------------------------------------------

	public function testProviderDeclarationHasDynamicPolicy(): void
	{
		$decl = RouteFormDeclaration::provider(StubFormProvider::class);

		self::assertSame(RouteFormDocPolicy::DYNAMIC, $decl->getPolicy());
	}

	public function testProviderDeclarationStoresProviderClass(): void
	{
		$decl = RouteFormDeclaration::provider(StubFormProvider::class);

		self::assertSame(StubFormProvider::class, $decl->getProviderClass());
	}

	public function testGetProviderClassIsNullForNonProviderDeclaration(): void
	{
		$decl = RouteFormDeclaration::make(new Form());

		self::assertNull($decl->getProviderClass());
	}

	public function testProviderDeclarationGetDocFormReturnsNull(): void
	{
		$decl = RouteFormDeclaration::provider(StubFormProvider::class);

		self::assertNull($decl->getDocForm());
	}

	public function testProviderDeclarationIsNotDynamic(): void
	{
		$decl = RouteFormDeclaration::provider(StubFormProvider::class);

		self::assertFalse($decl->isDynamic());
	}

	public function testProviderWithNonProviderClassThrows(): void
	{
		$this->expectException(RuntimeException::class);

		RouteFormDeclaration::provider(Form::class);
	}
}

/**
 * Minimal provider stub for use in RouteFormDeclarationTest.
 *
 * @internal
 */
final class StubFormProvider extends AbstractResumableFormProvider
{
	public static function getName(): string
	{
		return 'test:stub';
	}

	public function nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form
	{
		return null;
	}
}
