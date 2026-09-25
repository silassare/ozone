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

use Gobl\DBAL\Operator;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Enums\RuleOperator;
use OZONE\Core\Forms\Enums\RuleSetCondition;
use OZONE\Core\Forms\Enums\RuleSetDataType;
use OZONE\Core\Lang\I18nMessage;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Store\Store;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class RuleSet.
 *
 * A chainable, tree-structured predicate evaluated against a data {@see Store} object.
 * Children are {@see Rule} entries or nested {@see RuleSet} groups.
 *
 * Combining modes (set at construction time):
 *  - AND (default): all children must pass.
 *  - OR:            at least one child must pass.
 *
 * Usage:
 * ```php
 * $rs = new RuleSet();
 * $rs->eq('type', 'admin')
 *    ->isNotNull('email')
 *    ->or(function (RuleSet $sub): void {
 *        $sub->eq('status', 'active')->eq('status', 'pending');
 *    });
 * ```
 *
 * The rule set is considered server-only when any descendant {@see Rule}
 * has {@see Rule::$server_only} set to true (i.e. its value is a secret {@see AsyncValue}).
 * Server-only rule sets emit `['ref' => ..., '$secret' => true]` in {@see toArray()}.
 *
 * Context ({@see RuleSetDataType}) is set exclusively by the framework via
 * {@see self::create()} and is never exposed through a public setter.
 * Developer code using `new RuleSet()` always defaults to AND + UNSAFE.
 */
