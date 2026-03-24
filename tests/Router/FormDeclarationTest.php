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
use OZONE\Core\Router\Enums\FormDocPolicy;
use OZONE\Core\Router\FormDeclaration;
use PHPUnit\Framework\TestCase;

/**
 * Class FormDeclarationTest.
 *
 * Tests for {@see FormDeclaration} — arity detection, policy resolution,
 * and doc-gen behaviour.  Runtime resolution (resolve()) is excluded here
 * as it requires a live RouteInfo/Context.
 *
 * @internal
 *
 * @coversNothing
 */
final class FormDeclarationTest extends TestCase
{
	// -----------------------------------------------------------
	// make() — Form instance
	// -----------------------------------------------------------

	public function testMakeWithFormInstanceIsNotDynamic(): void
	{
		$form = new Form();
		$decl = FormDeclaration::make($form);

		self::assertFalse($decl->isDynamic());
	}

	public function testMakeWithFormInstanceGetPolicyIsAuto(): void
	{
		$form = new Form();
		$decl = FormDeclaration::make($form);

		self::assertSame(FormDocPolicy::AUTO, $decl->getPolicy());
	}

	public function testMakeWithFormInstanceGetDocFormReturnsForm(): void
	{
		$form = new Form();
		$decl = FormDeclaration::make($form);

		self::assertSame($form, $decl->getDocForm());
	}

	// -----------------------------------------------------------
	// make() — zero-arg callable (static factory)
	// -----------------------------------------------------------

	public function testMakeWithZeroArgCallableIsNotDynamic(): void
	{
		$form = new Form();
		$decl = FormDeclaration::make(static fn () => $form);

		self::assertFalse($decl->isDynamic());
	}

	public function testMakeWithZeroArgCallableGetDocFormCallsFactory(): void
	{
		$form  = new Form();
		$calls = 0;
		$decl  = FormDeclaration::make(static function () use ($form, &$calls): Form {
			++$calls;

			return $form;
		});

		$result = $decl->getDocForm();

		self::assertSame($form, $result);
		self::assertSame(1, $calls);
	}

	public function testMakeWithZeroArgCallableGetPolicyIsAuto(): void
	{
		$decl = FormDeclaration::make(static fn () => new Form());

		self::assertSame(FormDocPolicy::AUTO, $decl->getPolicy());
	}

	// -----------------------------------------------------------
	// make() — one-arg callable (dynamic factory)
	// -----------------------------------------------------------

	public function testMakeWithOneArgCallableIsDynamic(): void
	{
		$decl = FormDeclaration::make(static fn ($ri) => new Form());

		self::assertTrue($decl->isDynamic());
	}

	public function testMakeWithOneArgCallableGetDocFormReturnsNull(): void
	{
		$decl = FormDeclaration::make(static fn ($ri) => new Form());

		self::assertNull($decl->getDocForm());
	}

	// -----------------------------------------------------------
	// make() with explicit policy
	// -----------------------------------------------------------

