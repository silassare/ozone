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

use OZONE\Core\Lang\I18nMessage;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class FormRule.
 */
class FormRule implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	private array                   $rules   = [];
	private null|string|I18nMessage $message = null;

	/**
	 * @param \OZONE\Core\Forms\Field|string                     $field
	 * @param null|bool|float|int|\OZONE\Core\Forms\Field|string $value
	 * @param null|\OZONE\Core\Lang\I18nMessage|string           $message
	 *
	 * @return $this
	 */
	public function eq(string|Field $field, Field|null|int|string|float|bool $value, null|string|I18nMessage $message = null): self
	{
		return $this->add($field, 'eq', $value, $message);
	}

	/**
	 * @param \OZONE\Core\Forms\Field|string                     $field
	 * @param null|bool|float|int|\OZONE\Core\Forms\Field|string $value
	 * @param null|\OZONE\Core\Lang\I18nMessage|string           $message
	 *
	 * @return $this
	 */
	public function neq(string|Field $field, Field|null|int|string|float|bool $value, null|string|I18nMessage $message = null): self
	{
		return $this->add($field, 'neq', $value, $message);
	}

	/**
	 * @param \OZONE\Core\Forms\Field|string           $field
	 * @param float|int|\OZONE\Core\Forms\Field|string $value
	 * @param null|\OZONE\Core\Lang\I18nMessage|string $message
	 *
	 * @return $this
	 */
	public function gt(string|Field $field, Field|int|string|float $value, null|string|I18nMessage $message = null): self
	{
		return $this->add($field, 'gt', $value, $message);
	}

	/**
	 * @param \OZONE\Core\Forms\Field|string           $field
	 * @param float|int|\OZONE\Core\Forms\Field|string $value
	 * @param null|\OZONE\Core\Lang\I18nMessage|string $message
	 *
	 * @return $this
	 */
	public function gte(string|Field $field, Field|int|string|float $value, null|string|I18nMessage $message = null): self
	{
		return $this->add($field, 'gte', $value, $message);
	}

	/**
	 * @param \OZONE\Core\Forms\Field|string           $field
	 * @param float|int|\OZONE\Core\Forms\Field|string $value
	 * @param null|\OZONE\Core\Lang\I18nMessage|string $message
	 *
	 * @return $this
	 */
	public function lt(string|Field $field, Field|int|string|float $value, null|string|I18nMessage $message = null): self
	{
		return $this->add($field, 'lt', $value, $message);
	}

	/**
	 * @param \OZONE\Core\Forms\Field|string           $field
	 * @param float|int|\OZONE\Core\Forms\Field|string $value
	 * @param null|\OZONE\Core\Lang\I18nMessage|string $message
	 *
	 * @return $this
	 */
	public function lte(string|Field $field, Field|int|string|float $value, null|string|I18nMessage $message = null): self
	{
		return $this->add($field, 'lte', $value, $message);
	}

	/**
	 * @param \OZONE\Core\Forms\Field|string           $field
	 * @param array|\OZONE\Core\Forms\Field            $value
	 * @param null|\OZONE\Core\Lang\I18nMessage|string $message
	 *
	 * @return $this
	 */
	public function in(string|Field $field, Field|array $value, null|string|I18nMessage $message = null): self
	{
		return $this->add($field, 'in', $value, $message);
	}

	/**
	 * @param \OZONE\Core\Forms\Field|string           $field
	 * @param array|\OZONE\Core\Forms\Field            $value
	 * @param null|\OZONE\Core\Lang\I18nMessage|string $message
	 *
	 * @return $this
	 */
	public function notIn(string|Field $field, Field|array $value, null|string|I18nMessage $message = null): self
	{
		return $this->add($field, 'not_in', $value, $message);
	}

	/**
	 * @param \OZONE\Core\Forms\Field|string           $field
	 * @param null|\OZONE\Core\Lang\I18nMessage|string $message
	 *
	 * @return $this
	 */
	public function isNull(string|Field $field, null|string|I18nMessage $message = null): self
	{
		return $this->add($field, 'is_null', null, $message);
	}

	/**
	 * @param \OZONE\Core\Forms\Field|string           $field
	 * @param null|\OZONE\Core\Lang\I18nMessage|string $message
	 *
	 * @return $this
	 */
	public function isNotNull(string|Field $field, null|string|I18nMessage $message = null): self
	{
		return $this->add($field, 'is_not_null', null, $message);
	}

	/**
	 * @param \OZONE\Core\Forms\FormData $fd
	 *
	 * @return bool
	 */
	public function check(FormData $fd): bool
	{
		foreach ($this->rules as $entry) {
			$a = $fd->get($entry['field']);
			$b = $entry['value'];

			if ($entry['is_field']) {
				$b = $fd->get($b);
			}

			$this->message = $entry['message'];

			$ok = match ($entry['rule']) {
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
	 * @return null|\OZONE\Core\Lang\I18nMessage|string
	 */
	public function getErrorMessage(): string|I18nMessage|null
	{
		return $this->message;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return $this->rules;
	}

	/**
	 * @param \OZONE\Core\Forms\Field|string           $field
	 * @param string                                   $rule
	 * @param null|mixed                               $value
	 * @param null|\OZONE\Core\Lang\I18nMessage|string $message
	 *
	 * @return $this
	 */
	private function add(
		string|Field $field,
		string $rule,
		mixed $value = null,
		null|string|I18nMessage $message = null
	): self {
		$is_field = $value instanceof Field;

		$this->rules[] = [
			'field'    => $field instanceof Field ? $field->getName() : $field,
			'is_field' => $is_field,
			'rule'     => $rule,
			'value'    => $is_field ? null : $value,
			'message'  => $message,
		];

		return $this;
	}
}
