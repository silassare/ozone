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

use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Forms\DynamicValue;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use PHPUnit\Framework\TestCase;

/**
 * Class FormValidationTest.
 *
 * @internal
 *
 * @coversNothing
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
		self::assertFalse($ruleArr['rules'][0]['server_only']);
	}

	public function testToArrayExcludesServerOnlyExpectEntries(): void
	{
		$form = new Form();
		// DynamicValue -> isServerOnly() = true -> excluded from toArray()
		$form->expect()->eq('plan', new DynamicValue(static fn (FormData $fd) => 'enterprise'));

		$arr = $form->toArray();

		self::assertArrayHasKey('expect', $arr);
		// server-only rule sets are excluded entirely
		self::assertCount(0, $arr['expect']);
	}

	public function testToArrayExcludesMixedExpectRuleWhenServerOnly(): void
	{
		$form = new Form();
		$rule = $form->expect();
		$rule->eq('plan', 'enterprise');
		$rule->eq('flag', new DynamicValue(static fn (FormData $fd) => 'ok'));  // makes whole RuleSet server-only

		$arr = $form->toArray();

		// The entire RuleSet is excluded because isServerOnly() is true (all-or-nothing)
		self::assertCount(0, $arr['expect']);
	}

	public function testToArrayEmptyExpectWhenNoExpectRules(): void
	{
		$form = new Form();

		$arr = $form->toArray();

		self::assertArrayHasKey('expect', $arr);
		self::assertSame([], $arr['expect']);
	}

	public function testToArrayDoesNotExposeEnsureRules(): void
	{
		$form = new Form();
		$form->ensure()->eq('password', 'password_confirm');

		$arr = $form->toArray();

		// ensure rules are server-side only — must not appear in the serialized form
		self::assertArrayNotHasKey('ensure', $arr);
		self::assertSame([], $arr['expect']);
	}
}
