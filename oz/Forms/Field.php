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
	 * @var callable|TypeInterface|TypesSwitcher
	 */
	protected $validator;

	private bool $hide = false;

	/**
	 * Field constructor.
	 *
	 * @param string                                    $name
	 * @param null|callable|TypeInterface|TypesSwitcher $validator
	 * @param bool                                      $required
	 * @param null|\OZONE\OZ\Forms\FormRule             $only_if
	 */
	public function __construct(
		protected string $name,
		TypeInterface|TypesSwitcher|callable|null $validator,
		protected bool $required = false,
		protected ?FormRule $only_if = null
	) {
		$this->validator = $validator ?? new TypeString();
	}

	/**
	 * @param \OZONE\OZ\Forms\FormData $fd
	 *
	 * @return bool
	 */
	public function isEnabled(FormData $fd): bool
	{
		if (null === $this->only_if) {
			return true;
		}

		return $this->only_if->check($fd);
	}

	/**
	 * @param bool $hide
	 *
	 * @return $this
	 */
	public function hidden(bool $hide = true): static
	{
		$this->hide = $hide;

		return $this;
	}

	/**
	 * @param bool $required
	 *
	 * @return $this
	 */
	public function required(bool $required = true): static
	{
		$this->required = $required;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return callable|TypeInterface
	 */
	public function getValidator(): callable|TypeInterface
	{
		return $this->validator;
	}

	/**
	 * @param callable|TypeInterface|TypesSwitcher $validator
	 */
	public function setValidator(callable|TypeInterface|TypesSwitcher $validator): void
	{
		$this->validator = $validator;
	}

	/**
	 * @return bool
	 */
	public function isRequired(): bool
	{
		return $this->required;
	}

	/**
	 * @return bool
	 */
	public function isHidden(): bool
	{
		return $this->hide;
	}

	/**
	 * Validate a given value.
	 *
	 * @param mixed                    $value
	 * @param \OZONE\OZ\Forms\FormData $cleaned_fd
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 *
	 * @return mixed
	 */
	public function validate(mixed $value, FormData $cleaned_fd): mixed
	{
		if (\is_callable($this->validator)) {
			$v = $this->validator;

			return $v($value, $cleaned_fd);
		}

		$type = $this->validator;

		if ($type instanceof TypesSwitcher) {
			$type = $this->validator->getType($cleaned_fd);
		}

		return $type->validate($value);
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		if ($this->validator instanceof TypeInterface) {
			$type = $this->validator->getCleanOptions();
		} elseif ($this->validator instanceof TypesSwitcher) {
			$type = $this->validator->toArray();
		} else /* if (is_callable($this->validator)) */ {
			$type = (new TypeString())->getCleanOptions();
		}

		return [
			'name'     => $this->name,
			'type'     => $type,
			'required' => $this->required,
			'hidden'   => $this->hide,
			'only_if'  => $this->only_if?->toArray(),
		];
	}
}
