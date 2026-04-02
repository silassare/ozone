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

use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Http\Enums\RequestScope;
use PHPUnit\Framework\TestCase;

/**
 * Class FormTest.
 *
 * Tests for structural {@see Form} features: version fingerprinting, resume
 * configuration, merge behaviour, toArray output, and cache-key derivation.
 *
 * @internal
 *
 * @coversNothing
 */
final class FormTest extends TestCase
{
	// -----------------------------------------------------------------------
	// toArray
	// -----------------------------------------------------------------------

	public function testToArrayIncludesVersion(): void
	{
		$form = new Form();
		$form->field('name')->required(true);

		$arr = $form->toArray();
		self::assertArrayHasKey('version', $arr);
		self::assertNotEmpty($arr['version']);
		self::assertSame($form->getVersion(), $arr['version']);
	}

	public function testToArrayResumeScopeNullWhenNotEnabled(): void
	{
		$arr = (new Form())->toArray();

		self::assertNull($arr['resume_scope']);
		self::assertNull($arr['resume_ttl']);
	}

	public function testToArrayResumeScopeAndTTLWhenEnabled(): void
	{
		$arr = (new Form())->resume(RequestScope::STATE, 1800)->toArray();

		self::assertSame(RequestScope::STATE->value, $arr['resume_scope']);
		self::assertSame(1800, $arr['resume_ttl']);
	}

	// -----------------------------------------------------------------------
	// getVersion
	// -----------------------------------------------------------------------

	public function testGetVersionReturnsNonEmpty(): void
	{
		self::assertNotEmpty((new Form())->getVersion());
	}

	public function testGetVersionIsStableBetweenCalls(): void
	{
		$form = new Form();
		$form->field('email')->required(true);

		self::assertSame($form->getVersion(), $form->getVersion());
	}

	public function testGetVersionChangesWhenFieldAdded(): void
	{
		$form    = new Form();
		$vBefore = $form->getVersion();

		$form->field('email')->required(true);

		self::assertNotSame($vBefore, $form->getVersion());
	}

	public function testGetVersionChangesWhenRequiredFlagChanges(): void
	{
		$formA = new Form();
		$formA->field('name')->required(false);

		$formB = new Form();
		$formB->field('name')->required(true);

		self::assertNotSame($formA->getVersion(), $formB->getVersion());
	}

	public function testGetVersionSameForEquivalentForms(): void
	{
		$a = new Form();
		$a->field('name')->required(true);

		$b = new Form();
		$b->field('name')->required(true);

		self::assertSame($a->getVersion(), $b->getVersion());
	}

	// -----------------------------------------------------------------------
	// resume
	// -----------------------------------------------------------------------

	public function testResumeScopeDefaultIsNull(): void
	{
		self::assertNull((new Form())->getResumeScope());
	}

	public function testResumeTTLDefaultIs3600(): void
	{
		self::assertSame(3600, (new Form())->getResumeTTL());
	}

	public function testResumeSetsScopeAndTTL(): void
	{
		$form = (new Form())->resume(RequestScope::STATE, 7200);

		self::assertSame(RequestScope::STATE, $form->getResumeScope());
		self::assertSame(7200, $form->getResumeTTL());
	}

	public function testResumeReturnsSelf(): void
	{
		$form = new Form();

		self::assertSame($form, $form->resume(RequestScope::USER));
	}

	// -----------------------------------------------------------------------
	// buildResumeCacheKey
	// -----------------------------------------------------------------------

	public function testBuildResumeCacheKeyContainsVersion(): void
	{
		$form    = new Form();
		$form->field('name')->required(true);
		$version = $form->getVersion();

		self::assertStringContainsString($version, $form->buildResumeCacheKey('scope-abc'));
	}

	public function testBuildResumeCacheKeyDiffersForDifferentScopes(): void
	{
		$form = new Form();
		$form->field('name')->required(true);

		self::assertNotSame(
			$form->buildResumeCacheKey('scope-1'),
			$form->buildResumeCacheKey('scope-2')
		);
	}

	public function testBuildResumeCacheKeyIsStableForSameScope(): void
	{
		$form = new Form();
		$form->field('name')->required(true);

		self::assertSame(
			$form->buildResumeCacheKey('user-42'),
			$form->buildResumeCacheKey('user-42')
		);
	}

	// -----------------------------------------------------------------------
	// merge — resume propagation
	// -----------------------------------------------------------------------

	public function testMergePropagatesToNoneTarget(): void
	{
		$source = (new Form())->resume(RequestScope::USER, 600);
		$target = new Form();

		$target->merge($source);

		self::assertSame(RequestScope::USER, $target->getResumeScope());
		self::assertSame(600, $target->getResumeTTL());
	}

	public function testMergeDoesNotOverwriteExistingScope(): void
	{
		$source = (new Form())->resume(RequestScope::USER, 600);
		$target = (new Form())->resume(RequestScope::STATE, 300);

		$target->merge($source);

		// STATE was set first — it wins.
		self::assertSame(RequestScope::STATE, $target->getResumeScope());
		self::assertSame(300, $target->getResumeTTL());
	}

	public function testMergeWithBothNullScopeKeepsNull(): void
	{
		$target = new Form();
		$source = new Form();

		$target->merge($source);

		self::assertNull($target->getResumeScope());
	}

	public function testMergeAddsFieldsFromSource(): void
	{
		$target = new Form();
		$target->field('name')->required(true);

		$source = new Form();
		$source->field('email')->required(true);

		$target->merge($source);

		self::assertNotNull($target->getField('name'));
		self::assertNotNull($target->getField('email'));
	}

	public function testMergeSourceFieldOverridesTargetFieldWithSameName(): void
	{
		$target = new Form();
		$target->field('name')->required(false);

		$source = new Form();
		$source->field('name')->required(true);

		$target->merge($source);

		// Source field overrides (array_merge string key — last wins).
		self::assertTrue($target->getField('name')->isRequired());
	}

	// -----------------------------------------------------------------------
	// name
	// -----------------------------------------------------------------------

	public function testGetRefWithNoName(): void
	{
		$form = new Form();

		self::assertSame('field', $form->getRef('field'));
	}

	public function testGetRefWithName(): void
	{
		$form = new Form('section');

		self::assertSame('section.field', $form->getRef('field'));
	}

	// -----------------------------------------------------------------------
	// method / submitTo
	// -----------------------------------------------------------------------

	public function testDefaultMethodIsPost(): void
	{
		self::assertSame('POST', (new Form())->getMethod());
	}

	public function testMethodIsUpperCased(): void
	{
		$form = new Form(method: 'get');

		self::assertSame('GET', $form->getMethod());
	}

	public function testSubmitToNullByDefault(): void
	{
		self::assertNull((new Form())->getSubmitTo());
	}

	// -----------------------------------------------------------------------
	// getField
	// -----------------------------------------------------------------------

	public function testGetFieldReturnsNullForUnknownField(): void
	{
		self::assertNull((new Form())->getField('nonexistent'));
	}

	public function testGetFieldReturnsSameFieldInstanceAsField(): void
	{
		$form  = new Form();
		$field = $form->field('name');

		self::assertSame($field, $form->getField('name'));
	}

	// -----------------------------------------------------------------------
	// helper
	// -----------------------------------------------------------------------

	private function makeFormData(array $data): FormData
	{
		$fd = new FormData();

		foreach ($data as $key => $value) {
			$fd->set($key, $value);
		}

		return $fd;
	}
}
