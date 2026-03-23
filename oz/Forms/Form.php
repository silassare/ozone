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

use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeDate;
use InvalidArgumentException;
use Override;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Forms\Enums\RuleSetCondition;
use OZONE\Core\Forms\Enums\RuleSetContext;
use OZONE\Core\Forms\Traits\FormFieldsTrait;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Http\Request;
use OZONE\Core\Http\Uri;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Interfaces\MetaCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;
use PHPUtils\Traits\MetaCapableTrait;

/**
 * Class Form.
 */
class Form implements ArrayCapableInterface, MetaCapableInterface
{
	use ArrayCapableTrait;
	use FormFieldsTrait;
	use MetaCapableTrait;

	/**
	 * @var array<string,Field>
	 */
	public array $t_fields = [];

	/**
	 * @var array<string,FormStep>
	 */
	public array $t_steps = [];

	/**
	 * @var list<RuleSet>
	 */
	public array $t_pre_validation_rules = [];

	/**
	 * @var list<RuleSet>
	 */
	public array $t_post_validation_rules = [];

	/**
	 * Form prefix, used for field namespacing in case of multiple forms in the same page or nested forms (steps).
	 * It can be useful to avoid field name conflicts.
	 */
	public ?string $t_prefix = null;

	public string $t_method;

	public ?Uri $t_submit_to;

	/**
	 * Optional form key for form discovery and registry.
	 *
	 * @see FormRegistry
	 */
	public ?string $t_key = null;

	/**
	 * Controls incremental (multi-request) validation caching.
	 *
	 * @see RequestScope
	 */
	public ?RequestScope $t_resume_scope = null;

	/**
	 * Cache TTL in seconds for resume data. Defaults to 3600 (1 hour).
	 */
	public int $t_resume_ttl = 3600;

	/**
	 * Form constructor.
	 *
	 * @param null|string $prefix    optional prefix for field namespacing (validated as a dot-path segment)
	 * @param string      $method    The form submit method, default is POST
	 * @param null|Uri    $submit_to The form submit uri. Set this to instruct the client to submit the form to a specific uri.
	 */
	public function __construct(
		?string $prefix = null,
		string $method = 'POST',
		?Uri $submit_to = null
	) {
		if (null !== $prefix) {
			$this->prefix($prefix);
		}

		$this->method($method);

		$this->t_submit_to = $submit_to;
	}

	/**
	 * Form destructor.
	 */
	public function __destruct()
	{
		unset($this->t_fields, $this->t_steps, $this->t_pre_validation_rules, $this->t_post_validation_rules, $this->t_submit_to);
	}

	/**
	 * Registers this form in {@see FormRegistry} under the given key.
	 *
	 * The key is used for form discovery: the router can serve the serialized form
	 * definition at a discovery endpoint without running the route handler.
	 *
	 * @param string $key A non-empty identifier for this form
	 *
	 * @return $this
	 */
	public function key(string $key): static
	{
		if ('' === $key) {
			throw new InvalidArgumentException('Form key must be a non-empty string.');
		}

		$this->t_key = $key;

		FormRegistry::register($key, $this);

		return $this;
	}

	/**
	 * Returns the form key previously set via {@see self::key()}, or null if unset.
	 */
	public function getKey(): ?string
	{
		return $this->t_key;
	}

	/**
	 * Returns a short structural fingerprint of this form.
	 *
	 * The version is a 16-character hex string derived from a SHA-256 of the
	 * form's structure (prefix, field refs/types/required flags, step names
	 * and types, and expect/ensure rule sets). It changes when the structure
	 * changes and is used as the cache-key discriminator for resume data, so
	 * cached data from an old form shape is never replayed against a new one.
	 *
	 * @return string 16-character lowercase hex string
	 */
	public function getVersion(): string
	{
		$fields = [];

		foreach ($this->t_fields as $field) {
			$fields[] = [
				'ref' => $field->getRef(),
				'req' => $field->isRequired(),
				'mul' => $field->isMultiple(),
				'typ' => \get_class($field->getType()),
			];
		}

		$steps = [];

		foreach ($this->t_steps as $step) {
			$steps[] = [
				'name' => $step->getName(),
				'type' => $step->isStatic() ? 'static' : 'dynamic',
			];
		}

		$descriptor = [
			'pfx' => $this->t_prefix,
			'fld' => $fields,
			'stp' => $steps,
			'exp' => \array_map(static fn (RuleSet $r) => $r->toArray(), $this->t_pre_validation_rules),
			'ens' => \array_map(static fn (RuleSet $r) => $r->toArray(), $this->t_post_validation_rules),
		];

		return \substr(\hash('sha256', \json_encode($descriptor, \JSON_THROW_ON_ERROR)), 0, 16);
	}

