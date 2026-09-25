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
use OZONE\Core\Forms\AsyncValue;
use OZONE\Core\Forms\Fieldset;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormDataClean;
use OZONE\Core\Forms\FormValidationContext;
use OZONE\Core\Forms\RuleSet;
use PHPUnit\Framework\TestCase;

/**
 * Class FieldsetTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Forms\Fieldset
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

		self::assertArrayHasKey('address.street', $fieldset->getFields());
		self::assertArrayHasKey('address.city', $fieldset->getFields());
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

		self::assertTrue($fieldset->isEnabled($this->ctx([])));
	}

	public function testIsEnabledReturnsTrueWhenConditionPasses(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'details', static function (Fieldset $fs): void {});
		$fieldset->if()->eq('type', 'advanced');

		self::assertTrue($fieldset->isEnabled($this->ctx(['type' => 'advanced'])));
	}

	public function testIsEnabledReturnsFalseWhenConditionFails(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'details', static function (Fieldset $fs): void {});
		$fieldset->if()->eq('type', 'advanced');

		self::assertFalse($fieldset->isEnabled($this->ctx(['type' => 'simple'])));
	}

	// -----------------------------------------------------------------------
	// build
	// -----------------------------------------------------------------------

	public function testBuildReturnsThisForStaticWhenEnabled(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'address', static function (Fieldset $fs): void {});
		$built    = $fieldset->build($this->ctx([]));

		self::assertSame($fieldset, $built);
	}

	public function testBuildReturnsNullWhenConditionFails(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'details', static function (Fieldset $fs): void {});
		$fieldset->if()->eq('type', 'advanced');

		self::assertNull($fieldset->build($this->ctx(['type' => 'simple'])));
	}

	public function testBuildCallsDynamicFactoryWithContext(): void
	{
		$parent       = new Form();
		$received_ctx = null;
		$fieldset     = Fieldset::dynamic(
			$parent,
			'dyn',
			static function (FormValidationContext $ctx) use ($parent, &$received_ctx): Fieldset {
				$received_ctx = $ctx;

				return Fieldset::static($parent, 'dyn', static function (Fieldset $f): void {});
			}
		);

		$ctx   = $this->ctx(['key' => 'val']);
		$built = $fieldset->build($ctx);

		self::assertNotNull($built);
		self::assertSame($ctx, $received_ctx);
	}

	public function testBuildDynamicFactoryMustReturnFieldsetInstance(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::dynamic($parent, 'bad', static fn (FormValidationContext $ctx) => new Form());

		$this->expectException(RuntimeException::class);
		$fieldset->build($this->ctx([]));
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

		$ctx = $this->ctx(['address' => ['street' => 'Main St']]);
		$fieldset->validate($ctx);

		self::assertSame('Main St', $ctx->getCleanFormData()->get('address.street'));
	}

	public function testValidateMissingRequiredFieldThrows(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'address', static function (Fieldset $fs): void {
			$fs->field('street')->required(true);
		});

		$this->expectException(InvalidFormException::class);
		$fieldset->validate($this->ctx([]));
	}

	public function testValidateRunsPostValidationEnsureRules(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'pw', static function (Fieldset $fs): void {
			$fs->field('password');
			$fs->field('confirm');
			$fs->ensure()->eq('pw.password', 'pw.confirm');
		});

		$ctx = $this->ctx(['pw' => ['password' => 'abc', 'confirm' => 'xyz']]);
		$this->expectException(InvalidFormException::class);
		$fieldset->validate($ctx);
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
		// Its rules are those of the fieldset its factory builds, unknown until then.
		self::assertNull($arr['expect']);
		self::assertNull($arr['ensure']);
	}

	public function testToArraySendsTheFieldsetsOwnRules(): void
	{
		$parent   = new Form('order');
		$fieldset = Fieldset::static($parent, 'address', static function (Fieldset $fs): void {
			$fs->string('street');
			$fs->string('city');
			$fs->expect()->neq('address.street', 'nowhere', 'NO_SUCH_STREET');
			$fs->ensure()->neq('address.city', AsyncValue::secret(static fn (): string => 'closed'));
		});

		$arr = \json_decode((string) \json_encode($fieldset->toArray()), true);

		self::assertSame('order.address.@expect[0]', $arr['expect'][0]['ref']);
		self::assertSame('unsafe', $arr['expect'][0]['data_type']);
		self::assertSame('nowhere', $arr['expect'][0]['rules'][0]['value']);
		// A server-only set is announced by its ref alone, as the form's are.
		self::assertSame([['ref' => 'order.address.@ensure[0]', '$secret' => true]], $arr['ensure']);
	}

	public function testToArrayIncludesCondition(): void
	{
		$parent   = new Form();
		$fieldset = Fieldset::static($parent, 'details', static function (Fieldset $fs): void {});
		$fieldset->if()->eq('type', 'advanced');
		$arr = $fieldset->toArray();

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
		$form->dynamicFieldset('dyn', static function (FormValidationContext $ctx) use ($form): Fieldset {
			$cleaned = $ctx->getCleanFormData();

			return Fieldset::static($form, 'dyn', static function (Fieldset $fs) use ($cleaned): void {
				if ('verbose' === $cleaned->get('mode')) {
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
		$form->dynamicFieldset('dyn', static function (FormValidationContext $ctx) use ($form): Fieldset {
			$cleaned = $ctx->getCleanFormData();

			return Fieldset::static($form, 'dyn', static function (Fieldset $fs) use ($cleaned): void {
				if ('verbose' === $cleaned->get('mode')) {
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

	/**
	 * Builds a validation context seeded with $data on both sides.
	 */
	private function ctx(array $data): FormValidationContext
	{
		return new FormValidationContext(new FormData($data), new FormDataClean($data));
	}
}
