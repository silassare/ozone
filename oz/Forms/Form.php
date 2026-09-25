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
use JsonException;
use JsonSerializable;
use LogicException;
use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\JSONResponse;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Traits\FieldContainerHelpersTrait;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Http\Request;
use OZONE\Core\Http\Uri;
use OZONE\Core\Router\Route;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\RouteSharedOptions;
use OZONE\Core\Stores\StateRegistry;
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
	use FieldContainerHelpersTrait;
	use MetaCapableTrait;

	public const FORM_DATA_RESUME_CACHE_NAMESPACE = 'oz:form:resume';

	/**
	 * Fieldsets keyed by their ref, not their local name — same rationale as
	 * {@see AbstractFieldContainer::$t_fields}.
	 *
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
	 * The version its author gives it ({@see self::version()}), or null to fingerprint it.
	 */
	private ?string $t_version = null;

	/**
	 * Form constructor.
	 *
	 * @param null|string $name      optional name for field namespacing (validated as a dot-path segment)
	 * @param string      $method    The form submit method, default is POST
	 * @param null|Uri    $submit_to the URI the client should submit the form to, when not the current one
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
	 * Gets the registered fieldsets.
	 *
	 * @return array<string, Fieldset>
	 */
	public function getFieldsets(): array
	{
		return $this->t_fieldsets;
	}

	/**
	 * Sets this form's version, instead of the fingerprint {@see self::getVersion()} computes.
	 *
	 * For a form generated from a definition (the hash of that definition), or one whose texts change
	 * per request: the author then bumps it whenever the form changes in a way a saved draft of it
	 * should not survive, and nothing is computed.
	 *
	 * @return $this
	 */
	public function version(string $version): static
	{
		if ('' === $version) {
			throw new LogicException('A form version cannot be empty.');
		}

		$this->t_version = $version;

		return $this;
	}

	/**
	 * Gets this form's version: the one set with {@see self::version()}, else a fingerprint of what a
	 * client is sent of it.
	 *
	 * The fingerprint covers the whole bundle (fields and their types, conditions, labels, fieldsets,
	 * every rule set) and leaves out the previews of public values, which may differ per user and per
	 * moment (seats left) without the form changing. It is the same on the server and in what a
	 * client is sent, and a client reads it to tell whether a saved draft still fits the form.
	 */
	public function getVersion(): string
	{
		if (null !== $this->t_version) {
			return $this->t_version;
		}

		$bundle = AsyncValue::withoutPreview(fn (): array => self::resolve($this->bundle()));

		return Hasher::shorten(Hasher::hash32(\json_encode($bundle, \JSON_THROW_ON_ERROR)));
	}

	/**
	 * Enables incremental form submission by caching validated field values across requests.
	 *
	 * When enabled, previously validated fields stored in the cache satisfy their required
	 * constraints on subsequent requests, so the client only needs to submit fields it has
	 * not yet submitted. The cache is scoped using the provided {@see RequestScope} strategy.
	 *
	 * Routes honour it: when a form attached to a route declares it,
	 * {@see RouteInfo::checkRouteForm()} replays what an earlier
	 * failed attempt validated, saves progress when validation fails again, and clears
	 * the entry once the handler succeeds. The entry is partitioned by route. Outside
	 * the router, drive it with {@see self::resume()}, {@see self::validate()} and
	 * {@see self::saveForLater()}.
	 *
	 * This is unrelated to {@see RouteSharedOptions::resumable()},
	 * which drives the multi-step form session state machine.
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
	 * The key combines this form's identity (see {@see self::resumeIdentity()})
	 * with a hash of the scope identifier, so two forms active under the same
	 * scope never share an entry.
	 *
	 * The key deliberately does NOT include {@see self::getVersion()}: editing a
	 * form should not orphan every in-flight resume session. Safety comes from
	 * {@see self::keepKnownRefs()} dropping values whose field no longer exists,
	 * and from each surviving value being re-checked against its field during
	 * validation.
	 *
	 * An optional $partition splits the entry further, so the same form resumed in
	 * two places (e.g. two routes) never shares data. It is hashed, so any string works.
	 *
	 * @param string      $scope_id  Session stateID or user auth identifier
	 * @param null|string $partition Optional sub-key, e.g. {@see Route::key()}
	 *
	 * @return string Non-empty cache key
	 */
	public function buildResumeCacheKey(string $scope_id, ?string $partition = null): string
	{
		$key = $this->resumeIdentity();

		if (null !== $partition) {
			$key .= '_' . Hasher::shorten(Hasher::hash32($partition));
		}

		return $key . '_' . Hasher::shorten(Hasher::hash32($scope_id));
	}

	/**
	 * Set form submit to uri.
	 */
	public function submitTo(Uri $uri): static
	{
		$this->t_submit_to = $uri;

		return $this;
	}

	/**
	 * Sets form submit method.
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
	 * Attach a condition with {@see Fieldset::if()} on the returned instance:
	 *
	 * ```php
	 * $form->fieldset('advanced', $callback)->if()->eq('type', 'advanced');
	 * ```
	 *
	 * @param string                  $name     the fieldset name
	 * @param callable(Fieldset):void $callback populates the fieldset in-place
	 *
	 * @return Fieldset
	 */
	public function fieldset(string $name, callable $callback): Fieldset
	{
		$fs = Fieldset::static($this, $name, $callback);

		$this->t_fieldsets[$fs->getSelfRef()] = $fs;

		return $fs;
	}

	/**
	 * Creates a new dynamic fieldset whose structure is built at validation time.
	 *
	 * Attach a condition with {@see Fieldset::if()} on the returned instance.
	 *
	 * @param string                                   $name    the fieldset name
	 * @param callable(FormValidationContext):Fieldset $factory builds the fieldset from the context
	 *
	 * @return Fieldset
	 */
	public function dynamicFieldset(string $name, callable $factory): Fieldset
	{
		$fs = Fieldset::dynamic($this, $name, $factory);

		$this->t_fieldsets[$fs->getSelfRef()] = $fs;

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
		// Accept either a full ref or a local name; merged fieldsets keep their
		// original form's namespace and can only be addressed by their full ref.
		return $this->t_fieldsets[$name] ?? $this->t_fieldsets[$this->getRef($name)] ?? null;
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
	 * @param Context     $context   The request context to resolve the resume scope
	 * @param null|string $partition Must match the one given to {@see self::saveForLater()}
	 *
	 * @return array{
	 *  0: null|FormDataClean,
	 *  1: (Closure(): bool)
	 * }
	 *
	 * @throws JsonException
	 */
	public function resume(Context $context, ?string $partition = null): array
	{
		$cache        = null;
		$cache_key    = null;
		$prefilled    = null;

		if (null !== $this->t_resume_scope) {
			$scope_id = $this->t_resume_scope->resolveId($context);

			$cache     = StateRegistry::store(self::FORM_DATA_RESUME_CACHE_NAMESPACE);
			$cache_key = $this->buildResumeCacheKey($scope_id, $partition);
			$cached    = $cache->get($cache_key);

			if (\is_array($cached) && isset($cached['data']) && \is_array($cached['data'])) {
				// Keep only values whose field still exists in the current definition.
				// Anything kept is re-checked against its field during validation
				// (see AbstractFieldContainer::checkFields), so a stale entry can
				// never smuggle an unvalidated value into the handler.
				$prefilled = new FormDataClean($this->keepKnownRefs($cached['data']));
			}
		}

		$drop_callable = static fn () => $cache_key && $cache?->delete($cache_key);

		return [$prefilled, $drop_callable];
	}

	/**
	 * Saves validated form data for resuming later.
	 *
	 * @param Context       $context    The request context to resolve the resume scope
	 * @param FormDataClean $cleaned_fd Validated form data to save
	 * @param null|string   $partition  Optional sub-key, see {@see self::buildResumeCacheKey()}
	 *
	 * @throws JsonException
	 */
	public function saveForLater(Context $context, FormDataClean $cleaned_fd, ?string $partition = null): static
	{
		if (null === $this->t_resume_scope) {
			throw new LogicException('Form resume is not enabled for this form.');
		}

		$scope_id  = $this->t_resume_scope->resolveId($context);
		$store     = StateRegistry::store(self::FORM_DATA_RESUME_CACHE_NAMESPACE);
		$cache_key = $this->buildResumeCacheKey($scope_id, $partition);

		$store->set($cache_key, [
			'version' => $this->getVersion(),
			'data'    => $cleaned_fd->toArray(),
		], $this->t_resume_ttl);

		return $this;
	}

	/**
	 * Validates the form.
	 *
	 * @param FormData           $unsafe_fd  Raw (unsafe) form data from the request
	 * @param null|FormDataClean $cleaned_fd Pre-filled validated data (e.g. from a resume cache);
	 *                                       merged with newly validated fields
	 *
	 * @return FormDataClean
	 *
	 * @throws InvalidFormException
	 */
	public function validate(FormData $unsafe_fd, ?FormDataClean $cleaned_fd = null): FormDataClean
	{
		$cleaned_fd ??= new FormDataClean();

		$this->assertRulesFit();

		$ctx = new FormValidationContext($unsafe_fd, $cleaned_fd);

		// Everything this pass will validate, so a condition reading a field that is
		// validated later is caught (see FormValidationContext::assertReadable()).
		$ctx->markPending($this->knownFieldRefs());

		$this->checkPreValidationRules($ctx);

		$this->checkFields($ctx);

		foreach ($this->t_fieldsets as $fieldset) {
			$condition = $fieldset->getIf();

			if (null !== $condition) {
				$ctx->assertReadable($condition, $fieldset->getSelfRef());
			}

			$built = $fieldset->build($ctx);

			if (null === $built) {
				// Skipped: its fields stay absent for good, so reading them is fine.
				$ctx->markProcessed(...\array_keys($fieldset->getFields()));

				continue;
			}

			if ($fieldset->isDynamic()) {
				// Its fields only exist now that it is built.
				$ctx->markPending(\array_keys($built->getFields()));
			}

			$built->validate($ctx);
		}

		// Runs last so form-level assertions can see fieldset values.
		$this->checkPostValidationRules($ctx);

		return $cleaned_fd;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Every rule set is sent (`expect`, `ensure`, and a fieldset's own), so a client can check a form
	 * in the order the server does. Server-only {@see RuleSet} entries (those holding an
	 * {@see AsyncValue::secret()}) serialize to `{ref, $secret: true}` rather than exposing their
	 * operands. The client cannot evaluate them locally; it sends their refs to the evaluate endpoint
	 * and gets a pass/fail back per ref. Omitting them entirely would leave the client unable to
	 * discover that the rules exist at all. A literal operand is sent as it is: a value the client must
	 * not see belongs in an {@see AsyncValue::secret()}.
	 *
	 * @return array{
	 *  version: string,
	 *  name: ?string,
	 *  action: ?string,
	 *  method: string,
	 *  fields: array<string, Field>,
	 *  fieldsets: array<string, Fieldset>,
	 *  expect: list<RuleSet>,
	 *  ensure: list<RuleSet>,
	 *  resume_scope: ?string,
	 *  resume_ttl: ?int
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		$this->assertRulesFit();

		return ['version' => $this->getVersion(), ...$this->bundle()];
	}

	/**
	 * What a client is sent of this form: {@see self::toArray()} resolved to plain values inside
	 * {@see AsyncValue::withPreview()}, so a public value carries its preview.
	 *
	 * Every path that sends a form to a client goes through here (the envelope's `form`,
	 * {@see JSONResponse::toArray()}, and the `init_form` of the OpenAPI `x-oz-form`
	 * extension): resolved now, since the fields and rule sets {@see self::toArray()} holds are
	 * otherwise serialized when encoded, which may be outside the preview scope.
	 *
	 * @return array<string, mixed>
	 */
	public function toClientArray(): array
	{
		return AsyncValue::withPreview(fn (): array => self::resolve($this->toArray()));
	}

	/**
	 * Merges a given form to this.
	 *
	 * Fields and fieldsets are cloned, and keep the source form as their parent,
	 * so their refs are identical whether read from the source or from the merged
	 * bundle - and identical across requests. A ref already present in this form
	 * is a collision and throws rather than silently overwriting.
	 *
	 * @param Form $from
	 *
	 * @return $this
	 *
	 * @throws RuntimeException when a field or fieldset ref collides
	 */
	public function merge(self $from): static
	{
		if (!isset($this->t_submit_to)) {
			$this->t_submit_to = $from->t_submit_to;
		}

		$this->mergeContainerState($from);

		$fieldsets = [];

		foreach ($from->t_fieldsets as $ref => $fieldset) {
			if (isset($this->t_fieldsets[$ref])) {
				throw new RuntimeException(\sprintf(
					'Cannot merge form: fieldset "%s" is already defined. '
						. 'Give one of the two forms a name so their fieldsets do not collide.',
					$ref
				));
			}

			$fieldsets[$ref] = clone $fieldset;
		}

		$this->t_fieldsets = \array_merge($this->t_fieldsets, $fieldsets);

		// First non-null wins for both the id and the resume scope.
		$this->t_id ??= $from->t_id;

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
			$name  = $table;
			$table = db()->getTable($name);

			if (null === $table) {
				// A fatal on a null here would say "call to a member function on null" and name this
				// file, not the table the caller got wrong.
				throw new RuntimeException(\sprintf('Unable to build a form: no such table "%s".', $name));
			}
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

	/**
	 * The bundle a client is sent, but its version.
	 *
	 * @return array{
	 *  name: ?string,
	 *  action: ?string,
	 *  method: string,
	 *  fields: array<string, Field>,
	 *  fieldsets: array<string, Fieldset>,
	 *  expect: list<RuleSet>,
	 *  ensure: list<RuleSet>,
	 *  resume_scope: ?string,
	 *  resume_ttl: ?int
	 * }
	 */
	private function bundle(): array
	{
		return [
			'name'         => $this->getName(),
			// The absolute path, not the whole URI: a client addresses the server it is talking to,
			// whatever host answers for it.
			'action'       => $this->t_submit_to?->getAbsolutePath(),
			'method'       => $this->t_method,
			'fields'       => $this->getFields(),
			'fieldsets'    => $this->t_fieldsets,
			'expect'       => $this->getPreValidationRules(),
			// Checked last, once every field and fieldset is validated.
			'ensure'       => $this->getPostValidationRules(),
			'resume_scope' => $this->t_resume_scope?->value,
			'resume_ttl'   => null !== $this->t_resume_scope ? $this->t_resume_ttl : null,
		];
	}

	/**
	 * Identifies this form for resume-cache purposes.
	 *
	 * Prefers an explicit id, then the form name, and only falls back to the
	 * structural {@see self::getVersion()} when neither is set. The fallback is
	 * safe but brittle: every edit to the form changes the key, so all in-flight
	 * resume data for it is orphaned. Set an id on forms you intend to resume.
	 */
	private function resumeIdentity(): string
	{
		return $this->t_id ?? $this->getName() ?? $this->getVersion();
	}

	/**
	 * Drops every entry that does not map to a field ref in this form.
	 *
	 * @param array $data raw cached payload
	 *
	 * @return array
	 */
	private function keepKnownRefs(array $data): array
	{
		$source = new FormDataClean($data);
		$kept   = new FormDataClean();

		foreach ($this->knownFieldRefs() as $ref) {
			if ($source->has($ref)) {
				$kept->set($ref, $source->get($ref));
			}
		}

		return $kept->toArray();
	}

	/**
	 * Checks every rule set of this form against the types of the fields it reads
	 * ({@see RuleSet::assertOperatorsFit()}), so a mistake is reported when the form is
	 * discovered or first validated, not when a client happens to hit it.
	 *
	 * @throws RuntimeException
	 */
	private function assertRulesFit(): void
	{
		$fields = $this->getFields();
		$sets   = [...$this->getPreValidationRules(), ...$this->getPostValidationRules()];

		foreach ($this->t_fieldsets as $fieldset) {
			if ($fieldset->isStatic()) {
				$fields = \array_merge($fields, $fieldset->getFields());
			}
		}

		foreach ($fields as $field) {
			$if = $field->getIf();

			if (null !== $if) {
				$sets[] = $if;
			}

			$type = $field->getType();

			if ($type instanceof TypesSwitcher) {
				\array_push($sets, ...$type->getConditions());
			}
		}

		foreach ($this->t_fieldsets as $fieldset) {
			$if = $fieldset->getIf();

			if (null !== $if) {
				$sets[] = $if;
			}

			if ($fieldset->isStatic()) {
				\array_push($sets, ...$fieldset->getPreValidationRules(), ...$fieldset->getPostValidationRules());
			}
		}

		foreach ($sets as $set) {
			$set->assertOperatorsFit($fields);
		}
	}

	/**
	 * Every field ref this form can currently accept, including static fieldsets.
	 *
	 * Dynamic fieldsets are skipped: their shape is only known once the data they
	 * depend on has been validated, so nothing can be replayed into them.
	 *
	 * @return list<string>
	 */
	private function knownFieldRefs(): array
	{
		$refs = \array_keys($this->getFields());

		foreach ($this->t_fieldsets as $fieldset) {
			if ($fieldset->isStatic()) {
				foreach (\array_keys($fieldset->getFields()) as $ref) {
					$refs[] = $ref;
				}
			}
		}

		return $refs;
	}

	/**
	 * Resolves what json_encode() would serialize, keeping arrays and objects as they are.
	 */
	private static function resolve(mixed $value): mixed
	{
		if ($value instanceof JsonSerializable) {
			return self::resolve($value->jsonSerialize());
		}

		if (\is_array($value)) {
			return \array_map(self::resolve(...), $value);
		}

		return $value;
	}
}