	/**
	 * Enables incremental form submission by caching validated field values across requests.
	 *
	 * When enabled, previously validated fields stored in the cache satisfy their required
	 * constraints on subsequent requests, so the client only needs to submit fields it has
	 * not yet submitted. The cache is scoped using the provided {@see RequestScope} strategy.
	 *
	 * @param RequestScope $mode The scoping strategy for the resume cache
	 * @param int          $ttl  Cache TTL in seconds (default: 3600)
	 *
	 * @return $this
	 */
	public function resume(RequestScope $mode = RequestScope::STATE, int $ttl = 3600): static
	{
		$this->t_resume_scope = $mode;
		$this->t_resume_ttl   = $ttl;

		return $this;
	}

	/**
	 * Returns the active resume scope, or null if resume is disabled.
	 *
	 * @return null|RequestScope
	 */
	public function getResumeScope(): ?RequestScope
	{
		return $this->t_resume_scope;
	}

	/**
	 * Returns the resume cache TTL in seconds.
	 */
	public function getResumeTTL(): int
	{
		return $this->t_resume_ttl;
	}

	/**
	 * Builds the persistent cache key for storing resume data for this form.
	 *
	 * The key encodes both the form version (so stale cached data is never
	 * replayed against a structurally different form) and a hash of the
	 * provided scope identifier (session ID or user identifier).
	 *
	 * @param string $scope_id Session stateID or user auth identifier
	 *
	 * @return string Non-empty cache key
	 */
	public function buildResumeCacheKey(string $scope_id): string
	{
		return 'form_resume_' . $this->getVersion() . '_' . \substr(\hash('sha256', $scope_id), 0, 16);
	}

	/**
	 * Set form prefix.
	 */
	public function prefix(string $prefix): self
	{
		FormUtils::assertValidFieldName($prefix);

		$this->t_prefix = $prefix;

		return $this;
	}

	/**
	 * Set form submit to uri.
	 *
	 * @param Uri $uri
	 *
	 * @return $this
	 */
	public function submitTo(Uri $uri): static
	{
		$this->t_submit_to = $uri;

		return $this;
	}

	/**
	 * Sets form submit method.
	 *
	 * @param string $method
	 *
	 * @return Form
	 */
	public function method(string $method): self
	{
		$this->t_method = Request::filterMethod($method);

		return $this;
	}

	/**
	 * Creates a new static form step.
	 *
	 * @param string               $name    The step name
	 * @param callable():Form|Form $form    The step form or factory
	 * @param null|RuleSet         $only_if The step condition
	 *
	 * @return FormStep
	 */
	public function step(string $name, callable|self $form, ?RuleSet $only_if = null): FormStep
	{
		return FormStep::static($this, $name, $form, $only_if);
	}

	/**
	 * Creates a new dynamic form step.
	 *
	 * @param string                  $name    The step name
	 * @param callable(FormData):Form $factory The step form factory
	 * @param null|RuleSet            $only_if The step condition
	 *
	 * @return FormStep
	 */
	public function dynamicStep(string $name, callable $factory, ?RuleSet $only_if = null): FormStep
	{
		return FormStep::dynamic($this, $name, $factory, $only_if);
	}

	/**
	 * Create or gets a field by its name.
	 *
	 * @param string $name The field name
	 *
	 * @return Field
	 */
	public function field(string $name): Field
	{
		$field = $this->t_fields[$name] ?? null;

		if (!$field) {
			$field = new Field($this, $name);

			$this->t_fields[$name] = $field;
		}

		return $field;
	}

	/**
	 * Creates and registers a new pre-validation condition.
	 *
	 * Pre-validation conditions run server-side on raw (unsafe) form data, before any field is
	 * validated. Non-server-only rule sets are included in {@see self::toArray()}.
	 *
	 * If the condition fails, {@see InvalidFormException} is thrown before field validation begins.
	 *
	 * Use cases:
	 * - Gating the form on a flag or plan value present in the raw input
	 * - Rejecting globally invalid input combinations before processing individual fields
	 *
	 * @param RuleSetCondition $condition AND (default) or OR
	 *
	 * @return RuleSet
	 */
	public function expect(RuleSetCondition $condition = RuleSetCondition::AND): RuleSet
	{
		$rule = RuleSet::create($condition, RuleSetContext::UNSAFE);

		$this->t_pre_validation_rules[] = $rule;

		return $rule;
	}

	/**
	 * Creates and registers a new post-validation assertion.
	 *
	 * Post-validation assertions run server-side on cleaned form data, after all fields have been
	 * validated and transformed. They are never sent to the client.
	 *
	 * If the assertion fails, {@see InvalidFormException} is thrown.
	 *
	 * Use cases:
	 * - Cross-field equality checks on transformed values (e.g. password === password_confirm)
	 * - Permission or consistency checks that can only be evaluated on clean data
	 *
	 * @param RuleSetCondition $condition AND (default) or OR
	 *
	 * @return RuleSet
	 */
	public function ensure(RuleSetCondition $condition = RuleSetCondition::AND): RuleSet
	{
		$rule = RuleSet::create($condition, RuleSetContext::CLEANED);

		$this->t_post_validation_rules[] = $rule;

		return $rule;
	}

	/**
	 * Gets a ref for a given field or step name, prefixed with form prefix if any.
	 */
	public function getRef(string $name): string
	{
		if (empty($this->t_prefix)) {
			return $name;
		}

		return $this->t_prefix . '.' . $name;
	}

