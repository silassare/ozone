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

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Forms\AsyncValue;
use OZONE\Core\Forms\Fieldset;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormDataClean;
use PHPUnit\Framework\TestCase;

/**
 * Class FormValidationTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Forms\Form
 */
final class FormValidationTest extends TestCase
{
	public function testExpectPassesWhenConditionHoldsOnRawData(): void
	{
		$form = new Form();
		$form->expect()->eq('plan', 'enterprise');

		$result = $form->validate(new FormData(['plan' => 'enterprise']));

		self::assertInstanceOf(FormData::class, $result);
	}

	public function testExpectThrowsWhenConditionFailsOnRawData(): void
	{
		$form = new Form();
		$form->expect()->eq('plan', 'enterprise', 'PLAN_REQUIRED');

		$this->expectException(InvalidFormException::class);
		$this->expectExceptionMessage('PLAN_REQUIRED');

		$form->validate(new FormData(['plan' => 'basic']));
	}

	public function testExpectRunsBeforeFieldValidation(): void
	{
		$form = new Form();
		$form->field('name')->required(true);
		// expect() fails — no 'flag' key in input
		$form->expect()->isNotNull('flag', 'FLAG_REQUIRED');

		// 'name' is also missing but expect() should fire first
		try {
			$form->validate(new FormData([]));
			self::fail('Expected InvalidFormException');
		} catch (InvalidFormException $e) {
			self::assertSame('FLAG_REQUIRED', $e->getMessage());
		}
	}

	public function testExpectDoesNotRunAfterFieldValidation(): void
	{
		$form = new Form();
		$form->field('name')->required(true);
		$form->expect()->isNotNull('flag');

		// expect() passes, but required 'name' is missing -> field validation throws
		$this->expectException(InvalidFormException::class);
		$this->expectExceptionMessage('OZ_FORM_MISSING_REQUIRED_FIELD');

		$form->validate(new FormData(['flag' => 'yes']));
	}

	public function testEnsurePassesWhenConditionHoldsOnCleanData(): void
	{
		$form = new Form();
		$pw   = $form->field('password');
		$cpw  = $form->field('password_confirm');
		$form->ensure()->eq($pw, $cpw);

		$result = $form->validate(new FormData([
			'password'         => 'secret',
			'password_confirm' => 'secret',
		]));

		self::assertInstanceOf(FormData::class, $result);
	}

	public function testEnsureThrowsWhenConditionFailsOnCleanData(): void
	{
		$form = new Form();
		$pw   = $form->field('password');
		$cpw  = $form->field('password_confirm');
		$form->ensure()->eq($pw, $cpw, 'PASSWORDS_MUST_MATCH');

		$this->expectException(InvalidFormException::class);
		$this->expectExceptionMessage('PASSWORDS_MUST_MATCH');

		$form->validate(new FormData([
			'password'         => 'secret',
			'password_confirm' => 'wrong',
		]));
	}

	public function testEnsureRunsAfterFieldValidation(): void
	{
		$form = new Form();
		$form->field('name')->required(true);
		$form->ensure()->isNotNull('name', 'NAME_ENSURE_FAILED');

		// required 'name' is missing -> field validation fires first, not ensure()
		try {
			$form->validate(new FormData([]));
			self::fail('Expected InvalidFormException');
		} catch (InvalidFormException $e) {
			self::assertSame('OZ_FORM_MISSING_REQUIRED_FIELD', $e->getMessage());
		}
	}

	public function testToArrayIncludesExpectEntries(): void
	{
		$form = new Form();
		$form->expect()->eq('plan', 'enterprise');

		$arr = $form->toArray();

		self::assertArrayHasKey('expect', $arr);
		self::assertCount(1, $arr['expect']);
		$ruleArr = $arr['expect'][0]->toArray();
		self::assertSame('and', $ruleArr['condition']);
		self::assertSame('plan', $ruleArr['rules'][0]['field_ref']);
		self::assertSame('enterprise', $ruleArr['rules'][0]['value']);
		self::assertArrayNotHasKey('server_only', $ruleArr['rules'][0]);
	}

