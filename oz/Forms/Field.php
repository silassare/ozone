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

namespace OZONE\OZ\Forms;

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
	 * @var \Gobl\DBAL\Types\Interfaces\TypeInterface|\OZONE\OZ\Forms\TypesSwitcher
	 */
	protected TypeInterface|TypesSwitcher $_type;

	/**
	 * @var callable(mixed, \OZONE\OZ\Forms\FormData):(bool|null)
	 */
	protected $_validator;

	protected string    $_name;
	protected bool      $_hide     = false;
	protected bool      $_required = false;
	protected ?FormRule $_if       = null;

	/**
	 * Field constructor.
	 *
	 * @param string                           $name
	 * @param null|TypeInterface|TypesSwitcher $type
	 * @param bool                             $required
	 * @param null|\OZONE\OZ\Forms\FormRule    $if
	 */
	public function __construct(
		string $name,
		TypeInterface|TypesSwitcher|null $type = null,
		bool $required = false,
		?FormRule $if = null
	) {
		$this->_name     = $name;
		$this->_type     = $type ?? new TypeString();
		$this->_required = $required;
		$this->_if       = $if;
	}

	/**
	 * @param string $name
	 *
	 * @return $this
	 */
	public function name(string $name): static
	{
		$this->_name = $name;

		return $this;
	}

	/**
	 * @param bool $hide
	 *
	 * @return $this
	 */
	public function hidden(bool $hide = true): static
	{
		$this->_hide = $hide;

		return $this;
	}

	/**
	 * @param bool $required
	 *
	 * @return $this
	 */
	public function required(bool $required = true): static
	{
		$this->_required = $required;

		return $this;
	}

	/**
	 * @return FormRule
	 */
	public function if(): FormRule
	{
		$this->_if = new FormRule();

		return $this->_if;
	}

	/**
	 * @param \Gobl\DBAL\Types\Interfaces\TypeInterface|\OZONE\OZ\Forms\TypesSwitcher $type
	 *
	 * @return $this
	 */
	public function type(TypeInterface|TypesSwitcher $type): static
	{
		$this->_type = $type;

		return $this;
	}

	/**
	 * @param callable(mixed, \OZONE\OZ\Forms\FormData):(bool|null) $validator
	 *
	 * @return $this
	 */
	public function validator(callable $validator): static
	{
		$this->_validator = $validator;

		return $this;
	}

	/**
	 * @param \OZONE\OZ\Forms\FormData $fd
	 *
	 * @return bool
	 */
	public function isEnabled(FormData $fd): bool
	{
		if (null === $this->_if) {
			return true;
		}

		return $this->_if->check($fd);
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->_name;
	}

	/**
	 * @return callable|TypeInterface|TypesSwitcher
	 */
	public function getType(): callable|TypesSwitcher|TypeInterface
	{
		return $this->_type;
	}

	/**
	 * @return bool
	 */
	public function isRequired(): bool
	{
		return $this->_required;
	}

	/**
	 * @return bool
	 */
	public function isHidden(): bool
	{
		return $this->_hide;
	}

	/**
	 * Validate a given value.
	 *
	 * @param mixed                    $value
	 * @param \OZONE\OZ\Forms\FormData $cleaned_fd
	 *
	 * @return mixed
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	public function validate(mixed $value, FormData $cleaned_fd): mixed
	{
		$type = $this->_type;

		if ($type instanceof TypesSwitcher) {
			$type = $this->_type->getType($cleaned_fd);
		}

		$value = $type->validate($value);

		if (\is_callable($this->_validator)) {
			$v     = $this->_validator;
			$value = $v($value, $cleaned_fd);
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'name'     => $this->_name,
			'type'     => $this->_type->toArray(),
			'required' => $this->_required,
			'hidden'   => $this->_hide,
			'if'       => $this->_if?->toArray(),
		];
	}
}
