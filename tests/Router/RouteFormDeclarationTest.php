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

use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormRegistry;
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
	protected function setUp(): void
	{
		FormRegistry::clear();
	}

	public function testMakeWithFormInstanceRegistersForm(): void
	{
		// Only explicitly named forms (those with a key() call) are registered.
		$form = (new Form())->key('test:make:registers');
		$key  = $form->getKey();
		RouteFormDeclaration::make($form);

		self::assertSame($form, FormRegistry::get($key));
	}

	public function testMakeWithAutoKeyedFormInstanceDoesNotRegister(): void
	{
		// Auto-keyed forms must NOT be registered at make() time.
		$before = FormRegistry::all();
		$form   = new Form(); // auto-key, not named
		RouteFormDeclaration::make($form);

		self::assertSame($before, FormRegistry::all());
	}

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

	public function testMakeWithDynamicFactoryDoesNotRegister(): void
	{
		// One-arg factory — no form is registered at declaration time.
		$before = FormRegistry::all();
		RouteFormDeclaration::make(static fn($ri) => new Form());

		self::assertSame($before, FormRegistry::all());
	}

	public function testMakeWithFormInstanceIsNotDynamic(): void
	{
		$form = new Form();
		$decl = RouteFormDeclaration::make($form);

		self::assertFalse($decl->isDynamic());
	}

	public function testMakeWithFormInstanceGetPolicyIsAuto(): void
	{
		$form = new Form();
		$decl = RouteFormDeclaration::make($form);

		self::assertSame(RouteFormDocPolicy::AUTO, $decl->getPolicy());
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
		$decl = RouteFormDeclaration::make(static fn() => $form);

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

	public function testMakeWithZeroArgCallableGetPolicyIsAuto(): void
	{
		$decl = RouteFormDeclaration::make(static fn() => new Form());

		self::assertSame(RouteFormDocPolicy::AUTO, $decl->getPolicy());
	}

	public function testMakeWithOneArgCallableIsDynamic(): void
	{
		$decl = RouteFormDeclaration::make(static fn($ri) => new Form());

		self::assertTrue($decl->isDynamic());
	}

	public function testMakeWithOneArgCallableGetDocFormReturnsNull(): void
	{
		$decl = RouteFormDeclaration::make(static fn($ri) => new Form());

		self::assertNull($decl->getDocForm());
	}

	public function testMakeWithFormAndOpaquePolicy(): void
	{
		$decl = RouteFormDeclaration::make(new Form(), RouteFormDocPolicy::OPAQUE);

		self::assertSame(RouteFormDocPolicy::OPAQUE, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testMakeWithFormAndDiscoveryOnlyPolicy(): void
	{
		$decl = RouteFormDeclaration::make(new Form(), RouteFormDocPolicy::DISCOVERY_ONLY);

		self::assertSame(RouteFormDocPolicy::DISCOVERY_ONLY, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testMakeWithZeroArgCallableAndOpaquePolicy(): void
	{
		$decl = RouteFormDeclaration::make(static fn() => new Form(), RouteFormDocPolicy::OPAQUE);

		self::assertSame(RouteFormDocPolicy::OPAQUE, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testDynamicWithoutPreviewIsDynamic(): void
	{
		$decl = RouteFormDeclaration::dynamic(static fn($ri) => new Form());

		self::assertTrue($decl->isDynamic());
	}

	public function testDynamicWithoutPreviewGetDocFormReturnsNull(): void
	{
		$decl = RouteFormDeclaration::dynamic(static fn($ri) => new Form());

		self::assertNull($decl->getDocForm());
	}

	public function testDynamicWithoutPreviewGetPolicyPromotesToOpaque(): void
	{
		// AUTO + dynamic + no preview -> promoted to OPAQUE so the form does not
		// silently disappear from the spec.
		$decl = RouteFormDeclaration::dynamic(static fn($ri) => new Form());

		self::assertSame(RouteFormDocPolicy::OPAQUE, $decl->getPolicy());
	}

	public function testDynamicWithPreviewGetDocFormCallsPreview(): void
	{
		$preview = new Form();
		$calls   = 0;
		$decl    = RouteFormDeclaration::dynamic(
			static fn($ri) => new Form(),
			static function () use ($preview, &$calls): Form {
				++$calls;

				return $preview;
			}
		);

		$result = $decl->getDocForm();

		self::assertSame($preview, $result);
		self::assertSame(1, $calls);
	}

	public function testDynamicWithPreviewGetPolicyIsAuto(): void
	{
		$decl = RouteFormDeclaration::dynamic(
			static fn($ri) => new Form(),
			static fn() => new Form()
		);

		// AUTO is NOT promoted when a preview is provided.
		self::assertSame(RouteFormDocPolicy::AUTO, $decl->getPolicy());
	}

	public function testOpaqueWithFormHidesDocForm(): void
	{
		$decl = RouteFormDeclaration::opaque(new Form());

		self::assertSame(RouteFormDocPolicy::OPAQUE, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testOpaqueWithZeroArgCallableHidesDocForm(): void
	{
		$decl = RouteFormDeclaration::opaque(static fn() => new Form());

		self::assertSame(RouteFormDocPolicy::OPAQUE, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testDiscoveryOnlyWithFormHidesDocForm(): void
	{
		$decl = RouteFormDeclaration::discoveryOnly(new Form());

		self::assertSame(RouteFormDocPolicy::DISCOVERY_ONLY, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testGetPolicyNotPromotedForStaticForm(): void
	{
		$decl = RouteFormDeclaration::make(new Form());

		self::assertSame(RouteFormDocPolicy::AUTO, $decl->getPolicy());
	}

	public function testGetPolicyNotPromotedForStaticFactory(): void
	{
		$decl = RouteFormDeclaration::make(static fn() => new Form());

		self::assertSame(RouteFormDocPolicy::AUTO, $decl->getPolicy());
	}

	public function testGetPolicyNotPromotedForDynamicWithPreview(): void
	{
		$decl = RouteFormDeclaration::dynamic(
			static fn($ri) => new Form(),
			static fn() => new Form()
		);

		self::assertSame(RouteFormDocPolicy::AUTO, $decl->getPolicy());
	}

	public function testGetPolicyPromotedForDynamicWithoutPreview(): void
	{
		$decl = RouteFormDeclaration::dynamic(static fn($ri) => new Form());

		self::assertSame(RouteFormDocPolicy::OPAQUE, $decl->getPolicy());
	}

	public function testGetDocFormNullForOpaque(): void
	{
		$form = new Form();
		$decl = RouteFormDeclaration::make($form, RouteFormDocPolicy::OPAQUE);

		self::assertNull($decl->getDocForm());
	}

	public function testGetDocFormNullForDiscoveryOnly(): void
	{
		$form = new Form();
		$decl = RouteFormDeclaration::make($form, RouteFormDocPolicy::DISCOVERY_ONLY);

		self::assertNull($decl->getDocForm());
	}

	public function testGetDocFormNullForDynamicFactoryWithNoPreview(): void
	{
		$decl = RouteFormDeclaration::dynamic(static fn($ri) => new Form());

		self::assertNull($decl->getDocForm());
	}

	public function testIsDynamicFalseForStaticForm(): void
	{
		self::assertFalse(RouteFormDeclaration::make(new Form())->isDynamic());
	}

	public function testIsDynamicFalseForStaticFactory(): void
	{
		self::assertFalse(RouteFormDeclaration::make(static fn() => new Form())->isDynamic());
	}

	public function testIsDynamicTrueForDynamicFactory(): void
	{
		self::assertTrue(RouteFormDeclaration::dynamic(static fn($ri) => new Form())->isDynamic());
	}

	public function testIsDynamicTrueForOneArgMakeCallable(): void
	{
		self::assertTrue(RouteFormDeclaration::make(static fn($ri) => new Form())->isDynamic());
	}
}
