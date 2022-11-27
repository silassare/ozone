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
	 * @param bool                  $is_field
	 *
	 * @return $this
	 */
	public function eq(string $field, int|string|float|bool $value, bool $is_field = false): self
	{
		return $this->add($field, 'eq', $value, $is_field);
	}

	/**
	 * @param string                $field
	 * @param bool|float|int|string $value
	 * @param bool                  $is_field
	 *
	 * @return $this
	 */
	public function neq(string $field, int|string|float|bool $value, bool $is_field = false): self
	{
		return $this->add($field, 'neq', $value, $is_field);
	}

	/**
	 * @param string           $field
	 * @param float|int|string $value
	 * @param bool             $is_field
	 *
	 * @return $this
	 */
	public function gt(string $field, int|string|float $value, bool $is_field = false): self
	{
		return $this->add($field, 'gt', $value, $is_field);
	}

	/**
	 * @param string           $field
	 * @param float|int|string $value
	 * @param bool             $is_field
	 *
	 * @return $this
	 */
	public function gte(string $field, int|string|float $value, bool $is_field = false): self
	{
		return $this->add($field, 'gte', $value, $is_field);
	}

	/**
	 * @param string           $field
	 * @param float|int|string $value
	 * @param bool             $is_field
	 *
	 * @return $this
	 */
	public function lt(string $field, int|string|float $value, bool $is_field = false): self
	{
		return $this->add($field, 'lt', $value, $is_field);
	}

	/**
	 * @param string           $field
	 * @param float|int|string $value
	 * @param bool             $is_field
	 *
	 * @return $this
	 */
	public function lte(string $field, int|string|float $value, bool $is_field = false): self
	{
		return $this->add($field, 'lte', $value, $is_field);
	}

	/**
	 * @param string $field
	 * @param array  $value
	 * @param bool   $is_field
	 *
	 * @return $this
	 */
	public function in(string $field, array $value, bool $is_field = false): self
	{
		return $this->add($field, 'in', $value, $is_field);
	}

	/**
	 * @param string $field
	 * @param array  $value
	 * @param bool   $is_field
	 *
	 * @return $this
	 */
	public function notIn(string $field, array $value, bool $is_field = false): self
	{
		return $this->add($field, 'not_in', $value, $is_field);
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

			if ($item['is_field']){
				$b = $fd->get($b);
			}

			$ok = match ($item['rule']) {
				'eq'          => ($a === $b),
				'neq'         => ($a !== $b),
				'gt'          => ($a > $b),
				'gte'         => ($a >= $b),
				'lt'          => ($a < $b),
				'lte'         => ($a <= $b),
				'in'          => \is_array($b) && \in_array($a, $b, true),
				'not_in'      => !\is_array($b) || !\in_array($a, $b, true),
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
	 * @param string     $field
	 * @param string     $rule
	 * @param null|mixed $value
	 * @param bool       $is_field
	 *
	 * @return $this
	 */
	private function add(string $field, string $rule, mixed $value = null, bool $is_field = false): self
	{
		$this->rules[] = [
			'field' => $field,
			'is_field' => $is_field,
			'rule'  => $rule,
			'value' => $value,
		];

		return $this;
	}
}