	public function testToArraySendsServerOnlyExpectAsOpaqueRef(): void
	{
		$form = new Form('checkout');
		$form->expect()->eq('plan', AsyncValue::secret(static fn() => 'enterprise'));

		$arr = $form->toArray();

		self::assertArrayHasKey('expect', $arr);
		// The rule is still advertised so the client knows it exists and can ask
		// the evaluate endpoint about it by ref, but its operands stay server-side.
		self::assertCount(1, $arr['expect']);

		$rule_arr = $arr['expect'][0]->toArray();

		self::assertSame(['ref' => 'checkout.@expect[0]', '$secret' => true], $rule_arr);
	}

	public function testToArraySendsMixedExpectRuleAsOpaqueRef(): void
	{
		$form = new Form('checkout');
		$rule = $form->expect();
		$rule->eq('plan', 'enterprise');
		$rule->eq('flag', AsyncValue::secret(static fn() => 'ok'));  // makes the whole RuleSet server-only

		$arr = $form->toArray();

		self::assertCount(1, $arr['expect']);

		// All-or-nothing: one server-only rule makes the whole set opaque, so the
		// client gets only the ref and must round-trip to evaluate it.
		self::assertSame(
			['ref' => 'checkout.@expect[0]', '$secret' => true],
			$arr['expect'][0]->toArray()
		);
	}

	public function testToArrayEmptyExpectWhenNoExpectRules(): void
	{
		$form = new Form();

		$arr = $form->toArray();

		self::assertArrayHasKey('expect', $arr);
		self::assertSame([], $arr['expect']);
	}

	public function testToArraySendsEnsureRules(): void
	{
		$form = new Form();
		$form->string('password');
		$form->ensure()->neq('password', 'password', 'TOO_OBVIOUS');
		$form->ensure()->neq('password', AsyncValue::secret(static fn(): string => 'leaked'));

		$arr = \json_decode((string) \json_encode($form->toArray()), true);

		// Sent as expect() is, so a client checks them in the order the server does.
		self::assertSame([], $arr['expect']);
		self::assertSame('@ensure[0]', $arr['ensure'][0]['ref']);
		self::assertSame('cleaned', $arr['ensure'][0]['data_type']);
		self::assertSame('TOO_OBVIOUS', $arr['ensure'][0]['rules'][0]['message']);
		// A server-only one keeps its operands to itself.
		self::assertSame(['ref' => '@ensure[1]', '$secret' => true], $arr['ensure'][1]);
	}

	public function testToArraySendsTheCheckOfADoubleCheck(): void
	{
		$form = new Form();
		$form->string('password')->doubleCheck();

		$arr  = \json_decode((string) \json_encode($form->toArray()), true);
		$rule = $arr['ensure'][0]['rules'][0];

		self::assertSame('password', $rule['field_ref']);
		self::assertSame('eq', $rule['rule']);
		self::assertSame('password_confirm', $rule['target_ref']);
	}

	// region what a refusal names

	public function testATypeRefusalNamesItsField(): void
	{
		$form = new Form('order');
		$form->int('qty');
		$form->fieldset('address', static function (Fieldset $fs): void {
			$fs->int('zip');
		});

		$cases = [
			'order.qty'         => ['order' => ['qty' => 'x']],
			'order.address.zip' => ['order' => ['qty' => 1, 'address' => ['zip' => 'x']]],
		];

		foreach ($cases as $ref => $payload) {
			try {
				$form->validate(new FormData($payload));
				self::fail('The value must be refused.');
			} catch (InvalidFormException $e) {
				self::assertSame($ref, $e->getData()['field']);
			}
		}
	}

