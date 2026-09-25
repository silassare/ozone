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

use Override;
use OZONE\Core\Forms\Enums\RuleOperator;
use OZONE\Core\Lang\I18nMessage;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Store\Store;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Rule.
 *
 * An immutable predicate that compares a single form field value using one
 * of the supported {@see RuleOperator} operators.  Instances are created
 * internally by {@see RuleSet} and should not be instantiated directly.
 *
 * Two comparison modes:
 *  - Value   : field ref vs. a scalar / {@see AsyncValue}
 *  - Cross-field: field ref vs. another field ref (target_ref)
 *
 * A rule is considered server-only when its comparison value is a
 * {@see AsyncValue} (resolved at runtime, never exposed to the client).
 */
final class Rule implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	/**
	 * Whether this rule must be evaluated server-side only.
	 *
	 * True when {@see $value} is an {@see AsyncValue}.
	 */
	public readonly bool $server_only;

	/**
	 * Rule constructor.
	 *
	 * @param string                  $field_ref  the field to evaluate
	 * @param RuleOperator            $operator   the comparison operator
	 * @param mixed                   $value      the right-hand scalar or {@see AsyncValue}
	 *                                            (ignored when $target_ref is set)
	 * @param null|string             $target_ref field ref to compare against (cross-field mode)
	 * @param null|I18nMessage|string $message    optional failure message
	 */
	public function __construct(
		public readonly string $field_ref,
		public readonly RuleOperator $operator,
		public readonly mixed $value,
		public readonly ?string $target_ref,
		public readonly I18nMessage|string|null $message,
	) {
		$this->server_only = null === $target_ref
			&& $value instanceof AsyncValue
			&& !$value->isClientResolvable();
	}

	/**
	 * Evaluates this rule against the given form data.
	 *
	 * @param Store                 $data the operand source selected by the owning rule set
	 * @param FormValidationContext $ctx  full context, handed to {@see AsyncValue} factories
	 *
	 * @return bool
	 */
	public function evaluate(Store $data, FormValidationContext $ctx): bool
	{
		// An absent field evaluates to null: optional fields are legitimately absent, and
		// reading a field validated later in the same pass is caught by
		// FormValidationContext::assertReadable().
		$a = $data->get($this->field_ref);

		if (null !== $this->target_ref) {
			$b = $data->get($this->target_ref);
		} elseif ($this->value instanceof AsyncValue) {
			$b = $this->value->getValue($ctx);
		} else {
			$b = $this->value;
		}

		return match ($this->operator) {
			RuleOperator::EQ          => self::same($a, $b),
			RuleOperator::NEQ         => !self::same($a, $b),
			RuleOperator::GT          => ($a > $b),
			RuleOperator::GTE         => ($a >= $b),
			RuleOperator::LT          => ($a < $b),
			RuleOperator::LTE         => ($a <= $b),
			RuleOperator::IN          => \is_array($b) && self::contains($b, $a),
			RuleOperator::NOT_IN      => !\is_array($b) || !self::contains($b, $a),
			RuleOperator::IS_NULL     => null === $a,
			RuleOperator::IS_NOT_NULL => null !== $a,
		};
	}

	/**
	 * Whether two operands are the same value (G16).
	 *
	 * Identical (`===`), except that two numbers are compared by value: an int and a float that are
	 * equal are the same number. A float field's clean value is always a float, so `eq('price', 3)`
	 * compared `3.0 === 3` and could never pass; and JSON writes `3.0` as `3`, so a client could not
	 * tell the two apart anyway. `3` and `3.5` stay different, and a number is never the same as a
	 * numeric string or a boolean.
	 */
	private static function same(mixed $a, mixed $b): bool
	{
		if ((\is_int($a) || \is_float($a)) && (\is_int($b) || \is_float($b))) {
			return $a == $b;
		}

		return $a === $b;
	}

	/**
	 * `in_array()`, strict except for numbers, which are compared by value as in {@see self::same()}.
	 *
	 * @param array<array-key, mixed> $list
	 */
	private static function contains(array $list, mixed $a): bool
	{
		foreach ($list as $item) {
			if (self::same($a, $item)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * When in cross-field mode ({@see $target_ref} is set) the `value` key is omitted
	 * and replaced by `target_ref`.  Otherwise `target_ref` is omitted.
	 *
	 * @return array{
	 *  field_ref: string,
	 *  rule: string,
	 *  server_only: bool,
	 *  message: null|I18nMessage|string,
	 *  value?: mixed,
	 *  target_ref?: string
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		$arr = [
			'field_ref'   => $this->field_ref,
			'rule'        => $this->operator->value,
			'server_only' => $this->server_only,
			'message'     => $this->message,
		];

		if (null !== $this->target_ref) {
			$arr['target_ref'] = $this->target_ref;
		} else {
			$arr['value'] = $this->value instanceof AsyncValue
				? $this->value->toArray()
				: $this->value;
		}

		return $arr;
	}
}