	/**
	 * Gets a field by its name.
	 *
	 * @param string $name
	 *
	 * @return null|Field
	 */
	public function getField(string $name): ?Field
	{
		return $this->t_fields[$name] ?? null;
	}

	/**
	 * Gets form submit uri.
	 *
	 * @return null|Uri
	 */
	public function getSubmitTo(): ?Uri
	{
		return $this->t_submit_to;
	}

	/**
	 * Gets form submit method.
	 *
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->t_method;
	}

	/**
	 * Validates the form.
	 *
	 * @param FormData      $unsafe_fd
	 * @param null|FormData $cleaned_fd
	 *
	 * @return FormData
	 *
	 * @throws InvalidFormException
	 */
	public function validate(FormData $unsafe_fd, ?FormData $cleaned_fd = null): FormData
	{
		foreach ($this->t_pre_validation_rules as $rule) {
			if ($rule->check($unsafe_fd)) {
				continue;
			}

			throw new InvalidFormException($rule->getViolationMessage(), [
				'_rule' => $rule,
			]);
		}

		$cleaned_fd ??= new FormData();

		foreach ($this->t_fields as $field) {
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
						'field' => $ref,
						'_form' => $this,
						'_data' => $unsafe_fd->getData(),
					]);
				}
			}
		}

		foreach ($this->t_post_validation_rules as $rule) {
			if ($rule->check($cleaned_fd)) {
				continue;
			}

			throw new InvalidFormException($rule->getViolationMessage(), [
				// rule is prefixed by "_" as form rules are checked on validated data (cleaned data),
				// they may contains values that are not safe to be exposed to client (added while validating etc...)
				'_rule' => $rule,
			]);
		}

		foreach ($this->t_steps as $step) {
			if (!($step_form = $step->build($cleaned_fd))) {
				continue;
			}

			$step_form->validate($unsafe_fd, $cleaned_fd);
		}

		return $cleaned_fd;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Server-only {@see RuleSet} entries (those containing {@see DynamicValue})
	 * are excluded from `expect` — the client cannot evaluate them.
	 *
	 * @return array{
	 *  key: ?string,
	 *  prefix: ?string,
	 *  action: ?Uri,
	 *  method: string,
	 *  fields: list<Field>,
	 *  steps: list<FormStep>,
	 *  expect: list<RuleSet>
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'key'    => $this->t_key,
			'prefix' => $this->t_prefix,
			'action' => $this->t_submit_to,
			'method' => $this->t_method,
			'fields' => $this->t_fields,
			'steps'  => $this->t_steps,
			'expect' => \array_values(\array_filter(
				$this->t_pre_validation_rules,
				static fn (RuleSet $r) => !$r->isServerOnly()
			)),
		];
	}

	/**
	 * Merges a given form to this.
	 *
	 * @param Form $from
	 *
	 * @return $this
	 */
	public function merge(self $from): static
	{
		if (!$this->t_method) {
			$this->t_method = $from->t_method;
		}

		if (!$this->t_submit_to) {
			$this->t_submit_to = $from->t_submit_to;
		}

		$this->t_fields                = \array_merge($this->t_fields, $from->t_fields);
		$this->t_steps                 = \array_merge($this->t_steps, $from->t_steps);
		$this->t_pre_validation_rules  = \array_merge($this->t_pre_validation_rules, $from->t_pre_validation_rules);
		$this->t_post_validation_rules = \array_merge($this->t_post_validation_rules, $from->t_post_validation_rules);

		// Propagate resume scope from source — first non-null wins.
		if (null === $this->t_resume_scope && null !== $from->t_resume_scope) {
			$this->t_resume_scope = $from->t_resume_scope;
			$this->t_resume_ttl   = $from->t_resume_ttl;
		}

		return $this;
	}

	/**
	 * Generates a form for a given table.
	 *
	 * @param string|Table $table
	 *
	 * @return Form
	 */
	public static function fromTable(string|Table $table): self
	{
		if (\is_string($table)) {
			$table = db()
				->getTable($table);
		}

		$columns = $table->getColumns(false);

		$form = new self();

		foreach ($columns as $column) {
			$type = $column->getType();

			if ($type->isAutoIncremented()) {
				continue;
			}

			if ($type instanceof TypeDate && $type->isAuto()) {
				continue;
			}

			$form->field($column->getName())
				->label($column->getMeta()->get('field.label'))
				->description($column->getMeta()->get('field.description'))
				->help($column->getMeta()->get('field.help'))
				// we don't merge all as meta may contains sensitive data,
				// we keep api.doc meta because it's used to generate API documentation and is not sensitive
				->setMetaKey('api.doc', $column->getMeta()->get('api.doc', []))
				// frontend.options may contains presentation hints that are not sensitive, we can merge them safely
				->setMetaKey('frontend.options', $column->getMeta()->get('frontend.options', []))
				->type($type)
				->required(!$type->isNullable());
		}

		return $form;
	}
}