	public function testARuleViolationNamesItsSet(): void
	{
		$form = new Form();
		$form->string('a');
		$form->expect()->neq('a', 'raw', 'NOT_RAW');
		$form->ensure()->neq('a', 'clean', 'NOT_CLEAN');

		foreach (['@expect[0]' => 'raw', '@ensure[0]' => 'clean'] as $ref => $value) {
			try {
				$form->validate(new FormData(['a' => $value]));
				self::fail('The value must be refused.');
			} catch (InvalidFormException $e) {
				self::assertSame($ref, $e->getData()['rule']);
				// The set itself stays out of what the client is sent.
				self::assertArrayNotHasKey('_rule', $e->getData());
			}
		}
	}

	// endregion

	// region multiple fields

	public function testAMultipleFieldGivenAListCleansEachEntry(): void
	{
		$form = new Form();
		$form->int('ids')->multiple();

		self::assertSame([1, 2], $form->validate(new FormData(['ids' => ['1', 2]]))->get('ids'));
	}

	/**
	 * @dataProvider provideAMultipleFieldGivenSomethingElseIsAFormErrorCases
	 */
	public function testAMultipleFieldGivenSomethingElseIsAFormError(mixed $value): void
	{
		$form = new Form();
		$form->int('ids')->multiple();

		try {
			$form->validate(new FormData(['ids' => $value]));
			self::fail('A value that is not a list must be refused.');
		} catch (InvalidFormException $e) {
			self::assertSame('OZ_FIELD_SHOULD_BE_A_LIST', $e->getMessage());
			self::assertSame('ids', $e->getData()['field']);
			// The value is kept for the logs, on the type error, and never sent to the client.
			self::assertArrayNotHasKey('_value', $e->getData(true));

			$previous = $e->getPrevious();

			self::assertInstanceOf(TypesInvalidValueException::class, $previous);
			self::assertSame($value, $previous->getData(true)['_value']);
		}
	}

	/**
	 * @return iterable<string, array{mixed}>
	 */
	public static function provideAMultipleFieldGivenSomethingElseIsAFormErrorCases(): iterable
	{
		yield 'a string' => ['1'];

		yield 'an int' => [1];

		yield 'a bool' => [true];

		yield 'null' => [null];
	}

	// endregion

	// region prefilled data

	public function testPrefilledSatisfiesRequiredField(): void
	{
		$form = new Form();
		$form->field('name')->required(true);
		$form->field('email')->required(true);

		$first_unsafe = new FormData(['name' => 'Alice', 'email' => 'alice@example.com']);
		$first_clean  = $form->validate($first_unsafe);

		self::assertSame('Alice', $first_clean->get('name'));
		self::assertSame('alice@example.com', $first_clean->get('email'));

		// second submission omits 'name' — prefilled data satisfies it
		$second_clean = $form->validate(new FormData(['email' => 'bob@example.com']), $first_clean);

		self::assertSame('bob@example.com', $second_clean->get('email'));
		self::assertSame('Alice', $second_clean->get('name'));
	}

	public function testMissingRequiredFieldWithNoPrefilledThrows(): void
	{
		$form = new Form();
		$form->field('name')->required(true);

		$this->expectException(InvalidFormException::class);
		$form->validate(new FormData([]));
	}

	public function testMissingRequiredFieldNotInPrefilledStillThrows(): void
	{
		$form = new Form();
		$form->field('name')->required(true);
		$form->field('email')->required(true);

		$prefilled = new FormDataClean();
		$prefilled->set('name', 'Alice');
		// 'email' is required but missing from both input and prefilled

		$this->expectException(InvalidFormException::class);
		$form->validate(new FormData([]), $prefilled);
	}

	public function testPrefilledValueOverriddenByNewSubmission(): void
	{
		$form = new Form();
		$form->field('name')->required(true);

		$prefilled = new FormDataClean();
		$prefilled->set('name', 'Alice');

		$clean = $form->validate(new FormData(['name' => 'Bob']), $prefilled);
		self::assertSame('Bob', $clean->get('name'));
	}

	// endregion
}
