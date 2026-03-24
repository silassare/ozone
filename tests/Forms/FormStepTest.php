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

use InvalidArgumentException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormStep;
use OZONE\Core\Forms\RuleSet;
use PHPUnit\Framework\TestCase;

/**
 * Class FormStepTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class FormStepTest extends TestCase
{
	// -----------------------------------------------------------
	// static() factory
	// -----------------------------------------------------------

	public function testStaticStepWithFormInstance(): void
	{
		$parent = new Form();
		$inner  = new Form();
		$step   = FormStep::static($parent, 'details', $inner);

		self::assertTrue($step->isStatic());
		self::assertFalse($step->isDynamic());
		self::assertSame('details', $step->getName());
		self::assertSame('details', $step->getRef());
		self::assertSame($inner, $step->getStaticForm());
	}

	public function testStaticStepWithCallableFactory(): void
	{
		$parent = new Form();
		$inner  = new Form();
		$step   = FormStep::static($parent, 'details', static fn () => $inner);

		self::assertTrue($step->isStatic());
		self::assertSame($inner, $step->getStaticForm());
	}

	// -----------------------------------------------------------
	// dynamic() factory
	// -----------------------------------------------------------

	public function testDynamicStepIsDynamic(): void
	{
		$parent = new Form();
		$step   = FormStep::dynamic($parent, 'extra', static fn (FormData $fd) => new Form());

		self::assertFalse($step->isStatic());
		self::assertTrue($step->isDynamic());
	}

	public function testDynamicStepGetStaticFormThrows(): void
	{
		$parent  = new Form();
		$step    = FormStep::dynamic($parent, 'extra', static fn (FormData $fd) => new Form());

		$this->expectException(RuntimeException::class);
		$step->getStaticForm();
	}

	// -----------------------------------------------------------
	// invalid step name
	// -----------------------------------------------------------

	public function testStaticStepWithInvalidNameThrows(): void
	{
		$parent = new Form();

		$this->expectException(InvalidArgumentException::class);
		FormStep::static($parent, '', new Form());
	}

	// -----------------------------------------------------------
	// build() without condition
	// -----------------------------------------------------------

	public function testBuildAlwaysRunsWhenNoCondition(): void
	{
		$parent = new Form();
		$inner  = new Form();
		$step   = FormStep::static($parent, 'details', $inner);
		$fd     = $this->makeFormData([]);

		self::assertSame($inner, $step->build($fd));
	}

	// -----------------------------------------------------------
	// build() with condition (only_if)
	// -----------------------------------------------------------

	public function testBuildReturnsFormWhenConditionPasses(): void
	{
		$parent  = new Form();
		$inner   = new Form();
		$only_if = (new RuleSet())->eq('type', 'advanced');
		$step    = FormStep::static($parent, 'details', $inner, $only_if);

		self::assertSame($inner, $step->build($this->makeFormData(['type' => 'advanced'])));
	}

	public function testBuildReturnsNullWhenConditionFails(): void
	{
		$parent  = new Form();
		$inner   = new Form();
		$only_if = (new RuleSet())->eq('type', 'advanced');
		$step    = FormStep::static($parent, 'details', $inner, $only_if);

		self::assertNull($step->build($this->makeFormData(['type' => 'simple'])));
	}

	// -----------------------------------------------------------
	// build() dynamic step
	// -----------------------------------------------------------

	public function testBuildDynamicStepPassesFdToFactory(): void
	{
		$parent = new Form();
		$inner  = new Form();
		$step   = FormStep::dynamic($parent, 'extra', static fn (FormData $fd) => $inner);

		self::assertSame($inner, $step->build($this->makeFormData([])));
	}

	// -----------------------------------------------------------
	// getRef() with prefixed parent
	// -----------------------------------------------------------

	public function testGetRefIncludesParentPrefix(): void
	{
		$parent = new Form();
		$parent->prefix('user');
		$inner  = new Form();
		$step   = FormStep::static($parent, 'address', $inner);

		self::assertSame('user.address', $step->getRef());
	}

	// -----------------------------------------------------------
	// toArray()
	// -----------------------------------------------------------

	public function testToArrayStructureForStaticStep(): void
	{
		$parent = new Form();
		$inner  = new Form();
		$step   = FormStep::static($parent, 'details', $inner);
		$arr    = $step->toArray();

		self::assertSame('details', $arr['ref']);
		self::assertSame('details', $arr['name']);
		self::assertSame('static', $arr['type']);
		self::assertInstanceOf(Form::class, $arr['form']);
		self::assertNull($arr['if']);
	}

	public function testToArrayStructureForDynamicStep(): void
	{
		$parent = new Form();
		$step   = FormStep::dynamic($parent, 'extra', static fn (FormData $fd) => new Form());
		$arr    = $step->toArray();

		self::assertSame('dynamic', $arr['type']);
		self::assertNull($arr['form']);
		self::assertNull($arr['if']);
	}

	public function testToArrayIncludesCondition(): void
	{
		$parent  = new Form();
		$inner   = new Form();
		$only_if = (new RuleSet())->eq('type', 'advanced');
		$step    = FormStep::static($parent, 'details', $inner, $only_if);
		$arr     = $step->toArray();

		self::assertNotNull($arr['if']);
		self::assertInstanceOf(RuleSet::class, $arr['if']);
		self::assertSame('details', $arr['ref']);
	}

	// -----------------------------------------------------------
	// Form::step() / Form::dynamicStep() registration
	// -----------------------------------------------------------

	public function testFormStepRegistersInTSteps(): void
	{
		$form = new Form();
		$sub  = new Form();
		$step = $form->step('details', $sub);

		self::assertArrayHasKey('details', $form->t_steps);
		self::assertSame($step, $form->t_steps['details']);
	}

	public function testFormDynamicStepRegistersInTSteps(): void
	{
		$form = new Form();
		$step = $form->dynamicStep('extra', static fn (FormData $fd) => new Form());

		self::assertArrayHasKey('extra', $form->t_steps);
		self::assertSame($step, $form->t_steps['extra']);
	}

	// -----------------------------------------------------------
	// Form::getStep()
	// -----------------------------------------------------------

	public function testGetStepReturnsRegisteredStep(): void
	{
		$form = new Form();
		$form->step('details', new Form());

		self::assertInstanceOf(FormStep::class, $form->getStep('details'));
	}

	public function testGetStepReturnsNullForUnknownName(): void
	{
		self::assertNull((new Form())->getStep('unknown'));
	}

	// -----------------------------------------------------------
	// Form::validate() skip_steps
	// -----------------------------------------------------------

	public function testValidateSkipStepsDoesNotValidateStepFields(): void
	{
		$form = new Form();
		$form->field('name')->required(true);

		$sub = new Form();
		$sub->field('bio')->required(true); // required but absent from unsafe_fd
		$form->step('profile', $sub);

		// skip_steps=true: 'bio' is not checked even though it is required in the step
		$clean = $form->validate($this->makeFormData(['name' => 'Alice']), null, skip_steps: true);

		self::assertSame('Alice', $clean->get('name'));
		self::assertFalse($clean->has('bio'));
	}

	public function testValidateWithoutSkipStepsEnforcesStepRequiredFields(): void
	{
		$form = new Form();
		$form->field('name')->required(true);

		$sub = new Form();
		$sub->field('bio')->required(true);
		$form->step('profile', $sub);

		$this->expectException(InvalidFormException::class);

		$form->validate($this->makeFormData(['name' => 'Alice']));
	}

	public function testValidateWithStepsSucceedsWhenAllFieldsPresent(): void
	{
		$form = new Form();
		$form->field('name')->required(true);

		$sub = new Form();
		$sub->field('bio')->required(true);
		$form->step('profile', $sub);

		// step sub-form has no prefix, so 'bio' is the field ref (not 'profile.bio')
		$clean = $form->validate($this->makeFormData(['name' => 'Alice', 'bio' => 'Hello']));

		self::assertSame('Alice', $clean->get('name'));
		self::assertSame('Hello', $clean->get('bio'));
	}

	// -----------------------------------------------------------
	// Form::validate() conditional steps via FormStep::isEnabled()
	// -----------------------------------------------------------

	public function testConditionalStepSkippedWhenConditionFalse(): void
	{
		$form = new Form();
		$form->field('type')->required(true);

		$sub  = new Form();
		$sub->field('extra')->required(true);
		$step = $form->step('advanced', $sub);
		$step->if()->eq('type', 'advanced');

		// type=basic -> step disabled -> 'extra' not checked
		$clean = $form->validate($this->makeFormData(['type' => 'basic']));

		self::assertSame('basic', $clean->get('type'));
		self::assertFalse($clean->has('extra'));
	}

	public function testConditionalStepEnforcedWhenConditionTrue(): void
	{
		$form = new Form();
		$form->field('type')->required(true);

		$sub  = new Form();
		$sub->field('extra')->required(true);
		$step = $form->step('advanced', $sub);
		$step->if()->eq('type', 'advanced');

		$this->expectException(InvalidFormException::class);

		// type=advanced -> step active -> 'extra' is required but missing
		$form->validate($this->makeFormData(['type' => 'advanced']));
	}

	// -----------------------------------------------------------
	// Form::validate() dynamic steps
	// -----------------------------------------------------------

	public function testDynamicStepReceivesCleanedFormData(): void
	{
		$form = new Form();
		$form->field('mode')->required(true);

		$form->dynamicStep('dyn', static function (FormData $fd) {
			$f = new Form();

			if ('verbose' === $fd->get('mode')) {
				$f->field('detail')->required(true);
			}

			return $f;
		});

		// mode=verbose requires 'detail' but it is missing
		$this->expectException(InvalidFormException::class);
		$form->validate($this->makeFormData(['mode' => 'verbose']));
	}

	public function testDynamicStepNoRequiredFieldWhenModeSimple(): void
	{
		$form = new Form();
		$form->field('mode')->required(true);

		$form->dynamicStep('dyn', static function (FormData $fd) {
			$f = new Form();

			if ('verbose' === $fd->get('mode')) {
				$f->field('detail')->required(true);
			}

			return $f;
		});

		$clean = $form->validate($this->makeFormData(['mode' => 'simple']));
		self::assertSame('simple', $clean->get('mode'));
	}

	// -----------------------------------------------------------
	// Form::getVersion() reflects registered steps
	// -----------------------------------------------------------

	public function testVersionChangesAfterStepAdded(): void
	{
		$form   = new Form();
		$form->field('name');
		$before = $form->getVersion();

		$form->step('details', new Form());
		$after = $form->getVersion();

		self::assertNotSame($before, $after);
	}

	// -----------------------------------------------------------
	// helpers
	// -----------------------------------------------------------

	private function makeFormData(array $data): FormData
	{
		return new FormData($data);
	}
}
