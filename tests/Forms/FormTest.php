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

use LogicException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\AsyncValue;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormDataClean;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Http\Uri;
use OZONE\Core\Stores\StateRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Class FormTest.
 *
 * Tests for structural {@see Form} features: its version, resume
 * configuration, merge behaviour, toArray output, and cache-key derivation.
 *
 * @internal
 *
 * @covers \OZONE\Core\Forms\Form
 */
final class FormTest extends TestCase
{
	protected function setUp(): void
	{
		// Clear the form resume cache before each test so cache round-trip tests
		// do not interfere with each other.
		StateRegistry::store(Form::FORM_DATA_RESUME_CACHE_NAMESPACE)->clear();
	}

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

	public function testToArrayResumableScopeAndTTLWhenEnabled(): void
	{
		$arr = (new Form())->resumable(RequestScope::STATE, 1800)->toArray();

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

	public function testResumableSetsScopeAndTTL(): void
	{
		$form = (new Form())->resumable(RequestScope::STATE, 7200);

		self::assertSame(RequestScope::STATE, $form->getResumeScope());
		self::assertSame(7200, $form->getResumeTTL());
	}

	public function testResumableReturnsSelf(): void
	{
		$form = new Form();

		self::assertSame($form, $form->resumable(RequestScope::USER));
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

	public function testBuildResumeCacheKeyWithIdPrefixesKey(): void
	{
		$form = new Form();
		$form->field('name')->required(true);
		$form->setId('step-1');

		self::assertStringStartsWith('step-1_', $form->buildResumeCacheKey('scope-abc'));
	}

	public function testBuildResumeCacheKeyDiffersForDifferentIds(): void
	{
		$form = new Form();
		$form->field('name')->required(true);

		$formA = clone $form;
		$formA->setId('form-a');

		$formB = clone $form;
		$formB->setId('form-b');

		self::assertNotSame(
			$formA->buildResumeCacheKey('scope-x'),
			$formB->buildResumeCacheKey('scope-x')
		);
	}

	public function testBuildResumeCacheKeyWithoutIdDiffersFromWithId(): void
	{
		$form = new Form();
		$form->field('name')->required(true);

		$withoutId = $form->buildResumeCacheKey('scope-x');

		$form->setId('my-form');

		$withId = $form->buildResumeCacheKey('scope-x');

		self::assertNotSame($withoutId, $withId);
	}

	// -----------------------------------------------------------------------
	// setId / getId
	// -----------------------------------------------------------------------

	public function testGetIdNullByDefault(): void
	{
		self::assertNull((new Form())->getId());
	}

	public function testSetIdReturnsSelf(): void
	{
		$form = new Form();

		self::assertSame($form, $form->setId('wizard'));
	}

	public function testGetIdReturnsSetValue(): void
	{
		$form = new Form();
		$form->setId('step-2');

		self::assertSame('step-2', $form->getId());
	}

	// -----------------------------------------------------------------------
	// merge — resume propagation
	// -----------------------------------------------------------------------

	public function testMergePropagatesToNoneTarget(): void
	{
		$source = (new Form())->resumable(RequestScope::USER, 600);
		$target = new Form();

		$target->merge($source);

		self::assertSame(RequestScope::USER, $target->getResumeScope());
		self::assertSame(600, $target->getResumeTTL());
	}

	public function testMergeDoesNotOverwriteExistingScope(): void
	{
		$source = (new Form())->resumable(RequestScope::USER, 600);
		$target = (new Form())->resumable(RequestScope::STATE, 300);

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

	public function testMergeRejectsCollidingFieldRef(): void
	{
		$target = new Form();
		$target->field('name')->required(false);

		$source = new Form();
		$source->field('name')->required(true);

		// Both forms are unnamed, so both fields carry the ref "name".
		// Silently overwriting one with the other would drop a constraint, so merge refuses.
		$this->expectException(RuntimeException::class);
		$target->merge($source);
	}

	public function testMergeKeepsSameLocalNameWhenFormsAreNamed(): void
	{
		$target = new Form('a');
		$target->field('name')->required(false);

		$source = new Form('b');
		$source->field('name')->required(true);

		$target->merge($source);

		self::assertFalse($target->getField('a.name')->isRequired());
		self::assertTrue($target->getField('b.name')->isRequired());
	}

	public function testMergeClonesFieldsSoSourceIsNotMutated(): void
	{
		$source = new Form('src');
		$source->field('name')->required(true);

		$bundle = new Form();
		$bundle->merge($source);

		$bundle->getField('src.name')->required(false);

		// A long-lived source form must not be mutated through a merged bundle.
		self::assertTrue($source->getField('name')->isRequired());
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

	public function testActionIsTheAbsolutePathOfTheSubmitUri(): void
	{
		// A client addresses the server it is talking to: the scheme and the authority are left out, so
		// the same answer serves whatever host it was asked on.
		$uri  = Uri::createFromString('https://api.example.com/orders/new?step=2');
		$form = (new Form())->submitTo($uri);

		self::assertSame('/orders/new?step=2', $form->toArray()['action']);
	}

	public function testActionIsNullWithoutASubmitUri(): void
	{
		self::assertNull((new Form())->toArray()['action']);
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
	// resume() and saveForLater() — cache round-trip
	// -----------------------------------------------------------------------

	public function testResumeWhenNotResumableReturnsNullAndCallable(): void
	{
		$form = new Form();
		$form->field('email')->required(true);

		[$prefilled, $drop] = $form->resume(context());

		self::assertNull($prefilled);
		self::assertIsCallable($drop);
		$drop(); // must not throw
	}

	public function testResumeWhenResumableAndNoCachedDataReturnsNull(): void
	{
		$form = (new Form())->resumable(RequestScope::HOST);
		$form->field('email')->required(true);

		[$prefilled] = $form->resume(context());

		self::assertNull($prefilled);
	}

	public function testSaveForLaterThrowsWhenNotResumable(): void
	{
		$form = new Form();
		$form->field('email')->required(true);

		$this->expectException(LogicException::class);
		$form->saveForLater(context(), $this->makeFormData(['email' => 'a@b.com']));
	}

	public function testSaveForLaterReturnsSelf(): void
	{
		$form = (new Form())->resumable(RequestScope::HOST);
		$form->field('email')->required(true);

		self::assertSame($form, $form->saveForLater(context(), $this->makeFormData(['email' => 'a@b.com'])));
	}

	public function testResumeRoundTrip(): void
	{
		$form = (new Form('signup'))->resumable(RequestScope::HOST);
		$form->field('email')->required(true);

		$form->saveForLater(context(), $this->makeFormData(['signup' => ['email' => 'test@example.com']]));

		[$prefilled] = $form->resume(context());

		self::assertNotNull($prefilled);
		self::assertSame('test@example.com', $prefilled->get('signup.email'));
	}

	public function testResumeSurvivesAddingAField(): void
	{
		$form = (new Form('signup'))->resumable(RequestScope::HOST);
		$form->field('email')->required(true);

		$form->saveForLater(context(), $this->makeFormData(['signup' => ['email' => 'test@example.com']]));

		// Adding a field no longer orphans in-flight resume data.
		$form->field('name');

		[$prefilled] = $form->resume(context());

		self::assertNotNull($prefilled);
		self::assertSame('test@example.com', $prefilled->get('signup.email'));
	}

	public function testResumeDropsValuesWhoseFieldNoLongerExists(): void
	{
		$before = (new Form('signup'))->resumable(RequestScope::HOST);
		$before->field('email')->required(true);
		$before->saveForLater(context(), $this->makeFormData(['signup' => ['email' => 'test@example.com']]));

		// 'email' is gone from the definition, so its cached value must not be replayed.
		$after = (new Form('signup'))->resumable(RequestScope::HOST);
		$after->field('name');

		[$prefilled] = $after->resume(context());

		self::assertNotNull($prefilled);
		self::assertFalse($prefilled->has('signup.email'));
	}

	public function testDropCallableClearsCachedData(): void
	{
		$form = (new Form('drop'))->resumable(RequestScope::HOST);
		$form->field('email')->required(true);

		$form->saveForLater(context(), $this->makeFormData(['drop' => ['email' => 'x@x.com']]));

		[$prefilled, $drop] = $form->resume(context());
		self::assertNotNull($prefilled); // data is there

		$drop();

		[$prefilledAfterDrop] = $form->resume(context());
		self::assertNull($prefilledAfterDrop); // gone
	}

	public function testResumePreservesTTLFromResumable(): void
	{
		$form = (new Form())->resumable(RequestScope::HOST, 900);
		$form->field('name')->required(true);

		self::assertSame(900, $form->getResumeTTL());
	}

	public function testBuildResumeCacheKeyDiffersPerPartition(): void
	{
		$form = (new Form())->setId('step-1');

		$none = $form->buildResumeCacheKey('scope-x');
		$a    = $form->buildResumeCacheKey('scope-x', 'route-a');
		$b    = $form->buildResumeCacheKey('scope-x', 'route-b');

		self::assertNotSame($none, $a);
		self::assertNotSame($a, $b);
		self::assertSame($a, $form->buildResumeCacheKey('scope-x', 'route-a'));
		self::assertStringStartsWith('step-1_', $a);
	}

	public function testResumeIsIsolatedPerPartition(): void
	{
		$form = (new Form('part'))->resumable(RequestScope::HOST);
		$form->field('email')->required(true);

		$form->saveForLater(context(), $this->makeFormData(['part' => ['email' => 'a@b.com']]), 'route-a');

		[$other] = $form->resume(context(), 'route-b');
		[$none]  = $form->resume(context());
		[$same]  = $form->resume(context(), 'route-a');

		self::assertNull($other);
		self::assertNull($none);
		self::assertNotNull($same);
		self::assertSame('a@b.com', $same->get('part.email'));
	}

	public function testReplayedValueThatNoLongerValidatesIsDropped(): void
	{
		$form = new Form();
		$form->int('age', true);

		$cleaned = new FormDataClean(['age' => 'not a number']);

		try {
			$form->validate(new FormData([]), $cleaned);
			self::fail('The stale replayed value should be rejected.');
		} catch (InvalidFormException) {
		}

		// Dropped, so saving progress after the failure cannot store it back.
		self::assertFalse($cleaned->has('age'));
	}

	public function testADiscoveredFieldSaysWhetherItHoldsAList(): void
	{
		$form = new Form();
		$form->int('one');
		$form->int('several')->multiple();

		$fields = \json_decode((string) \json_encode($form->toArray()), true)['fields'];

		self::assertFalse($fields['one']['multiple']);
		self::assertTrue($fields['several']['multiple']);
	}

	public function testAClientIsSentThePreviewOfAPublicValue(): void
	{
		$form = new Form();
		$form->int('seats');
		$form->expect()->lte('seats', AsyncValue::public(static fn () => 12, static fn () => 10));

		// Resolved to plain values: nothing is left to serialize outside the preview scope.
		$sent = $form->toClientArray();

		self::assertSame(['$preview' => ['value' => 10]], $sent['expect'][0]['rules'][0]['value']);
		self::assertSame($sent, \json_decode((string) \json_encode($sent), true));
	}

	public function testAFormsVersionRunsNoPreview(): void
	{
		$runs = 0;
		$form = new Form();
		$form->int('seats');
		$form->expect()->lte('seats', AsyncValue::public(
			static fn () => 12,
			static function () use (&$runs) {
				++$runs;

				return 10;
			}
		));

		$version = $form->getVersion();

		self::assertSame(0, $runs);

		// A preview changes with what it reads (seats left): it must not change the version.
		$form->toClientArray();

		self::assertSame(1, $runs);
		self::assertSame($version, $form->getVersion());
		// The client is sent the version the server computes.
		self::assertSame($version, $form->toClientArray()['version']);
	}

	public function testAFormsVersionSeesEveryChangeAClientWouldSee(): void
	{
		$make = static function (string $kind, string $label): Form {
			$form = new Form();
			$form->string('kind');
			$form->string('detail')->label($label)->if()->eq('kind', $kind);

			return $form;
		};

		$version = $make('a', 'Detail')->getVersion();

		// A condition and a label were outside the old structural fingerprint.
		self::assertNotSame($version, $make('b', 'Detail')->getVersion());
		self::assertNotSame($version, $make('a', 'More')->getVersion());
		self::assertSame($version, $make('a', 'Detail')->getVersion());
	}

	public function testAnExplicitVersionIsTheFormsVersion(): void
	{
		$form = (new Form())->version('b3f9c2');
		$form->string('name');

		self::assertSame('b3f9c2', $form->getVersion());
		self::assertSame('b3f9c2', $form->toClientArray()['version']);

		$this->expectException(LogicException::class);

		(new Form())->version('');
	}

	public function testADiscoveredEnumFieldSendsItsCases(): void
	{
		$form = new Form();
		$form->enum('plan', FormTestPlan::class);

		$type = \json_decode((string) \json_encode($form->toArray()), true)['fields']['plan']['type'];

		self::assertSame('enum', $type['type']);
		self::assertSame([
			['name' => 'Free', 'value' => 'free'],
			['name' => 'Pro', 'value' => 'pro'],
		], $type['enum_cases']);
	}

	// -----------------------------------------------------------------------
	// helper
	// -----------------------------------------------------------------------

	private function makeFormData(array $data): FormDataClean
	{
		$fd = new FormDataClean();

		foreach ($data as $key => $value) {
			$fd->set($key, $value);
		}

		return $fd;
	}
}

enum FormTestPlan: string
{
	case Free = 'free';

	case Pro = 'pro';
}
