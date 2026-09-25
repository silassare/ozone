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

namespace OZONE\Core\Forms;

use BackedEnum;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\TypeEnum;
use Gobl\DBAL\Types\TypeString;
use Gobl\DBAL\Types\Utils\TypeUtils;
use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Enums\RuleSetCondition;
use OZONE\Core\Forms\Enums\RuleSetDataType;
use OZONE\Core\Forms\Interfaces\FieldContainerInterface;
use OZONE\Core\Forms\Interfaces\FrontendTypeOptionsInterface;
use OZONE\Core\Lang\I18n;
use OZONE\Core\Lang\I18nMessage;
use OZONE\Core\Utils\Utils;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Interfaces\MetaCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;
use PHPUtils\Traits\MetaCapableTrait;

/**
 * Class Field.
 */
final class Field implements ArrayCapableInterface, MetaCapableInterface
{
	use ArrayCapableTrait;
	use MetaCapableTrait;

	/**
	 * @var TypeInterface|TypesSwitcher
	 */
	private TypeInterface|TypesSwitcher $t_type;

	/**
	 * @var null|callable(mixed, FormValidationContext):mixed
	 */
	private $t_validator;
	private string $t_name;
	private ?I18nMessage $t_label             = null;
	private ?I18nMessage $t_description       = null;
	private ?I18nMessage $t_help              = null;
	private bool $t_hide                      = false;
	private bool $t_required                  = false;
	private bool $t_multiple                  = false;
	private ?RuleSet $t_if                    = null;
	private FieldContainerInterface $t_parent;
	private ?self $t_double_check = null;

	/**
	 * Field constructor.
	 *
	 * @param FieldContainerInterface          $parent the container (Form or Fieldset) this field belongs to
	 * @param string                           $name
	 * @param null|TypeInterface|TypesSwitcher $type
	 */
	public function __construct(
		FieldContainerInterface $parent,
		string $name,
		TypeInterface|TypesSwitcher|null $type = null
	) {
		$this->t_parent = $parent;
		$this->t_type   = $type ?? new TypeString();

		$this->name($name);
	}

	/**
	 * Deep-copies the mutable state a field owns.
	 *
	 * Without this, {@see Form::merge()} would hand the same Field instance to
	 * every bundle built from a long-lived form, so a mutation through one
	 * bundle would leak into the source definition and into every other bundle.
	 *
	 * The double-check companion is deliberately not cloned here: it is a
	 * sibling entry in the same container, so the container re-links it after
	 * cloning the whole set (see {@see AbstractFieldContainer::mergeContainerState()}).
	 */
	public function __clone()
	{
		$this->t_type = clone $this->t_type;

		if (null !== $this->t_if) {
			$this->t_if = clone $this->t_if;
		}
	}

	/**
	 * Re-links this field's double-check companion after a bulk clone.
	 *
	 * @internal
	 */
	public function relinkDoubleCheck(?self $confirm): void
	{
		$this->t_double_check = $confirm;
	}

	/**
	 * Returns the double-check companion field, if any.
	 *
	 * @internal
	 */
	public function getDoubleCheck(): ?self
	{
		return $this->t_double_check;
	}

	/**
	 * Re-binds this field to a container.
	 *
	 * @internal
	 */
	public function rebind(FieldContainerInterface $parent): void
	{
		$this->t_parent = $parent;
	}

	/**
	 * Gets the field reference.
	 */
	public function getRef(): string
	{
		return $this->t_parent->getRef($this->t_name);
	}

	/**
	 * Adds a double check field for this field.
	 *
	 * @return $this
	 */
	public function doubleCheck(): static
	{
		// syncWithDoubleCheck() is called from type(), required(), and multiple(), keeping the confirm
		// field's type/required/multiple in sync whenever the primary field's settings change later.
		if (null === $this->t_double_check) {
			$confirm = $this->t_parent->field($this->t_name . '_confirm');

			$this->t_parent->ensure()
				->eq($this, $confirm, I18n::m('OZ_FIELD_SHOULD_HAVE_SAME_VALUE', [
					'field'         => $this->t_name,
					'field_confirm' => $confirm->t_name,
				]));

			$this->t_double_check = $confirm;

			$this->syncWithDoubleCheck();
		}

		return $this;
	}

