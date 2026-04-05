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
use OZONE\Core\Forms\Fieldset;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\RuleSet;
use PHPUnit\Framework\TestCase;

/**
 * Class FieldsetTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class FieldsetTest extends TestCase
{
	// -----------------------------------------------------------------------
	// Static fieldset creation
	// -----------------------------------------------------------------------

	public function testStaticFieldsetCallbackIsInvokedEagerly(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'address', static function (Fieldset $fs): void {
			$fs->field('street');
			$fs->field('city');
		});

		self::assertArrayHasKey('street', $fieldset->getFields());
		self::assertArrayHasKey('city', $fieldset->getFields());
	}

	public function testStaticFieldsetIsStatic(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'info', static function (Fieldset $fs): void {});

		self::assertTrue($fieldset->isStatic());
		self::assertFalse($fieldset->isDynamic());
	}

	public function testStaticFieldsetWithInvalidNameThrows(): void
	{
		$parent = new Form();

		$this->expectException(InvalidArgumentException::class);
		Fieldset::static($parent, '', static function (Fieldset $fs): void {});
	}

	// -----------------------------------------------------------------------
	// Dynamic fieldset creation
	// -----------------------------------------------------------------------

	public function testDynamicFieldsetIsDynamic(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::dynamic($parent, 'extra', static fn (FormData $fd) => Fieldset::static($parent, 'extra', static function (Fieldset $f): void {}));

		self::assertFalse($fieldset->isStatic());
		self::assertTrue($fieldset->isDynamic());
	}

	public function testDynamicFieldsetHasNoFieldsBeforeBuild(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::dynamic($parent, 'extra', static fn (FormData $fd) => Fieldset::static($parent, 'extra', static function (Fieldset $f): void {}));

		self::assertEmpty($fieldset->getFields());
	}

	// -----------------------------------------------------------------------
	// Legend
	// -----------------------------------------------------------------------

	public function testLegendSetsTheLegend(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'billing', static function (Fieldset $fs): void {});
		$fieldset->legend('Billing Info');

		self::assertNotNull($fieldset->getLegend());
		self::assertSame('Billing Info', $fieldset->getLegend()->getText());
	}

	public function testLegendIsNullByDefault(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'billing', static function (Fieldset $fs): void {});

		self::assertNull($fieldset->getLegend());
	}

	// -----------------------------------------------------------------------
	// getRef
	// -----------------------------------------------------------------------

	public function testGetRefPrefixesFieldWithFieldsetName(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'address', static function (Fieldset $fs): void {});

		self::assertSame('address.street', $fieldset->getRef('street'));
	}

	public function testGetRefPrefixesFieldWithNamedParentForm(): void
	{
		$parent = new Form();
		$parent->name('user');
		$fieldset = Fieldset::static($parent, 'address', static function (Fieldset $fs): void {});

		self::assertSame('user.address.street', $fieldset->getRef('street'));
	}

	// -----------------------------------------------------------------------
	// isEnabled / condition
	// -----------------------------------------------------------------------

	public function testIsEnabledWhenNoCondition(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'address', static function (Fieldset $fs): void {});

		self::assertTrue($fieldset->isEnabled(new FormData([])));
	}

	public function testIsEnabledReturnsTrueWhenConditionPasses(): void
	{
		$parent   = new Form();
		$only_if  = (new RuleSet())->eq('type', 'advanced');
		$fieldset = Fieldset::static($parent, 'details', static function (Fieldset $fs): void {}, $only_if);

		self::assertTrue($fieldset->isEnabled(new FormData(['type' => 'advanced'])));
	}

	public function testIsEnabledReturnsFalseWhenConditionFails(): void
	{
		$parent   = new Form();
		$only_if  = (new RuleSet())->eq('type', 'advanced');
		$fieldset = Fieldset::static($parent, 'details', static function (Fieldset $fs): void {}, $only_if);

		self::assertFalse($fieldset->isEnabled(new FormData(['type' => 'simple'])));
	}

	// -----------------------------------------------------------------------
	// build
	// -----------------------------------------------------------------------

	public function testBuildReturnsThisForStaticWhenEnabled(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'address', static function (Fieldset $fs): void {});
		$built    = $fieldset->build(new FormData([]));

		self::assertSame($fieldset, $built);
	}

	public function testBuildReturnsNullWhenConditionFails(): void
	{
		$parent   = new Form();
		$only_if  = (new RuleSet())->eq('type', 'advanced');
		$fieldset = Fieldset::static($parent, 'details', static function (Fieldset $fs): void {}, $only_if);

		self::assertNull($fieldset->build(new FormData(['type' => 'simple'])));
	}

	public function testBuildCallsDynamicFactoryWithFormData(): void
	{
		$parent      = new Form();
		$received_fd = null;
		$fieldset    = Fieldset::dynamic($parent, 'dyn', static function (FormData $fd) use ($parent, &$received_fd): Fieldset {
			$received_fd = $fd;

			return Fieldset::static($parent, 'dyn', static function (Fieldset $f): void {});
		});

		$fd    = new FormData(['key' => 'val']);
		$built = $fieldset->build($fd);

		self::assertNotNull($built);
		self::assertSame($fd, $received_fd);
	}

	public function testBuildDynamicFactoryMustReturnFieldsetInstance(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::dynamic($parent, 'bad', static fn (FormData $fd) => new Form());

		$this->expectException(RuntimeException::class);
		$fieldset->build(new FormData([]));
	}

	// -----------------------------------------------------------------------
	// validate
	// -----------------------------------------------------------------------

	public function testValidatePopulatesCleanedData(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'address', static function (Fieldset $fs): void {
			$fs->field('street');
		});

		$unsafe  = new FormData(['address' => ['street' => 'Main St']]);
		$cleaned = new FormData();
		$fieldset->validate($unsafe, $cleaned);

		self::assertSame('Main St', $cleaned->get('address.street'));
	}

	public function testValidateMissingRequiredFieldThrows(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'address', static function (Fieldset $fs): void {
			$fs->field('street')->required(true);
		});

		$this->expectException(InvalidFormException::class);
		$fieldset->validate(new FormData([]), new FormData());
	}

	public function testValidateRunsPostValidationEnsureRules(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'pw', static function (Fieldset $fs): void {
			$fs->field('password');
			$fs->field('confirm');
			$fs->ensure()->eq('pw.password', 'pw.confirm');
		});

		$unsafe  = new FormData(['pw' => ['password' => 'abc', 'confirm' => 'xyz']]);
		$this->expectException(InvalidFormException::class);
		$fieldset->validate($unsafe, new FormData());
	}

	// -----------------------------------------------------------------------
	// toArray
	// -----------------------------------------------------------------------

	public function testToArrayStructureForStaticFieldset(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'info', static function (Fieldset $fs): void {
			$fs->field('name');
		});
		$arr = $fieldset->toArray();

		self::assertSame('info', $arr['ref']);
		self::assertSame('info', $arr['name']);
		self::assertSame('static', $arr['type']);
		self::assertIsArray($arr['fields']);
		self::assertNull($arr['legend']);
		self::assertNull($arr['if']);
	}

	public function testToArrayForDynamicFieldsetHasNullFields(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::dynamic($parent, 'dyn', static fn (FormData $fd) => Fieldset::static($parent, 'dyn', static function (Fieldset $f): void {}));
		$arr      = $fieldset->toArray();

		self::assertSame('dynamic', $arr['type']);
		self::assertNull($arr['fields']);
	}

	public function testToArrayIncludesCondition(): void
	{
		$parent   = new Form();
		$only_if  = (new RuleSet())->eq('type', 'advanced');
		$fieldset = Fieldset::static($parent, 'details', static function (Fieldset $fs): void {}, $only_if);
		$arr      = $fieldset->toArray();

		self::assertNotNull($arr['if']);
		self::assertInstanceOf(RuleSet::class, $arr['if']);
	}

	public function testToArrayRefIncludesParentFormName(): void
	{
		$parent = new Form();
		$parent->name('user');
		$fieldset = Fieldset::static($parent, 'address', static function (Fieldset $fs): void {});
		$arr      = $fieldset->toArray();

		self::assertSame('user.address', $arr['ref']);
	}

	// -----------------------------------------------------------------------
	// Form::fieldset / Form::dynamicFieldset integration
	// -----------------------------------------------------------------------

	public function testFormFieldsetRegistersInGetFieldsets(): void
	{
		$form     = new Form();
		$fieldset = $form->fieldset('address', static function (Fieldset $fs): void {});

		self::assertArrayHasKey('address', $form->getFieldsets());
		self::assertSame($fieldset, $form->getFieldsets()['address']);
	}

	public function testFormDynamicFieldsetRegistersInGetFieldsets(): void
	{
		$form     = new Form();
		$fieldset = $form->dynamicFieldset('extra', static fn (FormData $fd) => Fieldset::static($form, 'extra', static function (Fieldset $f): void {}));

		self::assertArrayHasKey('extra', $form->getFieldsets());
		self::assertSame($fieldset, $form->getFieldsets()['extra']);
	}

	public function testGetFieldsetReturnsRegisteredFieldset(): void
	{
		$form = new Form();
		$form->fieldset('billing', static function (Fieldset $fs): void {});

		self::assertInstanceOf(Fieldset::class, $form->getFieldset('billing'));
	}

	public function testGetFieldsetReturnsNullForUnknownName(): void
	{
		self::assertNull((new Form())->getFieldset('unknown'));
	}

	// -----------------------------------------------------------------------
	// Form validate integration with fieldsets
	// -----------------------------------------------------------------------

	public function testValidateShallowDoesNotTraverseFieldsets(): void
	{
		$form = new Form();
		$form->field('name')->required(true);
		$form->fieldset('profile', static function (Fieldset $fs): void {
			$fs->field('bio')->required(true); // required but not checked in shallow mode
		});

		$clean = $form->validate(new FormData(['name' => 'Alice']), null, shallow: true);

		self::assertSame('Alice', $clean->get('name'));
		self::assertFalse($clean->has('profile.bio'));
	}

	public function testValidateTraversesFieldsets(): void
	{
		$form = new Form();
		$form->field('name')->required(true);
		$form->fieldset('profile', static function (Fieldset $fs): void {
			$fs->field('bio')->required(true);
		});

		$clean = $form->validate(new FormData(['name' => 'Alice', 'profile' => ['bio' => 'Hello']]));

		self::assertSame('Alice', $clean->get('name'));
		self::assertSame('Hello', $clean->get('profile.bio'));
	}

	public function testValidateMissingFieldsetRequiredFieldThrows(): void
	{
		$form = new Form();
		$form->field('name')->required(true);
		$form->fieldset('profile', static function (Fieldset $fs): void {
			$fs->field('bio')->required(true);
		});

		$this->expectException(InvalidFormException::class);

		$form->validate(new FormData(['name' => 'Alice']));
	}

	public function testConditionalFieldsetSkippedWhenConditionFalse(): void
	{
		$form = new Form();
		$form->field('type')->required(true);
		$form->fieldset('advanced', static function (Fieldset $fs): void {
			$fs->field('extra')->required(true);
		})->if()->eq('type', 'advanced');

		$clean = $form->validate(new FormData(['type' => 'basic']));

		self::assertSame('basic', $clean->get('type'));
		self::assertFalse($clean->has('advanced.extra'));
	}

	public function testConditionalFieldsetEnforcedWhenConditionTrue(): void
	{
		$form = new Form();
		$form->field('type')->required(true);
		$form->fieldset('advanced', static function (Fieldset $fs): void {
			$fs->field('extra')->required(true);
		})->if()->eq('type', 'advanced');

		$this->expectException(InvalidFormException::class);

		$form->validate(new FormData(['type' => 'advanced']));
	}

	public function testDynamicFieldsetReceivesCleanedFormData(): void
	{
		$form = new Form();
		$form->field('mode')->required(true);
		$form->dynamicFieldset('dyn', static function (FormData $fd) use ($form): Fieldset {
			return Fieldset::static($form, 'dyn', static function (Fieldset $fs) use ($fd): void {
				if ('verbose' === $fd->get('mode')) {
					$fs->field('detail')->required(true);
				}
			});
		});

		$this->expectException(InvalidFormException::class);

		$form->validate(new FormData(['mode' => 'verbose'])); // 'dyn.detail' missing
	}

	public function testDynamicFieldsetNoRequiredFieldWhenModeSimple(): void
	{
		$form = new Form();
		$form->field('mode')->required(true);
		$form->dynamicFieldset('dyn', static function (FormData $fd) use ($form): Fieldset {
			return Fieldset::static($form, 'dyn', static function (Fieldset $fs) use ($fd): void {
				if ('verbose' === $fd->get('mode')) {
					$fs->field('detail')->required(true);
				}
			});
		});

		$clean = $form->validate(new FormData(['mode' => 'simple']));

		self::assertSame('simple', $clean->get('mode'));
	}

	public function testVersionChangesAfterFieldsetAdded(): void
	{
		$form   = new Form();
		$form->field('name');
		$before = $form->getVersion();

		$form->fieldset('details', static function (Fieldset $fs): void {});
		$after = $form->getVersion();

		self::assertNotSame($before, $after);
	}
}