class RuleSet implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	/**
	 * @var list<Rule|RuleSet>
	 */
	private array $t_children = [];

	private RuleSetCondition $t_condition;

	/**
	 * @internal
	 */
	private RuleSetDataType $t_data_type;

	/**
	 * Stable reference identifying this rule set within its form definition.
	 *
	 * Derived from the owning container at definition time, so it is identical
	 * across requests for the same form and survives {@see Form::merge()}.
	 * Empty for rule sets built directly with `new RuleSet()`.
	 */
	private string $t_ref = '';

	private ?RuleViolation $t_violation = null;

	/**
	 * RuleSet constructor.
	 *
	 * Defaults to ANDing all rules, evaluated against unsafe (raw) form data.
	 * Use {@see self::create()} to specify context — reserved for framework use.
	 *
	 * @param RuleSetCondition $condition AND (default) or OR
	 * @param RuleSetDataType  $data_type UNSAFE (default) or CLEANED
	 */
	public function __construct(
		RuleSetCondition $condition = RuleSetCondition::AND,
		RuleSetDataType $data_type = RuleSetDataType::UNSAFE,
		string $ref = ''
	) {
		$this->t_ref              = $ref;
		$this->t_condition        = $condition;
		$this->t_data_type        = $data_type;
	}

	/**
	 * Deep-copies nested sub-groups so a cloned rule set does not share
	 * mutable violation state with the original.
	 *
	 * {@see Rule} children are immutable and can safely stay shared.
	 */
	public function __clone()
	{
		$this->t_violation = null;

		foreach ($this->t_children as $i => $child) {
			if ($child instanceof self) {
				$this->t_children[$i] = clone $child;
			}
		}
	}

	/**
	 * Framework factory — creates a rule set with an explicit context.
	 *
	 * @param RuleSetCondition $condition AND or OR
	 * @param RuleSetDataType  $data_type UNSAFE or CLEANED
	 * @param string           $ref       stable reference within the form definition
	 *
	 * @return static
	 *
	 * @internal not intended for use in application or plugin code
	 */
	public static function create(RuleSetCondition $condition, RuleSetDataType $data_type, string $ref = ''): static
	{
		return new static($condition, $data_type, $ref);
	}

	/**
	 * Gets this rule set's stable reference.
	 *
	 * Assigned by the owning container at definition time and identical across
	 * requests for the same form definition, so a client can correlate a rule it
	 * was sent with the evaluation result it gets back from the server.
	 *
	 * Returns an empty string for rule sets built directly with `new RuleSet()`.
	 */
	public function getRef(): string
	{
		return $this->t_ref;
	}

	/**
	 * Adds a rule: field value must equal $value.
	 *
	 * @param Field|string                                                            $field
	 * @param null|AsyncValue<null|bool|float|int|string>|bool|Field|float|int|string $value
	 * @param null|I18nMessage|string                                                 $message
	 *
	 * @return $this
	 */
	public function eq(
		Field|string $field,
		AsyncValue|bool|Field|float|int|string|null $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::EQ, $value, $message);
	}

	/**
	 * Adds a rule: field value must not equal $value.
	 *
	 * @param Field|string                                                            $field
	 * @param null|AsyncValue<null|bool|float|int|string>|bool|Field|float|int|string $value
	 * @param null|I18nMessage|string                                                 $message
	 *
	 * @return $this
	 */
	public function neq(
		Field|string $field,
		AsyncValue|bool|Field|float|int|string|null $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::NEQ, $value, $message);
	}

	/**
	 * Adds a rule: field value must be greater than $value.
	 *
	 * @param Field|string                                        $field
	 * @param AsyncValue<float|int|string>|Field|float|int|string $value
	 * @param null|I18nMessage|string                             $message
	 *
	 * @return $this
	 */
	public function gt(
		Field|string $field,
		AsyncValue|Field|float|int|string $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::GT, $value, $message);
	}

	/**
	 * Adds a rule: field value must be greater than or equal to $value.
	 *
	 * @param Field|string                                        $field
	 * @param AsyncValue<float|int|string>|Field|float|int|string $value
	 * @param null|I18nMessage|string                             $message
	 *
	 * @return $this
	 */
	public function gte(
		Field|string $field,
		AsyncValue|Field|float|int|string $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::GTE, $value, $message);
	}

	/**
	 * Adds a rule: field value must be less than $value.
	 *
	 * @param Field|string                                        $field
	 * @param AsyncValue<float|int|string>|Field|float|int|string $value
	 * @param null|I18nMessage|string                             $message
	 *
	 * @return $this
	 */
	public function lt(
		Field|string $field,
		AsyncValue|Field|float|int|string $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::LT, $value, $message);
	}

	/**
	 * Adds a rule: field value must be less than or equal to $value.
	 *
	 * @param Field|string                                        $field
	 * @param AsyncValue<float|int|string>|Field|float|int|string $value
	 * @param null|I18nMessage|string                             $message
	 *
	 * @return $this
	 */
	public function lte(
		Field|string $field,
		AsyncValue|Field|float|int|string $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::LTE, $value, $message);
	}

	/**
	 * Adds a rule: field value must be in $value.
	 *
	 * @param Field|string                  $field
	 * @param array|AsyncValue<array>|Field $value
	 * @param null|I18nMessage|string       $message
	 *
	 * @return $this
	 */
	public function in(
		Field|string $field,
		array|AsyncValue|Field $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::IN, $value, $message);
	}

	/**
	 * Adds a rule: field value must not be in $value.
	 *
	 * @param Field|string                  $field
	 * @param array|AsyncValue<array>|Field $value
	 * @param null|I18nMessage|string       $message
	 *
	 * @return $this
	 */
	public function notIn(
		Field|string $field,
		array|AsyncValue|Field $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::NOT_IN, $value, $message);
	}

	/**
	 * Adds a rule: field value must be null (or absent).
	 *
	 * @param Field|string            $field
	 * @param null|I18nMessage|string $message
	 *
	 * @return $this
	 */
	public function isNull(
		Field|string $field,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::IS_NULL, null, $message);
	}

	/**
	 * Adds a rule: field value must not be null (and must be present).
	 *
	 * @param Field|string            $field
	 * @param null|I18nMessage|string $message
	 *
	 * @return $this
	 */
	public function isNotNull(
		Field|string $field,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::IS_NOT_NULL, null, $message);
	}

	/**
	 * Adds a nested AND sub-group.
	 *
	 * All rules inside the callback must pass for the sub-group to pass.
	 *
	 * @param callable(RuleSet):void $callback
	 *
	 * @return $this
	 */
	public function and(callable $callback): static
	{
		$sub            = static::create(RuleSetCondition::AND, $this->t_data_type, $this->subRef('and'));
		$callback($sub);
		$this->t_children[] = $sub;

		return $this;
	}

	/**
	 * Adds a nested OR sub-group.
	 *
	 * At least one rule inside the callback must pass for the sub-group to pass.
	 *
	 * @param callable(RuleSet):void $callback
	 *
	 * @return $this
	 */
	public function or(callable $callback): static
	{
		$sub            = static::create(RuleSetCondition::OR, $this->t_data_type, $this->subRef('or'));
		$callback($sub);
		$this->t_children[] = $sub;

		return $this;
	}

	/**
	 * Evaluates all children against the given form data.
	 *
	 * After calling this method, use {@see self::getViolation()} to inspect any failure.
	 *
	 * The operand source is selected from the context by this rule set's own
	 * {@see RuleSetDataType}, so a CLEANED rule set can never read raw input.
	 *
	 * @param FormValidationContext $ctx
	 *
	 * @return bool true when all conditions pass, false otherwise
	 */
	public function check(FormValidationContext $ctx): bool
	{
		$data = $ctx->dataFor($this->t_data_type);

		$this->t_violation = null;

		if (RuleSetCondition::AND === $this->t_condition) {
			foreach ($this->t_children as $child) {
				if (!$this->evaluateChild($child, $ctx, $data)) {
					return false;
				}
			}

			return true;
		}

		// OR: pass on first match
		if (empty($this->t_children)) {
			return true;
		}

		foreach ($this->t_children as $child) {
			if ($this->evaluateChild($child, $ctx, $data, true)) {
				return true;
			}
		}

		// $this->t_violation already holds the last failing child's violation
		// (evaluateChild with reset_violation=true updates it on each iteration).

		return false;
	}

	/**
	 * Gets the violation recorded by the last call to {@see self::check()}.
	 *
	 * Returns null when the last check passed or when no check has been run yet.
	 *
	 * @return null|RuleViolation
	 */
	public function getViolation(): ?RuleViolation
	{
		return $this->t_violation;
	}

	/**
	 * Gets the message from the last recorded violation, if any.
	 *
	 * @return null|I18nMessage|string
	 */
	public function getViolationMessage(): I18nMessage|string|null
	{
		return $this->t_violation?->getMessage();
	}

	/**
	 * Whether this rule set (or any of its nested descendants) contains
	 * a {@see Rule} that must be evaluated server-side only.
	 *
	 * Server-only rule sets serialize to `['ref' => ..., '$secret' => true]` in {@see toArray()}.
	 *
	 * @return bool
	 */
	public function isServerOnly(): bool
	{
		foreach ($this->t_children as $child) {
			if ($child instanceof Rule) {
				if ($child->server_only) {
					return true;
				}
			} elseif ($child->isServerOnly()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the condition (AND or OR) used to combine children.
	 *
	 * @return RuleSetCondition
	 */
	public function getCondition(): RuleSetCondition
	{
		return $this->t_condition;
	}

	/**
	 * Gets all direct children (Rule or nested RuleSet entries).
	 *
	 * @return list<Rule|RuleSet>
	 */
	public function getChildren(): array
	{
		return $this->t_children;
	}

	/**
	 * Every field ref this rule set reads, including cross-field targets and nested sets.
	 *
	 * @return list<string>
	 */
	public function getFieldRefs(): array
	{
		$refs = [];

		foreach ($this->t_children as $child) {
			if ($child instanceof self) {
				\array_push($refs, ...$child->getFieldRefs());

				continue;
			}

			$refs[] = $child->field_ref;

			if (null !== $child->target_ref) {
				$refs[] = $child->target_ref;
			}
		}

		return $refs;
	}

	/**
	 * Throws when a rule compares a field with an operator its type does not allow.
	 *
	 * Gobl limits the operators of a column by its type (a boolean has no order), and the values a
	 * CLEANED set reads are the types' clean values, so the same limit holds here. An UNSAFE set reads
	 * the raw payload, which the field's type says nothing about: it is left alone. `is_null` and
	 * `is_not_null` are always allowed, since a field left out of the payload reads as null whatever
	 * its type. A field this form does not know (a dynamic fieldset's, or none) and a field whose type
	 * is picked at validation time ({@see TypesSwitcher}) cannot be checked, and are skipped.
	 *
	 * @param array<string, Field> $fields the fields the refs of this set resolve to, by ref
	 *
	 * @throws RuntimeException
	 */
	public function assertOperatorsFit(array $fields): void
	{
		foreach ($this->t_children as $child) {
			if ($child instanceof self) {
				$child->assertOperatorsFit($fields);

				continue;
			}

			if (RuleSetDataType::CLEANED !== $this->t_data_type) {
				continue;
			}

			if (RuleOperator::IS_NULL === $child->operator || RuleOperator::IS_NOT_NULL === $child->operator) {
				continue;
			}

			$type = ($fields[$child->field_ref] ?? null)?->getType();

			if (!$type instanceof TypeInterface) {
				continue;
			}

			$operator = Operator::from($child->operator->value);
			$allowed  = $type->getAllowedFilterOperators();

			if (!\in_array($operator, $allowed, true)) {
				throw new RuntimeException(\sprintf(
					'"%s": the rule "%s" on "%s" is not allowed, a "%s" field accepts %s.',
					'' === $this->t_ref ? 'rule set' : $this->t_ref,
					$child->operator->value,
					$child->field_ref,
					$type->getName(),
					\implode(', ', \array_map(static fn (Operator $op) => \sprintf('"%s"', $op->value), $allowed))
				));
			}
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * Returns `['ref' => ..., '$secret' => true]` when {@see self::isServerOnly()} is true:
	 * the ref is still sent so the client can ask the evaluate endpoint about the
	 * rule, while its operands stay server-side.
	 *
	 * Otherwise returns:
	 * ```
	 * [
	 *   'ref'       => string,
	 *   'condition' => 'and'|'or',
	 *   'data_type' => string,
	 *   'rules'     => [ <Rule::toArray()>|<RuleSet::toArray()>, ... ],
	 * ]
	 * ```
	 *
	 * @return array
	 */
	#[Override]
	public function toArray(): array
	{
		if ($this->isServerOnly()) {
			return [
				'ref'     => $this->t_ref,
				'$secret' => true,
			];
		}

		return [
			'ref'       => $this->t_ref,
			'condition' => $this->t_condition->value,
			'data_type' => $this->t_data_type->value,
			'rules'     => \array_map(static fn (Rule|RuleSet $c) => $c->toArray(), $this->t_children),
		];
	}

	/**
	 * Builds the reference for a nested sub-group.
	 *
	 * Positional within the parent, but scoped to it, so it stays stable across
	 * requests for the same definition code.
	 */
	private function subRef(string $kind): string
	{
		if ('' === $this->t_ref) {
			return '';
		}

		return \sprintf('%s.%s[%d]', $this->t_ref, $kind, \count($this->t_children));
	}

	/**
	 * Builds a {@see Rule} child from a field, operator, value and message, then appends it
	 * to the children list.
	 *
	 * @param Field|string            $field    the field ref or instance
	 * @param RuleOperator            $operator
	 * @param mixed                   $value    the right-hand operand (scalar, AsyncValue, or Field for cross-field)
	 * @param null|I18nMessage|string $message  optional failure message
	 *
	 * @return $this
	 */
	private function addRule(
		Field|string $field,
		RuleOperator $operator,
		mixed $value,
		I18nMessage|string|null $message
	): static {
		if ($field instanceof Field) {
			$field_ref  = $field->getRef();
			$target_ref = null;
		} else {
			FormUtils::assertValidFieldName($field);

			$field_ref  = $field;
			$target_ref = null;
		}

		if ($value instanceof Field) {
			$target_ref = $value->getRef();
			$value      = null;
		}

		$this->t_children[] = new Rule($field_ref, $operator, $value, $target_ref, $message);

		return $this;
	}

	/**
	 * Evaluates a single child (Rule or RuleSet), recording any violation.
	 *
	 * @param Rule|RuleSet          $child
	 * @param FormValidationContext $ctx
	 * @param Store                 $data            this rule set's own operand source
	 * @param bool                  $reset_violation whether to reset $t_violation before evaluating (used in OR loops)
	 *
	 * @return bool
	 */
	private function evaluateChild(
		Rule|self $child,
		FormValidationContext $ctx,
		Store $data,
		bool $reset_violation = false
	): bool {
		if ($reset_violation) {
			$this->t_violation = null;
		}

		if ($child instanceof Rule) {
			if (!$child->evaluate($data, $ctx)) {
				$this->t_violation = new RuleViolation($child);

				return false;
			}
		} elseif (!$child->check($ctx)) {
			$this->t_violation = new RuleViolation($child, $child->getViolation());

			return false;
		}

		return true;
	}
}
