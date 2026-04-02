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
use Override;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Forms\Enums\RuleSetCondition;
use OZONE\Core\Forms\Enums\RuleSetContext;
use OZONE\Core\Forms\Traits\FormFieldsTrait;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Http\Request;
use OZONE\Core\Http\Uri;
use OZONE\Core\Utils\Hasher;
use OZONE\Core\Utils\Utils;
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
	private array $t_fields = [];

	/**
	 * @var array<string,FormStep>
	 */
	private array $t_steps = [];

	/**
	 * @var list<RuleSet>
	 */
	private array $t_pre_validation_rules = [];

	/**
	 * @var list<RuleSet>
	 */
	private array $t_post_validation_rules = [];

	/**
	 * Form name, used for field namespacing in case of multiple forms in the
	 * same page or nested forms (steps). It can be useful to avoid field name conflicts.
	 */
	private ?string $t_name = null;

	private string $t_method;

	private ?Uri $t_submit_to;

	/**
	 * Controls incremental (multi-request) validation caching.
	 *
	 * @see RequestScope
	 */
	private ?RequestScope $t_resume_scope = null;

	/**
	 * Cache TTL in seconds for resume data. Defaults to 3600 (1 hour).
	 */
	private int $t_resume_ttl = 3600;

	/**
	 * Form constructor.
	 *
	 * @param null|string $name      optional name for field namespacing (validated as a dot-path segment)
	 * @param string      $method    The form submit method, default is POST
	 * @param null|Uri    $submit_to The form submit uri. Set this to instruct the client to submit the form to a specific uri.
	 */
	public function __construct(
		?string $name = null,
		string $method = 'POST',
		?Uri $submit_to = null
	) {
		if (null !== $name) {
			$this->name($name);
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
	 * Gets the registered steps.
	 *
	 * @return array<string, FormStep>
	 */
	public function getSteps(): array
	{
		return $this->t_steps;
	}

	/**
	 * Gets the registered pre-validation rules.
	 *
	 * @return list<RuleSet>
	 */
	public function getPreValidationRules(): array
	{
		return $this->t_pre_validation_rules;
	}

	/**
	 * Gets the registered post-validation rules.
	 *
	 * @return list<RuleSet>
	 */
	public function getPostValidationRules(): array
	{
		return $this->t_post_validation_rules;
	}

	/**
	 * Returns a short structural fingerprint of this form.
	 *
	 * @return string
	 */
	public function getVersion(): string
	{
		$fields = [];

		foreach ($this->t_fields as $field) {
			$fields[] = [
				'r'  => $field->getRef(),
				'rq' => $field->isRequired(),
				'm'  => $field->isMultiple(),
				't'  => \get_class($field->getType()),
			];
		}

		$steps = [];

		foreach ($this->t_steps as $step) {
			$steps[] = [
				'n'  => $step->getName(),
				'sd' => $step->isStatic() ? 'static' : 'dynamic',
			];
		}

		$descriptor = [
			'n'  => $this->t_name,
			'f'  => $fields,
			's'  => $steps,
			'pr' => \array_map(static fn (RuleSet $r) => $r->toArray(), $this->t_pre_validation_rules),
			'po' => \array_map(static fn (RuleSet $r) => $r->toArray(), $this->t_post_validation_rules),
		];

		return Hasher::shorten(Hasher::hash32(\json_encode($descriptor, \JSON_THROW_ON_ERROR)));
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
	 * Set form name.
	 */
	public function name(string $name): static
	{
		FormUtils::assertValidFieldName($name);

		$this->t_name = $name;

		return $this;
	}

	/**
	 * Set form submit to uri.
	 *
	 * @param Uri $uri
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
	 */
	public function method(string $method): static
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
		$step = FormStep::static($this, $name, $form, $only_if);

		$this->t_steps[$name] = $step;

		return $step;
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
		$step = FormStep::dynamic($this, $name, $factory, $only_if);

		$this->t_steps[$name] = $step;

		return $step;
	}

	/**
	 * Retrieves a previously registered step by name.
	 *
	 * @param string $name The step name
	 *
	 * @return null|FormStep
	 */
	public function getStep(string $name): ?FormStep
	{
		return $this->t_steps[$name] ?? null;
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
	 * Gets a ref for a given field or step name, prefixed with form name if any.
	 */
	public function getRef(string $name): string
	{
		if (empty($this->t_name)) {
			return $name;
		}

		return $this->t_name . '.' . $name;
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
	 * @param FormData      $unsafe_fd  Raw (unsafe) form data from the request
	 * @param null|FormData $cleaned_fd Pre-filled validated data (e.g. from a resume cache); merged with newly validated fields
	 * @param bool          $skip_steps When true, step sub-forms are not traversed. Useful in step-by-step validation where only root-level fields are validated
	 *
	 * @return FormData
	 *
	 * @throws InvalidFormException
	 */
	public function validate(FormData $unsafe_fd, ?FormData $cleaned_fd = null, bool $skip_steps = false): FormData
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

		if (!$skip_steps) {
			foreach ($this->t_steps as $step) {
				if (!($step_form = $step->build($cleaned_fd))) {
					continue;
				}

				$step_form->validate($unsafe_fd, $cleaned_fd);
			}
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
	 *  version: string,
	 *  name: ?string,
	 *  action: ?Uri,
	 *  method: string,
	 *  fields: list<Field>,
	 *  steps: list<FormStep>,
	 *  expect: list<RuleSet>,
	 *  resume_scope: ?string,
	 *  resume_ttl: ?int
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		$expect_rules = \array_values(\array_filter(
			$this->t_pre_validation_rules,
			static fn (RuleSet $r) => !$r->isServerOnly()
		));

		return [
			'version'      => $this->getVersion(),
			'name'         => $this->t_name,
			'action'       => $this->t_submit_to,
			'method'       => $this->t_method,
			'fields'       => $this->t_fields,
			'steps'        => $this->t_steps,
			'expect'       => $expect_rules,
			'resume_scope' => $this->t_resume_scope?->value,
			'resume_ttl'   => null !== $this->t_resume_scope ? $this->t_resume_ttl : null,
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

			$field = $form->field($column->getName())
				->label($column->getMeta()->get('field.label'))
				->description($column->getMeta()->get('field.description'))
				->help($column->getMeta()->get('field.help'))

				->type($type)
				->required(!$type->isNullable());

			Utils::safeFrontendMeta($column, $field);
		}

		return $form;
	}
}
