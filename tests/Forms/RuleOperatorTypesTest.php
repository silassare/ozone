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

use Gobl\DBAL\Operator;
use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeString;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Enums\RuleOperator;
use OZONE\Core\Forms\Fieldset;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\RuleSet;
use OZONE\Core\Forms\TypesSwitcher;
use PHPUnit\Framework\TestCase;

/**
 * Class RuleOperatorTypesTest.
 *
 * A rule on a cleaned value uses an operator the field's type allows, as a Gobl filter does.
 *
 * @internal
 *
 * @covers \OZONE\Core\Forms\Form
 * @covers \OZONE\Core\Forms\RuleSet
 */
final class RuleOperatorTypesTest extends TestCase
{
	public function testEveryRuleOperatorIsAGoblOperator(): void
	{
		foreach (RuleOperator::cases() as $case) {
			self::assertInstanceOf(Operator::class, Operator::from($case->value));
		}
	}

	public function testAnOrderOnABooleanIsRefused(): void
	{
		$form = new Form();
		$form->bool('active');
		$form->string('note')->if()->gt('active', 1);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('the rule "gt" on "active" is not allowed, a "bool" field accepts');

		$form->validate(new FormData(['active' => true, 'note' => 'x']));
	}

	public function testTheMessageNamesTheRuleSetAndWhatTheTypeAccepts(): void
	{
		$form = new Form();
		$form->bool('active');
		$form->string('note')->if()->lt('active', 1);

		try {
			$form->validate(new FormData([]));
			self::fail('expected a RuntimeException');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('"note@if"', $e->getMessage());
			self::assertStringContainsString('"eq", "neq"', $e->getMessage());
		}
	}

	public function testDiscoveryIsRefusedToo(): void
	{
		$form = new Form();
		$form->bool('active');
		$form->string('note')->if()->gte('active', 1);

		$this->expectException(RuntimeException::class);

		$form->toArray();
	}

	public function testWhatATypeAllowsIsAccepted(): void
	{
		$form = new Form();
		$form->bool('active');
		$form->int('age');
		$form->string('kind');
		$form->string('a')->if()->eq('active', true);
		$form->string('b')->if()->neq('active', false);
		$form->string('c')->if()->gt('age', 17);
		$form->string('d')->if()->in('kind', ['x', 'y']);
		$form->string('e')->if()->notIn('kind', ['z']);

		self::assertSame([], $form->validate(new FormData([]))->getData());
		self::assertIsArray($form->toArray());
	}

	public function testNullChecksAreAllowedOnAnyType(): void
	{
		// A field left out of the payload reads as null whatever its type: not a nullable one.
		$form = new Form();
		$form->bool('active');
		$form->string('note')->if()->isNotNull('active');
		$form->string('other')->if()->isNull('active');

		self::assertIsArray($form->toArray());
		self::assertIsArray($form->validate(new FormData(['active' => true]))->getData());
	}

	public function testARuleOnTheRawPayloadIsNotChecked(): void
	{
		// `expect()` reads what was submitted, not the type's clean value.
		$form = new Form();
		$form->bool('active');
		$form->expect()->gt('active', 0);

		self::assertIsArray($form->toArray());
	}

	public function testAnEnsureRuleReadsCleanedValuesAndIsChecked(): void
	{
		$form = new Form();
		$form->bool('active');
		$form->ensure()->gt('active', 0);

		$this->expectException(RuntimeException::class);

		$form->toArray();
	}

	public function testANestedGroupIsChecked(): void
	{
		$form = new Form();
		$form->bool('active');
		$form->int('age');
		$form->string('note')->if()->or(static function (RuleSet $rs): void {
			$rs->eq('age', 3)->lt('active', 1);
		});

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('the rule "lt" on "active"');

		$form->toArray();
	}

	public function testAFieldsetConditionIsChecked(): void
	{
		$form = new Form();
		$form->bool('active');
		$form->fieldset('extra', static function (Fieldset $fs): void {
			$fs->field('x');
		})->if()->gt('active', 1);

		$this->expectException(RuntimeException::class);

		$form->toArray();
	}

	public function testAFieldOfAFieldsetIsKnownByItsQualifiedRef(): void
	{
		$form = new Form();
		$form->string('note')->if()->gt('extra.flag', 1);
		$form->fieldset('extra', static function (Fieldset $fs): void {
			$fs->field('flag')->type(new TypeInt());
		});

		// An integer accepts an order: nothing to refuse.
		self::assertIsArray($form->toArray());

		$strict = new Form();
		$strict->string('note')->if()->gt('extra.on', 1);
		$strict->fieldset('extra', static function (Fieldset $fs): void {
			$fs->field('on')->type(new TypeBool());
		});

		$this->expectException(RuntimeException::class);

		$strict->toArray();
	}

	public function testAFieldWhoseTypeIsPickedLaterIsNotChecked(): void
	{
		$form = new Form();
		$form->string('kind');
		$form->switcher('value')->configureType(static fn (TypesSwitcher $s) => $s
			->when(static fn (RuleSet $rs) => $rs->eq('kind', 'a'), new TypeString())
			->otherwise(new TypeInt()));
		$form->string('note')->if()->gt('value', 1);

		self::assertIsArray($form->toArray());
	}

	public function testARuleOnAFieldTheFormDoesNotKnowIsNotChecked(): void
	{
		$form = new Form();
		$form->string('note')->if()->gt('elsewhere', 1);

		self::assertIsArray($form->toArray());
	}

	public function testTheBranchesOfASwitcherAreChecked(): void
	{
		$form = new Form();
		$form->bool('flag');
		$form->switcher('value')->configureType(static fn (TypesSwitcher $s) => $s
			->when(static fn (RuleSet $rs) => $rs->gt('flag', 1), new TypeString())
			->otherwise(new TypeInt()));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('the rule "gt" on "flag"');

		$form->toArray();
	}
}
