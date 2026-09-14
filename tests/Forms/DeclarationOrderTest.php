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

use Gobl\DBAL\Types\TypeString;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Fieldset;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormDataClean;
use OZONE\Core\Forms\RuleSet;
use OZONE\Core\Forms\TypesSwitcher;
use PHPUnit\Framework\TestCase;

/**
 * Class DeclarationOrderTest.
 *
 * A condition reads the cleaned store, which fills in validation order: one that
 * reads a field validated after it would always see null, so validation throws.
 *
 * @internal
 *
 * @covers \OZONE\Core\Forms\FormValidationContext
 */
final class DeclarationOrderTest extends TestCase
{
	public function testConditionOnAnEarlierFieldIsAllowed(): void
	{
		$form = new Form();
		$form->string('type', true);
		$form->string('extra')->if()->eq('type', 'pro');

		self::assertSame('x', $form->validate(new FormData(['type' => 'pro', 'extra' => 'x']))->get('extra'));
	}

	public function testConditionOnALaterFieldThrows(): void
	{
		$form = new Form();
		$form->string('extra')->if()->eq('type', 'pro');
		$form->string('type', true);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('"extra" has a condition on "type"');

		$form->validate(new FormData(['type' => 'pro', 'extra' => 'x']));
	}

	public function testConditionOnItselfThrows(): void
	{
		$form = new Form();
		$form->string('extra')->if()->isNotNull('extra');

		$this->expectException(RuntimeException::class);

		$form->validate(new FormData(['extra' => 'x']));
	}

	public function testConditionOnAFieldOutsideTheFormIsAllowed(): void
	{
		// e.g. a value accumulated by an earlier wizard step.
		$form = new Form();
		$form->string('extra')->if()->eq('plan', 'pro');

		$clean = $form->validate(new FormData(['extra' => 'x']), new FormDataClean(['plan' => 'pro']));

		self::assertSame('x', $clean->get('extra'));
	}

	public function testSwitcherConditionOnALaterFieldThrows(): void
	{
		$form = new Form();
		$form->switcher('doc_number')->configureType(static fn (TypesSwitcher $s) => $s
			->when(static fn (RuleSet $rs) => $rs->eq('doc_type', 'passport'), new TypeString())
			->otherwise(new TypeString()));
		$form->string('doc_type', true);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('"doc_number" has a condition on "doc_type"');

		$form->validate(new FormData(['doc_type' => 'passport', 'doc_number' => 'X1']));
	}

	public function testFieldsetConditionOnARootFieldIsAllowedWhateverTheCodeOrder(): void
	{
		// Root fields are validated before any fieldset.
		$form = new Form();
		$form->fieldset('pro', static function (Fieldset $fs): void {
			$fs->string('vat');
		})->if()->eq('type', 'pro');
		$form->string('type', true);

		$clean = $form->validate(new FormData(['type' => 'pro', 'pro' => ['vat' => 'FR1']]));

		self::assertSame('FR1', $clean->get('pro.vat'));
	}

	public function testFieldsetConditionOnALaterFieldsetThrows(): void
	{
		$form = new Form();
		$form->fieldset('a', static function (Fieldset $fs): void {
			$fs->string('x');
		})->if()->eq('b.y', 'on');
		$form->fieldset('b', static function (Fieldset $fs): void {
			$fs->string('y');
		});

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('"a" has a condition on "b.y"');

		$form->validate(new FormData(['b' => ['y' => 'on']]));
	}

	public function testConditionOnAFieldOfASkippedFieldsetIsAllowed(): void
	{
		$form = new Form();
		$form->string('type', true);
		$form->fieldset('a', static function (Fieldset $fs): void {
			$fs->string('x');
		})->if()->eq('type', 'pro');
		$form->fieldset('b', static function (Fieldset $fs): void {
			$fs->string('y');
		})->if()->eq('a.x', 'on');

		// `a` is skipped, so `a.x` is absent for good and `b` is simply disabled.
		$clean = $form->validate(new FormData(['type' => 'basic', 'b' => ['y' => 'v']]));

		self::assertFalse($clean->has('b.y'));
	}
}
