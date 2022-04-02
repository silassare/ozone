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

use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class FormRule.
 */
class FormRule implements ArrayCapableInterface
{
	use ArrayCapableTrait;
	private array $rules = [];

	/**
	 * @param string                $field
	 * @param bool|float|int|string $value
	 *
	 * @return $this
	 */
	public function eq(string $field, int|string|float|bool $value): self
	{
		return $this->add($field, 'eq', $value);
	}

	/**
	 * @param string                $field
	 * @param bool|float|int|string $value
	 *
	 * @return $this
	 */
	public function neq(string $field, int|string|float|bool $value): self
	{
		return $this->add($field, 'neq', $value);
	}

	/**
	 * @param string           $field
	 * @param float|int|string $value
	 *
	 * @return $this
	 */
	public function gt(string $field, int|string|float $value): self
	{
		return $this->add($field, 'gt', $value);
	}

	/**
	 * @param string           $field
	 * @param float|int|string $value
	 *
	 * @return $this
	 */
	public function gte(string $field, int|string|float $value): self
	{
		return $this->add($field, 'gte', $value);
	}

	/**
	 * @param string           $field
	 * @param float|int|string $value
	 *
	 * @return $this
	 */
	public function lt(string $field, int|string|float $value): self
	{
		return $this->add($field, 'lt', $value);
	}

	/**
	 * @param string           $field
	 * @param float|int|string $value
	 *
	 * @return $this
	 */
	public function lte(string $field, int|string|float $value): self
	{
		return $this->add($field, 'lte', $value);
	}

	/**
	 * @param string $field
	 * @param array  $value
	 *
	 * @return $this
	 */
	public function in(string $field, array $value): self
	{
		return $this->add($field, 'in', $value);
	}

	/**
	 * @param string $field
	 * @param array  $value
	 *
	 * @return $this
	 */
	public function notIn(string $field, array $value): self
	{
		return $this->add($field, 'not_in', $value);
	}

	/**
	 * @param string $field
	 *
	 * @return $this
	 */
	public function isNull(string $field): self
	{
		return $this->add($field, 'is_null');
	}

	/**
	 * @param string $field
	 *
	 * @return $this
	 */
	public function isNotNull(string $field): self
	{
		return $this->add($field, 'is_not_null');
	}

	/**
	 * @param \OZONE\OZ\Forms\FormData $fd
	 *
	 * @return bool
	 */
	public function check(FormData $fd): bool
	{
		foreach ($this->rules as $item) {
			$a = $fd->get($item['field']);
			$b = $item['value'];

			$ok = match ($item['rule']) {
				'eq'          => ($a === $b),
				'neq'         => ($a !== $b),
				'gt'          => ($a > $b),
				'gte'         => ($a >= $b),
				'lt'          => ($a < $b),
				'lte'         => ($a <= $b),
				'in'          => \in_array($a, $b, true),
				'not_in'      => !\in_array($a, $b, true),
				'is_null'     => null === $a,
				'is_not_null' => null !== $a,
			};

			if (!$ok) {
				return false;
			}
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return $this->rules;
	}

	/**
	 * @param string $field
	 * @param string $rule
	 * @param        $value
	 *
	 * @return $this
	 */
	private function add(string $field, string $rule, $value = null): self
	{
		$this->rules[] = [
			'field' => $field,
			'rule'  => $rule,
			'value' => $value,
		];

		return $this;
	}
}
