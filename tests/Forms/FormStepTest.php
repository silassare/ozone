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
	// helpers
	// -----------------------------------------------------------

	private function makeFormData(array $data): FormData
	{
		return new FormData($data);
	}
}