	/**
	 * Define the field name.
	 *
	 * @param string $name
	 *
	 * @return $this
	 */
	public function name(string $name): static
	{
		FormUtils::assertValidFieldName($name);

		$this->t_name = $name;

		return $this;
	}

	/**
	 * Define the field label.
	 *
	 * @param null|I18nMessage|string $label
	 *
	 * @return $this
	 */
	public function label(I18nMessage|string|null $label): static
	{
		$this->t_label = null === $label ? null : ($label instanceof I18nMessage ? $label : I18n::m($label));

		return $this;
	}

	/**
	 * Define the field description.
	 *
	 * @param null|I18nMessage|string $description
	 *
	 * @return $this
	 */
	public function description(I18nMessage|string|null $description): static
	{
		$this->t_description = null === $description ? null : (
			$description instanceof I18nMessage ? $description : I18n::m($description)
		);

		return $this;
	}

	/**
	 * Define the field help text.
	 *
	 * @param null|I18nMessage|string $help
	 *
	 * @return $this
	 */
	public function help(I18nMessage|string|null $help): static
	{
		$this->t_help = null === $help ? null : ($help instanceof I18nMessage ? $help : I18n::m($help));

		return $this;
	}

	/**
	 * Define whether the field should be hidden or not.
	 *
	 * @param bool $hide
	 *
	 * @return $this
	 */
	public function hidden(bool $hide = true): static
	{
		$this->t_hide = $hide;

		return $this;
	}

	/**
	 * Define whether the field is required or not.
	 *
	 * @param bool $required
	 *
	 * @return $this
	 */
	public function required(bool $required = true): static
	{
		$this->t_required = $required;

		$this->syncWithDoubleCheck();

		return $this;
	}

	/**
	 * Define whether the field is multiple or not.
	 *
	 * @param bool $multiple
	 *
	 * @return $this
	 */
	public function multiple(bool $multiple = true): static
	{
		$this->t_multiple = $multiple;

		$this->syncWithDoubleCheck();

		return $this;
	}

	/**
	 * Set the field visibility condition.
	 *
	 * @return RuleSet
	 */
	public function if(): RuleSet
	{
		if (!isset($this->t_if)) {
			$this->t_if = RuleSet::create(
				RuleSetCondition::AND,
				RuleSetDataType::CLEANED,
				$this->getRef() . '@if'
			);
		}

		return $this->t_if;
	}

	/**
	 * Set the field type.
	 *
	 * @param TypeInterface|TypesSwitcher $type
	 *
	 * @return $this
	 */
	public function type(TypeInterface|TypesSwitcher $type): static
	{
		$this->t_type = $type;

		if ($type instanceof TypesSwitcher) {
			$type->bindTo($this);
		}

		$this->syncWithDoubleCheck();

		return $this;
	}

	/**
	 * Configures the field's current type in place, keeping the chain on the field.
	 *
	 * Lets type-level and field-level configuration share one chain after a typed
	 * helper, which returns the field:
	 *
	 * ```php
	 * $form->string('name', true)
	 *     ->configureType(static fn (TypeString $t) => $t->min(2)->max(60))
	 *     ->label('Full name');
	 * ```
	 *
	 * The callback's return value is ignored; it mutates the type it receives. A
	 * callback typed for a different type class fails with a TypeError.
	 *
	 * @template T of TypeInterface|TypesSwitcher
	 *
	 * @param callable(T):mixed $configure
	 *
	 * @return $this
	 */
	public function configureType(callable $configure): static
	{
		// T is chosen by the caller's callback; a mismatch surfaces as a TypeError.
		/** @psalm-suppress InvalidArgument */
		$configure($this->t_type);

		return $this;
	}