	public function testMakeWithFormAndOpaquePolicy(): void
	{
		$decl = FormDeclaration::make(new Form(), FormDocPolicy::OPAQUE);

		self::assertSame(FormDocPolicy::OPAQUE, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testMakeWithFormAndDiscoveryOnlyPolicy(): void
	{
		$decl = FormDeclaration::make(new Form(), FormDocPolicy::DISCOVERY_ONLY);

		self::assertSame(FormDocPolicy::DISCOVERY_ONLY, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testMakeWithZeroArgCallableAndOpaquePolicy(): void
	{
		$decl = FormDeclaration::make(static fn () => new Form(), FormDocPolicy::OPAQUE);

		self::assertSame(FormDocPolicy::OPAQUE, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	// -----------------------------------------------------------
	// dynamic() — without doc preview
	// -----------------------------------------------------------

	public function testDynamicWithoutPreviewIsDynamic(): void
	{
		$decl = FormDeclaration::dynamic(static fn ($ri) => new Form());

		self::assertTrue($decl->isDynamic());
	}

	public function testDynamicWithoutPreviewGetDocFormReturnsNull(): void
	{
		$decl = FormDeclaration::dynamic(static fn ($ri) => new Form());

		self::assertNull($decl->getDocForm());
	}

	public function testDynamicWithoutPreviewGetPolicyPromotesToOpaque(): void
	{
		// AUTO + dynamic + no preview -> promoted to OPAQUE so the form does not
		// silently disappear from the spec.
		$decl = FormDeclaration::dynamic(static fn ($ri) => new Form());

		self::assertSame(FormDocPolicy::OPAQUE, $decl->getPolicy());
	}

	// -----------------------------------------------------------
	// dynamic() — with doc preview
	// -----------------------------------------------------------

	public function testDynamicWithPreviewGetDocFormCallsPreview(): void
	{
		$preview = new Form();
		$calls   = 0;
		$decl    = FormDeclaration::dynamic(
			static fn ($ri) => new Form(),
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
		$decl = FormDeclaration::dynamic(
			static fn ($ri) => new Form(),
			static fn () => new Form()
		);

		// AUTO is NOT promoted when a preview is provided.
		self::assertSame(FormDocPolicy::AUTO, $decl->getPolicy());
	}

	// -----------------------------------------------------------
	// opaque()
	// -----------------------------------------------------------

	public function testOpaqueWithFormHidesDocForm(): void
	{
		$decl = FormDeclaration::opaque(new Form());

		self::assertSame(FormDocPolicy::OPAQUE, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	public function testOpaqueWithZeroArgCallableHidesDocForm(): void
	{
		$decl = FormDeclaration::opaque(static fn () => new Form());

		self::assertSame(FormDocPolicy::OPAQUE, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	// -----------------------------------------------------------
	// discoveryOnly()
	// -----------------------------------------------------------

	public function testDiscoveryOnlyWithFormHidesDocForm(): void
	{
		$decl = FormDeclaration::discoveryOnly(new Form());

		self::assertSame(FormDocPolicy::DISCOVERY_ONLY, $decl->getPolicy());
		self::assertNull($decl->getDocForm());
	}

	// -----------------------------------------------------------
	// getPolicy() — AUTO promotion rules
	// -----------------------------------------------------------

	public function testGetPolicyNotPromotedForStaticForm(): void
	{
		$decl = FormDeclaration::make(new Form());

		self::assertSame(FormDocPolicy::AUTO, $decl->getPolicy());
	}

	public function testGetPolicyNotPromotedForStaticFactory(): void
	{
		$decl = FormDeclaration::make(static fn () => new Form());

		self::assertSame(FormDocPolicy::AUTO, $decl->getPolicy());
	}

	public function testGetPolicyNotPromotedForDynamicWithPreview(): void
	{
		$decl = FormDeclaration::dynamic(
			static fn ($ri) => new Form(),
			static fn () => new Form()
		);

		self::assertSame(FormDocPolicy::AUTO, $decl->getPolicy());
	}

	public function testGetPolicyPromotedForDynamicWithoutPreview(): void
	{
		$decl = FormDeclaration::dynamic(static fn ($ri) => new Form());

		self::assertSame(FormDocPolicy::OPAQUE, $decl->getPolicy());
	}

	// -----------------------------------------------------------
	// getDocForm() — exhaustive policy enforcement
	// -----------------------------------------------------------

	public function testGetDocFormNullForOpaque(): void
	{
		$form = new Form();
		$decl = FormDeclaration::make($form, FormDocPolicy::OPAQUE);

		self::assertNull($decl->getDocForm());
	}

	public function testGetDocFormNullForDiscoveryOnly(): void
	{
		$form = new Form();
		$decl = FormDeclaration::make($form, FormDocPolicy::DISCOVERY_ONLY);

		self::assertNull($decl->getDocForm());
	}

	public function testGetDocFormNullForDynamicFactoryWithNoPreview(): void
	{
		$decl = FormDeclaration::dynamic(static fn ($ri) => new Form());

		self::assertNull($decl->getDocForm());
	}

	// -----------------------------------------------------------
	// isDynamic()
	// -----------------------------------------------------------

	public function testIsDynamicFalseForStaticForm(): void
	{
		self::assertFalse(FormDeclaration::make(new Form())->isDynamic());
	}

	public function testIsDynamicFalseForStaticFactory(): void
	{
		self::assertFalse(FormDeclaration::make(static fn () => new Form())->isDynamic());
	}

	public function testIsDynamicTrueForDynamicFactory(): void
	{
		self::assertTrue(FormDeclaration::dynamic(static fn ($ri) => new Form())->isDynamic());
	}

	public function testIsDynamicTrueForOneArgMakeCallable(): void
	{
		self::assertTrue(FormDeclaration::make(static fn ($ri) => new Form())->isDynamic());
	}
}
