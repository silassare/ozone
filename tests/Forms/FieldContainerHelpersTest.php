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

use Gobl\DBAL\Types\TypeDate;
use Gobl\DBAL\Types\TypeString;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Forms\Field;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\TypesSwitcher;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * Class FieldContainerHelpersTest.
 *
 * Tests for the typed field helpers: they return the {@see Field}
 * and reach the type through {@see Field::configureType()}.
 *
 * @internal
 *
 * @covers \OZONE\Core\Forms\Field
 * @covers \OZONE\Core\Forms\Traits\FieldContainerHelpersTrait
 */
final class FieldContainerHelpersTest extends TestCase
{
	public function testHelperReturnsTheRegisteredField(): void
	{
		$form  = new Form();
		$field = $form->string('name', true);

		self::assertSame($form->getField('name'), $field);
		self::assertTrue($field->isRequired());
		self::assertInstanceOf(TypeString::class, $field->getType());
	}

	public function testFieldLevelConfigChainsFromHelper(): void
	{
		$form = new Form();
		$form->string('name')->label('Full name')->hidden();

		$field = $form->getField('name');

		self::assertNotNull($field);
		self::assertTrue($field->isHidden());
		self::assertNotNull($field->getLabel());
	}

	public function testConfigureTypeMutatesTheFieldType(): void
	{
		$form = new Form();
		$form->string('name', true)
			->configureType(static fn (TypeString $t) => $t->min(2)->max(5));

		self::assertSame('Bob', $form->validate(new FormData(['name' => 'Bob']))->get('name'));

		$this->expectException(InvalidFormException::class);

		$form->validate(new FormData(['name' => 'far too long']));
	}

	public function testConfigureTypeWithMismatchedTypeFails(): void
	{
		$this->expectException(TypeError::class);

		(new Form())->int('age')->configureType(static fn (TypeString $t) => $t);
	}

	public function testConfigureTypeReachesTypesSwitcher(): void
	{
		$field = (new Form())->switcher('doc')
			->configureType(static fn (TypesSwitcher $s) => $s->otherwise(new TypeString()));

		self::assertInstanceOf(TypesSwitcher::class, $field->getType());
	}

	public function testTimestampHelperSetsTimestampFormat(): void
	{
		$type = (new Form())->timestamp('at')->getType();

		self::assertInstanceOf(TypeDate::class, $type);
		self::assertSame('timestamp', $type->getOption('format'));
	}
}
