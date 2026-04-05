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

use Closure;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\TypeDate;
use LogicException;
use Override;
use OZONE\Core\App\Context;
use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Exceptions\InvalidFormException;
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
class Form extends AbstractFieldContainer implements ArrayCapableInterface, MetaCapableInterface
{
	use ArrayCapableTrait;
	use FormFieldsTrait;
	use MetaCapableTrait;

	public const FORM_DATA_RESUME_CACHE_NAMESPACE = 'oz.form.resume';

	/**
	 * @var array<string,Fieldset>
	 */
	private array $t_fieldsets = [];

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
	 * Optional form ID, can be used to correlate form submissions with server-side state or logs.
	 */
	private ?string $t_id = null;

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
	#[Override]
	public function __destruct()
	{
		parent::__destruct();
		unset($this->t_fieldsets, $this->t_submit_to);
	}

	/**
	 * Gets the registered fieldsets.
	 *
	 * @return array<string, Fieldset>
	 */
	public function getFieldsets(): array
	{
		return $this->t_fieldsets;
	}

	/**
	 * Returns a short structural fingerprint of this form.
	 *
	 * @return string
	 */
	public function getVersion(): string
	{
		$fields = [];

		foreach ($this->getFields() as $field) {
			$fields[] = [
				'r'  => $field->getRef(),
				'rq' => $field->isRequired(),
				'm'  => $field->isMultiple(),
				't'  => \get_class($field->getType()),
			];
		}

		$fieldsets = [];

		foreach ($this->t_fieldsets as $fieldset) {
			$fieldsets[] = [
				'n'  => $fieldset->getName(),
				'sd' => $fieldset->isStatic() ? 'static' : 'dynamic',
			];
		}

		$descriptor = [
			'n'   => $this->getName(),
			'f'   => $fields,
			'fs'  => $fieldsets,
			'pr'  => \array_map(static fn (RuleSet $r) => $r->toArray(), $this->getPreValidationRules()),
			'po'  => \array_map(static fn (RuleSet $r) => $r->toArray(), $this->getPostValidationRules()),
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
	public function resumable(RequestScope $mode = RequestScope::STATE, int $ttl = 3600): static
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
	 * When form ID is set, it is also included in the cache key to allow multiple
	 * forms with different IDs to be active under the same resume scope without
	 * key collisions.
	 *
	 * @param string $scope_id Session stateID or user auth identifier
	 *
	 * @return string Non-empty cache key
	 */
	public function buildResumeCacheKey(string $scope_id): string
	{
		return ($this->t_id ? $this->t_id . '_' : '') . $this->getVersion() . '_' . Hasher::shorten(Hasher::hash32($scope_id));
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
	 * Creates a new static fieldset whose fields are defined by a mutate-in-place callback.
	 *
	 * The callback is executed immediately at definition time and receives the new
	 * {@see Fieldset} instance, allowing fields to be registered inline:
	 *
	 * ```php
	 * $form->fieldset('address', function (Fieldset $fs): void {
	 *     $fs->string('street')->required();
	 *     $fs->string('city')->required();
	 * });
	 * ```
	 *
	 * @param string                  $name     the fieldset name
	 * @param callable(Fieldset):void $callback populates the fieldset in-place
	 * @param null|RuleSet            $only_if  the fieldset condition
	 *
	 * @return Fieldset
	 */
	public function fieldset(string $name, callable $callback, ?RuleSet $only_if = null): Fieldset
	{
		$fs = Fieldset::static($this, $name, $callback, $only_if);

		$this->t_fieldsets[$name] = $fs;

		return $fs;
	}

	/**
	 * Creates a new dynamic fieldset whose structure is built at validation time.
	 *
	 * @param string                      $name    the fieldset name
	 * @param callable(FormData):Fieldset $factory builds the fieldset from accumulated cleaned data
	 * @param null|RuleSet                $only_if the fieldset condition
	 *
	 * @return Fieldset
	 */
	public function dynamicFieldset(string $name, callable $factory, ?RuleSet $only_if = null): Fieldset
	{
		$fs = Fieldset::dynamic($this, $name, $factory, $only_if);

		$this->t_fieldsets[$name] = $fs;

		return $fs;
	}

	/**
	 * Retrieves a previously registered fieldset by name.
	 *
	 * @param string $name the fieldset name
	 *
	 * @return null|Fieldset
	 */
	public function getFieldset(string $name): ?Fieldset
	{
		return $this->t_fieldsets[$name] ?? null;
	}

	/**
	 * Gets a ref for a given field or step name, prefixed with form name if any.
	 */
	#[Override]
	public function getRef(string $name): string
	{
		$name_prefix = $this->getName();

		if (empty($name_prefix)) {
			return $name;
		}

		return $name_prefix . '.' . $name;
	}

	/**
	 * Sets form ID.
	 */
	public function setId(string $id): static
	{
		$this->t_id = $id;

		return $this;
	}

	/**
	 * Gets form ID.
	 */
	public function getId(): ?string
	{
		return $this->t_id;
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
	 * Try resuming a previously saved form state.
	 *
	 * @return array{
	 *  0: null|FormData,
	 *  1: (Closure(): bool)
	 * }
	 */
	public function resume(Context $context): array
	{
		$cache        = null;
		$cache_key    = null;
		$prefilled    = null;

		if (null !== $this->t_resume_scope) {
			$scope_id = $this->t_resume_scope->resolveId($context);

			$cache     = CacheManager::persistent(self::FORM_DATA_RESUME_CACHE_NAMESPACE);
			$cache_key = $this->buildResumeCacheKey($scope_id);
			$cached    = $cache->get($cache_key);

			if (\is_array($cached) && isset($cached['version'], $cached['data']) && $cached['version'] === $this->getVersion()) {
				$prefilled = new FormData($cached['data']);
			}
		}

		$drop_callable = static fn () => $cache_key && $cache?->delete($cache_key);

		return [$prefilled, $drop_callable];
	}

	/**
	 * Saves validated form data for resuming later.
	 *
	 * @param Context  $context The request context to resolve the resume scope
	 * @param FormData $fd      Validated form data to save
	 */
	public function saveForLater(Context $context, FormData $fd): static
	{
		if (null === $this->t_resume_scope) {
			throw new LogicException('Form resume is not enabled for this form.');
		}

		$scope_id  = $this->t_resume_scope->resolveId($context);
		$cache     = CacheManager::persistent(self::FORM_DATA_RESUME_CACHE_NAMESPACE);
		$cache_key = $this->buildResumeCacheKey($scope_id);

		$cache->set($cache_key, [
			'version' => $this->getVersion(),
			'data'    => $fd->toArray(),
		], $this->t_resume_ttl);

		return $this;
	}

	/**
	 * Validates the form.
	 *
	 * @param FormData      $unsafe_fd  Raw (unsafe) form data from the request
	 * @param null|FormData $cleaned_fd Pre-filled validated data (e.g. from a resume cache); merged with newly validated fields
	 * @param bool          $shallow    When true, fieldsets are not traversed. Useful in step-by-step validation where only root-level fields are validated
	 *
	 * @return FormData
	 *
	 * @throws InvalidFormException
	 */
	public function validate(FormData $unsafe_fd, ?FormData $cleaned_fd = null, bool $shallow = false): FormData
	{
		$this->checkPreValidationRules($unsafe_fd);

		$cleaned_fd ??= new FormData();

		$this->checkFields($unsafe_fd, $cleaned_fd);

		$this->checkPostValidationRules($cleaned_fd);

		if (!$shallow) {
			foreach ($this->t_fieldsets as $fieldset) {
				if (!($built = $fieldset->build($cleaned_fd))) {
					continue;
				}

				$built->validate($unsafe_fd, $cleaned_fd);
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
	 *  fieldsets: list<Fieldset>,
	 *  expect: list<RuleSet>,
	 *  resume_scope: ?string,
	 *  resume_ttl: ?int
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		$expect_rules = \array_values(\array_filter(
			$this->getPreValidationRules(),
			static fn (RuleSet $r) => !$r->isServerOnly()
		));

		return [
			'version'      => $this->getVersion(),
			'name'         => $this->getName(),
			'action'       => $this->t_submit_to,
			'method'       => $this->t_method,
			'fields'       => $this->getFields(),
			'fieldsets'    => $this->t_fieldsets,
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

		$this->mergeContainerState($from);
		$this->t_fieldsets = \array_merge($this->t_fieldsets, $from->t_fieldsets);

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