	/**
	 * Set the field validator.
	 *
	 * The callable receives the cleaned value and the full
	 * {@see FormValidationContext}, so it can read either the raw payload or the
	 * data cleaned so far.
	 *
	 * @param callable(mixed, FormValidationContext):mixed $validator
	 *
	 * @return $this
	 */
	public function validator(callable $validator): static
	{
		$this->t_validator = $validator;

		return $this;
	}

	/**
	 * Gets the field visibility condition, if any.
	 *
	 * Returns null when no condition has been defined (field is always visible).
	 * Use this to inspect the condition without creating one as a side effect.
	 *
	 * @return null|RuleSet
	 */
	public function getIf(): ?RuleSet
	{
		return $this->t_if;
	}

	/**
	 * Check if the field is enabled.
	 *
	 * @param FormValidationContext $ctx
	 *
	 * @return bool
	 */
	public function isEnabled(FormValidationContext $ctx): bool
	{
		if (null === $this->t_if) {
			return true;
		}

		return $this->t_if->check($ctx);
	}

	/**
	 * Gets the field name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->t_name;
	}

	/**
	 * Gets the field label.
	 *
	 * @return null|I18nMessage|string
	 */
	public function getLabel(): I18nMessage|string|null
	{
		return $this->t_label;
	}

	/**
	 * Gets the field description.
	 *
	 * @return null|I18nMessage|string
	 */
	public function getDescription(): I18nMessage|string|null
	{
		return $this->t_description;
	}

	/**
	 * Gets the field help text.
	 *
	 * @return null|I18nMessage|string
	 */
	public function getHelp(): I18nMessage|string|null
	{
		return $this->t_help;
	}

	/**
	 * Gets the field type.
	 *
	 * @return TypeInterface|TypesSwitcher
	 */
	public function getType(): TypeInterface|TypesSwitcher
	{
		return $this->t_type;
	}

	/**
	 * @return bool
	 */
	public function isRequired(): bool
	{
		return $this->t_required;
	}

	/**
	 * Check if the field is hidden.
	 *
	 * @return bool
	 */
	public function isHidden(): bool
	{
		return $this->t_hide;
	}

	/**
	 * Check if the field is multiple.
	 *
	 * @return bool
	 */
	public function isMultiple(): bool
	{
		return $this->t_multiple;
	}

	/**
	 * Validate a given value.
	 *
	 * The raw $value is what gets validated; $ctx only supplies the surrounding
	 * data that a {@see TypesSwitcher} or a custom validator may consult.
	 *
	 * @param mixed                 $value
	 * @param FormValidationContext $ctx
	 *
	 * @return mixed
	 *
	 * @throws TypesInvalidValueException
	 */
	public function validate(mixed $value, FormValidationContext $ctx): mixed
	{
		$value = $this->validateType($value, $ctx);

		if (isset($this->t_validator)) {
			$value = \call_user_func($this->t_validator, $value, $ctx);
		}

		return $value;
	}

