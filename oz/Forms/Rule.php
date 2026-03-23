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
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Rule.
 *
 * An immutable predicate that compares a single form field value using one
 * of the supported {@see RuleOperator} operators.  Instances are created
 * internally by {@see RuleSet} and should not be instantiated directly.
 *
 * Two comparison modes:
 *  - Value   : field ref vs. a scalar / {@see DynamicValue}
 *  - Cross-field: field ref vs. another field ref (target_ref)
 *
 * A rule is considered server-only when its comparison value is a
 * {@see DynamicValue} (resolved at runtime, never exposed to the client).
 */
final class Rule implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	/**
	 * Whether this rule must be evaluated server-side only.
	 *
	 * True when {@see $value} is a {@see DynamicValue}.
	 */
	public readonly bool $server_only;

	/**
	 * Rule constructor.
	 *
	 * @param string                  $field_ref  the field to evaluate
	 * @param RuleOperator            $operator   the comparison operator
	 * @param mixed                   $value      the right-hand scalar or {@see DynamicValue}
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
		$this->server_only = null === $target_ref && $value instanceof DynamicValue;
	}

	/**
	 * Evaluates this rule against the given form data.
	 *
	 * @param FormData $fd
	 *
	 * @return bool
	 */
	public function evaluate(FormData $fd): bool
	{
		if (!$fd->has($this->field_ref)) {
			oz_trace(\sprintf(
				'[RuleSet] field_ref "%s" is absent from FormData during rule evaluation (operator: %s). '
					. 'Absent fields evaluate to null. Verify the field name and the evaluation context (UNSAFE vs CLEANED).',
				$this->field_ref,
				$this->operator->value
			));
		}

		$a = $fd->get($this->field_ref);

		if (null !== $this->target_ref) {
			$b = $fd->get($this->target_ref);
		} elseif ($this->value instanceof DynamicValue) {
			$b = $this->value->getValue($fd);
		} else {
			$b = $this->value;
		}

		return match ($this->operator) {
			RuleOperator::EQ          => ($a === $b),
			RuleOperator::NEQ         => ($a !== $b),
			RuleOperator::GT          => ($a > $b),
			RuleOperator::GTE         => ($a >= $b),
			RuleOperator::LT          => ($a < $b),
			RuleOperator::LTE         => ($a <= $b),
			RuleOperator::IN          => \is_array($b) && \in_array($a, $b, true),
			RuleOperator::NOT_IN      => !\is_array($b) || !\in_array($a, $b, true),
			RuleOperator::IS_NULL     => null === $a,
			RuleOperator::IS_NOT_NULL => null !== $a,
		};
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
			$arr['value'] = $this->value instanceof DynamicValue
				? $this->value->toArray()
				: $this->value;
		}

		return $arr;
	}
}
