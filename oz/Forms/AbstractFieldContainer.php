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
use Override;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Forms\Enums\RuleSetCondition;
use OZONE\Core\Forms\Enums\RuleSetContext;
use OZONE\Core\Forms\Interfaces\FieldContainerInterface;

/**
 * Abstract base for form field containers ({@see Form}, {@see Fieldset}).
 *
 * Holds the shared state and default implementations for the
 * {@see FieldContainerInterface} contract. Subclasses must provide
 * their own {@see getRef()} implementation since reference resolution
 * differs between a root form and a named fieldset.
 */
abstract class AbstractFieldContainer implements FieldContainerInterface
{
	/**
	 * Container name used for field reference prefixing.
	 */
	private ?string $t_name = null;

	/**
	 * @var array<string, Field>
	 */
	private array $t_fields = [];

	/**
	 * @var list<RuleSet>
	 */
	private array $t_pre_validation_rules = [];

	/**
	 * @var list<RuleSet>
	 */
	private array $t_post_validation_rules = [];

	/**
	 * AbstractFieldContainer destructor.
	 */
	public function __destruct()
	{
		unset($this->t_fields, $this->t_pre_validation_rules, $this->t_post_validation_rules);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function name(string $name): static
	{
		FormUtils::assertValidFieldName($name);
		$this->t_name = $name;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getName(): ?string
	{
		return $this->t_name;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function field(string $name): Field
	{
		if (!isset($this->t_fields[$name])) {
			$this->t_fields[$name] = new Field($this, $name);
		}

		return $this->t_fields[$name];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getField(string $name): ?Field
	{
		return $this->t_fields[$name] ?? null;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getFields(): array
	{
		return $this->t_fields;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function expect(RuleSetCondition $condition = RuleSetCondition::AND): RuleSet
	{
		$rule = RuleSet::create($condition, RuleSetContext::UNSAFE);

		$this->t_pre_validation_rules[] = $rule;

		return $rule;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function ensure(RuleSetCondition $condition = RuleSetCondition::AND): RuleSet
	{
		$rule = RuleSet::create($condition, RuleSetContext::CLEANED);

		$this->t_post_validation_rules[] = $rule;

		return $rule;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPreValidationRules(): array
	{
		return $this->t_pre_validation_rules;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPostValidationRules(): array
	{
		return $this->t_post_validation_rules;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	abstract public function getRef(string $name): string;

	/**
	 * Merges fields and validation rules from another container into this one.
	 *
	 * Access to the source container's private properties is valid here since
	 * both `$this` and `$from` are instances of the same declaring class.
	 *
	 * @param self $from The source container to merge from
	 */
	protected function mergeContainerState(self $from): void
	{
		$this->t_fields                = \array_merge($this->t_fields, $from->t_fields);
		$this->t_pre_validation_rules  = \array_merge($this->t_pre_validation_rules, $from->t_pre_validation_rules);
		$this->t_post_validation_rules = \array_merge($this->t_post_validation_rules, $from->t_post_validation_rules);
	}

	/**
	 * Checks all pre-validation rules against the given unsafe form data.
	 *
	 * @param FormData $unsafe_fd The raw, unvalidated form data
	 *
	 * @throws InvalidFormException if any rule is violated
	 */
	protected function checkPreValidationRules(FormData $unsafe_fd): void
	{
		foreach ($this->getPreValidationRules() as $rule) {
			if ($rule->check($unsafe_fd)) {
				continue;
			}

			throw new InvalidFormException($rule->getViolationMessage(), [
				// rule is prefixed by "_" as form rules are checked on validated data (cleaned data),
				// they may contains values that are not safe to be exposed to client (added while validating etc...)
				'_rule' => $rule,
			]);
		}
	}

	/**
	 * Checks all post-validation rules against the given cleaned form data.
	 *
	 * @param FormData $cleaned_fd The validated and cleaned form data
	 *
	 * @throws InvalidFormException if any rule is violated
	 */
	protected function checkPostValidationRules(FormData $cleaned_fd): void
	{
		foreach ($this->getPostValidationRules() as $rule) {
			if ($rule->check($cleaned_fd)) {
				continue;
			}

			throw new InvalidFormException($rule->getViolationMessage(), [
				// rule is prefixed by "_" as form rules are checked on validated data (cleaned data),
				// they may contains values that are not safe to be exposed to client (added while validating etc...)
				'_rule' => $rule,
			]);
		}
	}

	/**
	 * Checks all fields against the given form data.
	 *
	 * @param FormData $unsafe_fd  Raw (unsafe) form data from the request
	 * @param FormData $cleaned_fd Pre-filled validated data (e.g. from a resume cache); merged with newly validated fields
	 *
	 * @throws InvalidFormException if any field is invalid
	 */
	protected function checkFields(FormData $unsafe_fd, FormData $cleaned_fd): void
	{
		foreach ($this->getFields() as $field) {
			$ref = $field->getRef();
			if ($field->isEnabled($unsafe_fd)) {
				if ($unsafe_fd->has($ref)) {
					try {
						$cleaned_fd->set($ref, $field->validate($unsafe_fd->get($ref), $unsafe_fd));
					} catch (TypesInvalidValueException $e) {
						/** @var InvalidFormException $e */
						$e = InvalidFormException::tryConvert($e);

						$e->suspectObject($field);

						throw $e;
					}
				} elseif ($field->isRequired() && !$cleaned_fd->has($ref)) {
					throw new InvalidFormException('OZ_FORM_MISSING_REQUIRED_FIELD', [
						'field'   => $ref,
						'_parent' => $this,
						'_data'   => $unsafe_fd->getData(),
					]);
				}
			}
		}
	}
}