	/**
	 * Re-checks an already-cleaned value against this field's current type.
	 *
	 * Used for values replayed from a resume cache: they were cleaned during an
	 * earlier request, so the type constraints must be re-asserted against the
	 * field as it is defined *now* rather than trusted.
	 *
	 * The custom {@see self::validator()} is deliberately not re-run: it already
	 * ran when the value was first cleaned, and it is not required to be
	 * idempotent, so running it again could transform the value a second time.
	 *
	 * @param mixed                 $value a value produced by an earlier {@see self::validate()}
	 * @param FormValidationContext $ctx
	 *
	 * @return mixed
	 *
	 * @throws TypesInvalidValueException when the stored value no longer satisfies the field
	 */
	public function revalidateStored(mixed $value, FormValidationContext $ctx): mixed
	{
		return $this->validateType($value, $ctx);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array{
	 *  ref: string,
	 *  name: string,
	 *  type: array<string, mixed>|TypesSwitcher,
	 *  label: ?I18nMessage|string,
	 *  description: ?I18nMessage|string,
	 *  help: ?I18nMessage|string,
	 *  required: bool,
	 *  hidden: bool,
	 *  multiple: bool,
	 *  if: ?RuleSet
	 * }
	 *
	 * @throws TypesException
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'ref' 	       => $this->getRef(),
			'name'        => $this->t_name,
			'type'        => $this->t_type instanceof TypeInterface ? self::frontendType($this->t_type) : $this->t_type,
			'label'       => $this->t_label,
			'description' => $this->t_description,
			'help'        => $this->t_help,
			'required'    => $this->t_required,
			'hidden'      => $this->t_hide,
			// A list of values of `type`: without it a client takes the field for a single value.
			'multiple'    => $this->t_multiple,
			'if'          => $this->t_if,
		];
	}

	/**
	 * What a client is told of a type: the clean type ({@see self::cleanType()}), and for an enum its
	 * cases (`enum_cases`, `[{name, value}]` in declaration order), since the class name alone tells a
	 * client nothing it can render or check.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws TypesException
	 */
	public static function frontendType(TypeInterface $type): array
	{
		$out = self::cleanType($type)->toArray();

		if ($type instanceof TypeEnum) {
			$out['enum_cases'] = \array_map(
				static fn(BackedEnum $case) => ['name' => $case->name, 'value' => $case->value],
				$type->getEnumClass()::cases()
			);
		}

		// The rules a type takes from the settings, resolved now, so a client checks what the server
		// will check. They are resolved here, at discovery, rather than copied into the client:
		// a copy is stale as soon as a project changes a setting.
		if ($type instanceof FrontendTypeOptionsInterface) {
			$out = \array_replace($out, $type->frontendOptions());
		}

		return $out;
	}

	/**
	 * Creates a clean type instance for frontend consumption by stripping out
	 * any meta information that may contain sensitive data.
	 *
	 * @param TypeInterface $type
	 *
	 * @return TypeInterface
	 *
	 * @throws TypesException
	 */
	public static function cleanType(TypeInterface $type): TypeInterface
	{
		$type_array = $type->toArray();

		unset($type_array['meta']);

		$tn = TypeUtils::getTypeInstance($type->getName(), $type_array);

		if (null === $tn) {
			throw new RuntimeException(
				'Failed to clean type for frontend: unable to reconstruct type instance from array representation'
			);
		}

		Utils::safeFrontendMeta($type, $tn);

		return $tn;
	}

	/**
	 * Runs the type-level validation only, resolving a {@see TypesSwitcher} when needed.
	 *
	 * Unlike {@see self::validate()}, the custom {@see self::validator()} is not run.
	 * Used for replayed values ({@see self::revalidateStored()}) and to clean a step
	 * that is still being filled without triggering validator side effects.
	 *
	 * @throws TypesInvalidValueException
	 */
	public function validateType(mixed $value, FormValidationContext $ctx): mixed
	{
		$type = $this->t_type;

		if ($type instanceof TypesSwitcher) {
			$type = $this->t_type->getType($ctx);
		}

		if ($this->t_multiple) {
			if (!\is_array($value)) {
				// The value goes in the data, under a private key: the data of an exception is an array.
				throw new TypesInvalidValueException('OZ_FIELD_SHOULD_BE_A_LIST', [
					'field'  => $this->getRef(),
					'_value' => $value,
				]);
			}

			$list = [];

			foreach ($value as $entry) {
				$list[] = $type->validate($entry)->getCleanValue();
			}

			return $list;
		}

		return $type->validate($value)->getCleanValue();
	}

	/**
	 * Synchronizes the main field properties with the double check field, if it exists.
	 */
	private function syncWithDoubleCheck(): void
	{
		if (null !== $this->t_double_check) {
			$this->t_double_check->t_type      = $this->t_type;
			$this->t_double_check->t_multiple  = $this->t_multiple;
			$this->t_double_check->t_required  = $this->t_required;
		}
	}
}
