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

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\TypeString;
use Gobl\DBAL\Types\Utils\TypeUtils;
use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Interfaces\FieldContainerInterface;
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
	 * @var null|callable(mixed, FormData):mixed
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
	 * Field destructor.
	 */
	public function __destruct()
	{
		unset($this->t_parent, $this->t_type);
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
		$this->t_description = null === $description ? null : ($description instanceof I18nMessage ? $description : I18n::m($description));

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
			$this->t_if = new RuleSet();
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

		$this->syncWithDoubleCheck();

		return $this;
	}

	/**
	 * Set the field validator.
	 *
	 * @param callable(mixed, FormData):mixed $validator
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
	 * @param FormData $fd
	 *
	 * @return bool
	 */
	public function isEnabled(FormData $fd): bool
	{
		if (null === $this->t_if) {
			return true;
		}

		return $this->t_if->check($fd);
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
	 * @param mixed    $value
	 * @param FormData $fd
	 *
	 * @return mixed
	 *
	 * @throws TypesInvalidValueException
	 */
	public function validate(mixed $value, FormData $fd): mixed
	{
		$type = $this->t_type;

		if ($type instanceof TypesSwitcher) {
			$type = $this->t_type->getType($fd);
		}

		if ($this->t_multiple) {
			if (!\is_array($value)) {
				throw new TypesInvalidValueException('Expected an array', $value);
			}

			$list = [];

			foreach ($value as $entry) {
				$list[] = $type->validate($entry)->getCleanValue();
			}

			$value = $list;
		} else {
			$value = $type->validate($value)->getCleanValue();
		}

		if (isset($this->t_validator)) {
			$value = \call_user_func($this->t_validator, $value, $fd);
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array{
	 *  ref: string,
	 *  name: string,
	 *  type: TypeInterface|TypesSwitcher,
	 *  label: ?I18nMessage|string,
	 *  description: ?I18nMessage|string,
	 *  help: ?I18nMessage|string,
	 *  required: bool,
	 *  hidden: bool,
	 *  if: ?RuleSet
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'ref' 	       => $this->getRef(),
			'name'        => $this->t_name,
			'type'        => $this->t_type instanceof TypeInterface ? self::cleanType($this->t_type) : $this->t_type,
			'label'       => $this->t_label,
			'description' => $this->t_description,
			'help'        => $this->t_help,
			'required'    => $this->t_required,
			'hidden'      => $this->t_hide,
			'if'          => $this->t_if,
		];
	}

	/**
	 * Creates a clean type instance for frontend consumption by stripping out
	 * any meta information that may contain sensitive data.
	 *
	 * @param TypeInterface $type
	 *
	 * @return TypeInterface
	 */
	public static function cleanType(TypeInterface $type): TypeInterface
	{
		$type_array = $type->toArray();

		unset($type_array['meta']);

		$tn = TypeUtils::getTypeInstance($type->getName(), $type_array);

		if (null === $tn) {
			throw new RuntimeException('Failed to clean type for frontend: unable to reconstruct type instance from array representation');
		}

		Utils::safeFrontendMeta($type, $tn);

		return $tn;
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
