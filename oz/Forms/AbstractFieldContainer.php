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
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Enums\RuleSetCondition;
use OZONE\Core\Forms\Enums\RuleSetDataType;
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
	 * Fields keyed by their ref, not their local name.
	 *
	 * Keying by ref is what lets {@see Form::merge()} combine two forms that each
	 * declare a field with the same local name: as long as the forms are named,
	 * the refs differ and both survive.
	 *
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
		$ref = $this->getRef($name);

		if (!isset($this->t_fields[$ref])) {
			$this->t_fields[$ref] = new Field($this, $name);
		}

		return $this->t_fields[$ref];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getField(string $name): ?Field
	{
		// Accept either a full ref or a local name. After a merge, fields brought
		// in from another form keep that form's namespace, so they can only be
		// addressed by their full ref.
		return $this->t_fields[$name] ?? $this->t_fields[$this->getRef($name)] ?? null;
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
		$rule = RuleSet::create(
			$condition,
			RuleSetDataType::UNSAFE,
			$this->getRef(\sprintf('@expect[%d]', \count($this->t_pre_validation_rules)))
		);

		$this->t_pre_validation_rules[] = $rule;

		return $rule;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function ensure(RuleSetCondition $condition = RuleSetCondition::AND): RuleSet
	{
		$rule = RuleSet::create(
			$condition,
			RuleSetDataType::CLEANED,
			$this->getRef(\sprintf('@ensure[%d]', \count($this->t_post_validation_rules)))
		);

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
	 * Clones this container's own fields and re-binds them to it.
	 *
	 * Used when a container itself is cloned, so the copy does not share Field
	 * instances with the original.
	 */
	protected function cloneOwnFields(): void
	{
		$clones = [];

		foreach ($this->t_fields as $ref => $field) {
			$clones[$ref] = clone $field;
			$clones[$ref]->rebind($this);
		}

		foreach ($this->t_fields as $ref => $field) {
			$confirm = $field->getDoubleCheck();

			if (null !== $confirm) {
				$clones[$ref]->relinkDoubleCheck($clones[$confirm->getRef()] ?? $confirm);
			}
		}

		$this->t_fields = $clones;
	}

	/**
	 * Merges fields and validation rules from another container into this one.
	 *
	 * Fields are cloned so a long-lived source form is never mutated through the
	 * merged copy, and their original parent is preserved so refs stay stable.
	 *
	 * @param self $from The source container to merge from
	 *
	 * @throws RuntimeException when a field ref is already defined in this container
	 */
	protected function mergeContainerState(self $from): void
	{
		$clones = [];

		foreach ($from->t_fields as $ref => $field) {
			if (isset($this->t_fields[$ref])) {
				throw new RuntimeException(\sprintf(
					'Cannot merge form: field "%s" is already defined. '
					. 'Give one of the two forms a name so their fields do not collide.',
					$ref
				));
			}

			$clones[$ref] = clone $field;
		}

		// Re-link double-check companions onto the clones: each companion is a
		// sibling in the same container, so cloning fields one by one would
		// otherwise leave the clone pointing at the original sibling.
		foreach ($from->t_fields as $ref => $field) {
			$confirm = $field->getDoubleCheck();

			if (null === $confirm) {
				continue;
			}

			$clones[$ref]->relinkDoubleCheck($clones[$confirm->getRef()] ?? $confirm);
		}

		$this->t_fields                = \array_merge($this->t_fields, $clones);
		$this->t_pre_validation_rules  = \array_merge($this->t_pre_validation_rules, $from->t_pre_validation_rules);
		$this->t_post_validation_rules = \array_merge($this->t_post_validation_rules, $from->t_post_validation_rules);
	}

	/**
	 * Checks all pre-validation rules.
	 *
	 * @param FormValidationContext $ctx
	 *
	 * @throws InvalidFormException if any rule is violated
	 */
	protected function checkPreValidationRules(FormValidationContext $ctx): void
	{
		foreach ($this->getPreValidationRules() as $rule) {
			if ($rule->check($ctx)) {
				continue;
			}

			throw new InvalidFormException($rule->getViolationMessage(), [
				// The set's ref, which the client holds in the bundle: it names what refused (G22).
				'rule'  => $rule->getRef(),
				// rule is prefixed by "_" as form rules are checked on validated data (cleaned data),
				// they may contains values that are not safe to be exposed to client (added while validating etc...)
				'_rule' => $rule,
			]);
		}
	}

	/**
	 * Checks all post-validation rules.
	 *
	 * @param FormValidationContext $ctx
	 *
	 * @throws InvalidFormException if any rule is violated
	 */
	protected function checkPostValidationRules(FormValidationContext $ctx): void
	{
		foreach ($this->getPostValidationRules() as $rule) {
			if ($rule->check($ctx)) {
				continue;
			}

			throw new InvalidFormException($rule->getViolationMessage(), [
				// The set's ref, which the client holds in the bundle: it names what refused (G22).
				'rule'  => $rule->getRef(),
				// rule is prefixed by "_" as form rules are checked on validated data (cleaned data),
				// they may contains values that are not safe to be exposed to client (added while validating etc...)
				'_rule' => $rule,
			]);
		}
	}

	/**
	 * Checks all fields against the given form data.
	 *
	 * Fields are processed in declaration order and each cleaned value is written
	 * into the context's cleaned store as it is produced, so a field's condition
	 * or {@see TypesSwitcher} can only read fields declared before it; reading a
	 * later one throws (see {@see FormValidationContext::assertReadable()}).
	 *
	 * @param FormValidationContext $ctx
	 *
	 * @throws InvalidFormException if any field is invalid
	 * @throws RuntimeException     if a condition reads a field validated after it
	 */
	protected function checkFields(FormValidationContext $ctx): void
	{
		$unsafe_fd  = $ctx->getUnsafeFormData();
		$cleaned_fd = $ctx->getCleanFormData();

		foreach ($this->getFields() as $field) {
			$ref = $field->getRef();

			self::assertConditionsReadable($field, $ctx);

			if (!$field->isEnabled($ctx)) {
				$ctx->markProcessed($ref);

				continue;
			}

			if ($unsafe_fd->has($ref)) {
				try {
					$cleaned_fd->set($ref, $field->validate($unsafe_fd->get($ref), $ctx));
				} catch (TypesInvalidValueException $e) {
					/** @var InvalidFormException $e */
					$e = InvalidFormException::tryConvert($e);

					// The ref names the refused field to the client (G22); the field itself is for the logs.
					$e->mergeData(['field' => $ref])
						->suspectObject($field);

					throw $e;
				}
			} elseif ($cleaned_fd->has($ref)) {
				// Value replayed from a resume cache: it was cleaned by an earlier
				// request, so re-assert it against the field as defined now instead
				// of letting it through unchecked.
				try {
					$cleaned_fd->set($ref, $field->revalidateStored($cleaned_fd->get($ref), $ctx));
				} catch (TypesInvalidValueException $e) {
					// Drop it, so progress saved after this failure (e.g. by RouteInfo)
					// does not store it back and replay it forever: it must be resent.
					$cleaned_fd->remove($ref);

					/** @var InvalidFormException $e */
					$e = InvalidFormException::tryConvert($e);

					// The ref names the refused field to the client (G22); the field itself is for the logs.
					$e->mergeData(['field' => $ref])
						->suspectObject($field);

					throw $e;
				}
			} elseif ($field->isRequired()) {
				throw new InvalidFormException('OZ_FORM_MISSING_REQUIRED_FIELD', [
					'field'      => $ref,
					'_parent'    => $this,
					// Field names only: the payload may hold passwords, and this gets logged.
					'_submitted' => \array_keys((array) $unsafe_fd->getData()),
				]);
			}

			$ctx->markProcessed($ref);
		}
	}

	/**
	 * Rejects a field whose `if()` or {@see TypesSwitcher} branches read a field this
	 * pass has not processed yet.
	 *
	 * @throws RuntimeException
	 */
	private static function assertConditionsReadable(Field $field, FormValidationContext $ctx): void
	{
		$ref       = $field->getRef();
		$condition = $field->getIf();

		if (null !== $condition) {
			$ctx->assertReadable($condition, $ref);
		}

		$type = $field->getType();

		if ($type instanceof TypesSwitcher) {
			foreach ($type->getConditions() as $branch) {
				$ctx->assertReadable($branch, $ref);
			}
		}
	}
}
