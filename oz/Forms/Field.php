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
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Field.
 */
class Field implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	/**
	 * @var \Gobl\DBAL\Types\Interfaces\TypeInterface|\OZONE\Core\Forms\TypesSwitcher
	 */
	protected TypeInterface|TypesSwitcher $t_type;

	/**
	 * @var null|callable(mixed, \OZONE\Core\Forms\FormValidationContext):mixed
	 */
	protected $t_validator;
	protected string $t_name;
	protected bool $t_hide          = false;
	protected bool $t_required      = false;
	protected ?FormRule $t_if       = null;

	/**
	 * Field constructor.
	 *
	 * @param string                           $name
	 * @param null|TypeInterface|TypesSwitcher $type
	 * @param bool                             $required
	 * @param null|\OZONE\Core\Forms\FormRule  $if
	 */
	public function __construct(
		string $name,
		null|TypeInterface|TypesSwitcher $type = null,
		bool $required = false,
		?FormRule $if = null
	) {
		$this->t_name     = $name;
		$this->t_type     = $type ?? new TypeString();
		$this->t_required = $required;
		$this->t_if       = $if;
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
		$this->t_name = $name;

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

		return $this;
	}

	/**
	 * Set the field visibility condition.
	 *
	 * @return FormRule
	 */
	public function if(): FormRule
	{
		if (!isset($this->t_if)) {
			$this->t_if = new FormRule();
		}

		return $this->t_if;
	}

	/**
	 * Set the field type.
	 *
	 * @param \Gobl\DBAL\Types\Interfaces\TypeInterface|\OZONE\Core\Forms\TypesSwitcher $type
	 *
	 * @return $this
	 */
	public function type(TypeInterface|TypesSwitcher $type): static
	{
		$this->t_type = $type;

		return $this;
	}

	/**
	 * Set the field validator.
	 *
	 * @param callable(mixed, \OZONE\Core\Forms\FormValidationContext):mixed $validator
	 *
	 * @return $this
	 */
	public function validator(callable $validator): static
	{
		$this->t_validator = $validator;

		return $this;
	}

	/**
	 * Check if the field is enabled.
	 *
	 * @param FormValidationContext $fvc
	 *
	 * @return bool
	 */
	public function isEnabled(FormValidationContext $fvc): bool
	{
		if (null === $this->t_if) {
			return true;
		}

		return $this->t_if->check($fvc);
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
	 * Validate a given value.
	 *
	 * @param mixed                 $value
	 * @param FormValidationContext $fvc
	 *
	 * @return mixed
	 *
	 * @throws TypesInvalidValueException
	 */
	public function validate(mixed $value, FormValidationContext $fvc): mixed
	{
		$type = $this->t_type;

		if ($type instanceof TypesSwitcher) {
			$type = $this->t_type->getType($fvc);
		}

		$value = $type->validate($value);

		if (isset($this->t_validator)) {
			$value = \call_user_func($this->t_validator, $value, $fvc);
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'name'     => $this->t_name,
			'type'     => $this->t_type->toArray(),
			'required' => $this->t_required,
			'hidden'   => $this->t_hide,
			'if'       => $this->t_if?->toArray(),
		];
	}
}
