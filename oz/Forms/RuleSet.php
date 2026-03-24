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
use OZONE\Core\Forms\Enums\RuleSetCondition;
use OZONE\Core\Forms\Enums\RuleSetContext;
use OZONE\Core\Lang\I18nMessage;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class RuleSet.
 *
 * A chainable, tree-structured predicate evaluated against a {@see FormData} object.
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
 * has {@see Rule::$server_only} set to true (i.e. its value is a {@see DynamicValue}).
 * Server-only rule sets emit `['$async' => true]` in {@see toArray()}.
 *
 * Context ({@see RuleSetContext}) is set exclusively by the framework via
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
	private RuleSetContext $t_context;

	private ?RuleViolation $t_violation = null;

	/**
	 * RuleSet constructor.
	 *
	 * Defaults to ANDing all rules, evaluated against unsafe (raw) form data.
	 * Use {@see self::create()} to specify context — reserved for framework use.
	 *
	 * @param RuleSetCondition $condition AND (default) or OR
	 */
	public function __construct(RuleSetCondition $condition = RuleSetCondition::AND)
	{
		$this->t_condition = $condition;
		$this->t_context   = RuleSetContext::UNSAFE;
	}

	/**
	 * RuleSet destructor.
	 */
	public function __destruct()
	{
		unset($this->t_children, $this->t_violation);
	}

	/**
	 * Framework factory — creates a rule set with an explicit context.
	 *
	 * @internal not intended for use in application or plugin code
	 *
	 * @param RuleSetCondition $condition AND or OR
	 * @param RuleSetContext   $context   UNSAFE or CLEANED
	 *
	 * @return static
	 */
	public static function create(RuleSetCondition $condition, RuleSetContext $context): static
	{
		$rs            = new static($condition);
		$rs->t_context = $context;

		return $rs;
	}

	/**
	 * Adds a rule: field value must equal $value.
	 *
	 * @param Field|string                                                              $field
	 * @param null|bool|DynamicValue<null|bool|float|int|string>|Field|float|int|string $value
	 * @param null|I18nMessage|string                                                   $message
	 *
	 * @return $this
	 */
	public function eq(
		Field|string $field,
		bool|DynamicValue|Field|float|int|string|null $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::EQ, $value, $message);
	}

	/**
	 * Adds a rule: field value must not equal $value.
	 *
	 * @param Field|string                                                              $field
	 * @param null|bool|DynamicValue<null|bool|float|int|string>|Field|float|int|string $value
	 * @param null|I18nMessage|string                                                   $message
	 *
	 * @return $this
	 */
	public function neq(
		Field|string $field,
		bool|DynamicValue|Field|float|int|string|null $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::NEQ, $value, $message);
	}

	/**
	 * Adds a rule: field value must be greater than $value.
	 *
	 * @param Field|string                                          $field
	 * @param DynamicValue<float|int|string>|Field|float|int|string $value
	 * @param null|I18nMessage|string                               $message
	 *
	 * @return $this
	 */
	public function gt(
		Field|string $field,
		DynamicValue|Field|float|int|string $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::GT, $value, $message);
	}

	/**
	 * Adds a rule: field value must be greater than or equal to $value.
	 *
	 * @param Field|string                                          $field
	 * @param DynamicValue<float|int|string>|Field|float|int|string $value
	 * @param null|I18nMessage|string                               $message
	 *
	 * @return $this
	 */
	public function gte(
		Field|string $field,
		DynamicValue|Field|float|int|string $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::GTE, $value, $message);
	}

	/**
	 * Adds a rule: field value must be less than $value.
	 *
	 * @param Field|string                                          $field
	 * @param DynamicValue<float|int|string>|Field|float|int|string $value
	 * @param null|I18nMessage|string                               $message
	 *
	 * @return $this
	 */
	public function lt(
		Field|string $field,
		DynamicValue|Field|float|int|string $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::LT, $value, $message);
	}

	/**
	 * Adds a rule: field value must be less than or equal to $value.
	 *
	 * @param Field|string                                          $field
	 * @param DynamicValue<float|int|string>|Field|float|int|string $value
	 * @param null|I18nMessage|string                               $message
	 *
	 * @return $this
	 */
	public function lte(
		Field|string $field,
		DynamicValue|Field|float|int|string $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::LTE, $value, $message);
	}

	/**
	 * Adds a rule: field value must be in $value.
	 *
	 * @param Field|string                    $field
	 * @param array|DynamicValue<array>|Field $value
	 * @param null|I18nMessage|string         $message
	 *
	 * @return $this
	 */
	public function in(
		Field|string $field,
		array|DynamicValue|Field $value,
		I18nMessage|string|null $message = null
	): static {
		return $this->addRule($field, RuleOperator::IN, $value, $message);
	}

	/**
	 * Adds a rule: field value must not be in $value.
	 *
	 * @param Field|string                    $field
	 * @param array|DynamicValue<array>|Field $value
	 * @param null|I18nMessage|string         $message
	 *
	 * @return $this
	 */
	public function notIn(
		Field|string $field,
		array|DynamicValue|Field $value,
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
		$sub            = static::create(RuleSetCondition::AND, $this->t_context);
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
		$sub            = static::create(RuleSetCondition::OR, $this->t_context);
		$callback($sub);
		$this->t_children[] = $sub;

		return $this;
	}

	/**
	 * Evaluates all children against the given form data.
	 *
	 * After calling this method, use {@see self::getViolation()} to inspect any failure.
	 *
	 * @param FormData $fd
	 *
	 * @return bool true when all conditions pass, false otherwise
	 */
	public function check(FormData $fd): bool
	{
		$this->t_violation = null;

		if (RuleSetCondition::AND === $this->t_condition) {
			foreach ($this->t_children as $child) {
				if (!$this->evaluateChild($child, $fd)) {
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
			if ($this->evaluateChild($child, $fd, true)) {
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
	 * Server-only rule sets serialize to `['$async' => true]` in {@see toArray()}.
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
	 * Gets the context this rule set is evaluated in.
	 *
	 * @internal
	 *
	 * @return RuleSetContext
	 */
	public function getContext(): RuleSetContext
	{
		return $this->t_context;
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
	 * {@inheritDoc}
	 *
	 * Returns `['$async' => true]` when {@see self::isServerOnly()} is true.
	 *
	 * Otherwise returns:
	 * ```
	 * [
	 *   'condition' => 'and'|'or',
	 *   'rules'     => [ <Rule::toArray()>|<RuleSet::toArray()>, ... ],
	 * ]
	 * ```
	 *
	 * @return array{'$async': true}|array{condition: string, rules: list<array>}
	 */
	#[Override]
	public function toArray(): array
	{
		if ($this->isServerOnly()) {
			return ['$async' => true];
		}

		return [
			'condition' => $this->t_condition->value,
			'rules'     => \array_map(static fn (Rule|RuleSet $c) => $c->toArray(), $this->t_children),
		];
	}

	/**
	 * Builds a {@see Rule} child from a field, operator, value and message, then appends it
	 * to the children list.
	 *
	 * @param Field|string            $field    the field ref or instance
	 * @param RuleOperator            $operator
	 * @param mixed                   $value    the right-hand operand (scalar, DynamicValue, or Field for cross-field)
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
	 * @param Rule|RuleSet $child
	 * @param FormData     $fd
	 * @param bool         $reset_violation whether to reset $t_violation before evaluating (used in OR loops)
	 *
	 * @return bool
	 */
	private function evaluateChild(Rule|self $child, FormData $fd, bool $reset_violation = false): bool
	{
		if ($reset_violation) {
			$this->t_violation = null;
		}

		if ($child instanceof Rule) {
			if (!$child->evaluate($fd)) {
				$this->t_violation = new RuleViolation($child);

				return false;
			}
		} elseif (!$child->check($fd)) {
			$this->t_violation = new RuleViolation($child, $child->getViolation());

			return false;
		}

		return true;
	}
}
