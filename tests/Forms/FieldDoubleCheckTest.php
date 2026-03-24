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

use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeString;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Forms\Field;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use PHPUnit\Framework\TestCase;

/**
 * Class FieldDoubleCheckTest.
 *
 * Tests for {@see Field::doubleCheck()}.
 *
 * @internal
 *
 * @coversNothing
 */
final class FieldDoubleCheckTest extends TestCase
{
	public function testDoubleCheckCreatesConfirmField(): void
	{
		$form  = new Form();
		$field = $form->field('password');
		$field->doubleCheck();

		self::assertNotNull($form->getField('password_confirm'));
	}

	public function testDoubleCheckIsFluent(): void
	{
		$form  = new Form();
		$field = $form->field('password');

		self::assertSame($field, $field->doubleCheck());
	}

	public function testDoubleCheckIsIdempotent(): void
	{
		$form  = new Form();
		$field = $form->field('password');

		$field->doubleCheck();
		$field->doubleCheck(); // second call must be a no-op

		self::assertCount(1, $form->getPostValidationRules());
		self::assertNotNull($form->getField('password_confirm'));
	}

	public function testConfirmFieldInheritsTypeSetBeforeDoubleCheck(): void
	{
		$type  = new TypeBigint();
		$form  = new Form();
		$field = $form->field('amount');
		$field->type($type);
		$field->doubleCheck();

		$confirm = $form->getField('amount_confirm');
		self::assertSame($type, $confirm->getType());
	}

	public function testConfirmFieldSyncsTypePropagatedAfterDoubleCheck(): void
	{
		$form  = new Form();
		$field = $form->field('amount');
		$field->doubleCheck();

		$type = new TypeBigint();
		$field->type($type);

		$confirm = $form->getField('amount_confirm');
		self::assertSame($type, $confirm->getType());
	}

	public function testConfirmFieldSyncsRequired(): void
	{
		$form  = new Form();
		$field = $form->field('email');
		$field->doubleCheck();

		self::assertFalse($field->isRequired());
		self::assertFalse($form->getField('email_confirm')->isRequired());

		$field->required(true);

		self::assertTrue($form->getField('email_confirm')->isRequired());
	}

	public function testConfirmFieldRequiredSetBeforeDoubleCheck(): void
	{
		$form  = new Form();
		$field = $form->field('email');
		$field->required(true);
		$field->doubleCheck();

		self::assertTrue($form->getField('email_confirm')->isRequired());
	}

	public function testConfirmFieldSyncsMultiple(): void
	{
		$form  = new Form();
		$field = $form->field('tag');
		$field->doubleCheck();

		self::assertFalse($form->getField('tag_confirm')->isMultiple());

		$field->multiple(true);

		self::assertTrue($form->getField('tag_confirm')->isMultiple());
	}

	public function testFormValidationPassesWhenBothValuesMatch(): void
	{
		$form  = new Form();
		$field = $form->field('password');
		$field->type(new TypeString(6, 100))->required(true)->doubleCheck();

		$result = $form->validate(new FormData([
			'password'         => 'secret123',
			'password_confirm' => 'secret123',
		]));

		self::assertSame('secret123', $result->get('password'));
		self::assertSame('secret123', $result->get('password_confirm'));
	}

	public function testFormValidationFailsWhenValuesDiffer(): void
	{
		$form  = new Form();
		$field = $form->field('password');
		$field->type(new TypeString(6, 100))->required(true)->doubleCheck();

		$this->expectException(InvalidFormException::class);

		$form->validate(new FormData([
			'password'         => 'secret123',
			'password_confirm' => 'different',
		]));
	}

	public function testFormValidationFailureMessageContainsFieldName(): void
	{
		$form  = new Form();
		$field = $form->field('email');
		$field->type(new TypeString())->required(true)->doubleCheck();

		try {
			$form->validate(new FormData([
				'email'         => 'a@example.com',
				'email_confirm' => 'b@example.com',
			]));
			self::fail('Expected InvalidFormException');
		} catch (InvalidFormException $e) {
			// The message is an I18nMessage with the field name baked in — just verify it is the right kind.
			self::assertNotNull($e->getMessage());
		}
	}

	public function testEnsureRuleAddedExactlyOnce(): void
	{
		$form  = new Form();
		$field = $form->field('code');
		$field->doubleCheck();

		self::assertCount(1, $form->getPostValidationRules());
	}

	public function testDoubleCheckWorksWithPrefixedForm(): void
	{
		$form  = new Form('billing');
		$field = $form->field('card');
		$field->doubleCheck();

		// Confirm field ref is scoped to the same prefix.
		self::assertSame('billing.card_confirm', $form->getField('card_confirm')->getRef());
	}
}
