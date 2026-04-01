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

namespace OZONE\Core\Router;

use Closure;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Router\Enums\RouteFormDocPolicy;
use ReflectionException;
use ReflectionFunction;

/**
 * Class RouteFormDeclaration.
 *
 * Wraps a route's form definition together with its documentation policy.
 *
 * Three storage variants (exactly one is set at construction time):
 *  - t_static_form    : a Form instance; documentable and resolvable without RouteInfo.
 *  - t_static_factory : a zero-arg callable `fn():Form`; documentable and resolvable without RouteInfo.
 *  - t_dynamic_factory: a one-arg+ callable `fn(RouteInfo):?Form`; requires a live RouteInfo.
 *
 * An optional `t_doc_preview` (`fn():Form`) can be paired with a dynamic factory to make the
 * route's schema visible in API docs at doc-gen time (no live RouteInfo required for that path).
 *
 * Arity detection (for the `AUTO` policy) uses `ReflectionFunction` on the closure at construction
 * time, not at invocation time, so mismatches surface early.
 *
 * @see RouteSharedOptions::form()
 * @see RouteFormDocPolicy
 */
final class RouteFormDeclaration
{
	/**
	 * @var null|Form a Form instance — resolvable and documentable without RouteInfo
	 */
	private ?Form $t_static_form = null;

	/**
	 * @var null|Closure zero-arg factory: fn():Form — resolvable and documentable without RouteInfo
	 */
	private ?Closure $t_static_factory = null;

	/**
	 * @var null|Closure one-arg+ factory: fn(RouteInfo):?Form — requires a live RouteInfo at runtime
	 */
	private ?Closure $t_dynamic_factory = null;

	/**
	 * @var null|Closure zero-arg doc preview: fn():Form — paired with a dynamic factory to expose
	 *                   a schema in API docs without a live RouteInfo
	 */
	private ?Closure $t_doc_preview = null;

	private RouteFormDocPolicy $t_policy;

	private function __construct() {}

	public function __destruct()
	{
		unset($this->t_static_form, $this->t_static_factory, $this->t_dynamic_factory, $this->t_doc_preview);
	}

	/**
	 * Creates a declaration from a callable or Form with arity auto-detection.
	 *
	 * Detection rules:
	 *  - A Form instance or a zero-arg callable (`fn(): Form`) is stored as static: resolvable
	 *    and documentable without a live RouteInfo.
	 *  - A one-arg+ callable (`fn(RouteInfo $ri): ?Form`) is stored as dynamic: requires a live
	 *    RouteInfo at request time; opaque in API docs unless $policy says otherwise.
	 *    To expose a schema for a dynamic form use {@see RouteFormDeclaration::dynamic()} instead.
	 *
	 * The $policy parameter is applied as-is on top of the detection result.
	 * {@see RouteFormDocPolicy::AUTO} leaves the visibility determined by whether the form is static
	 * (documentable) or dynamic (opaque). Use {@see RouteFormDocPolicy::OPAQUE} or
	 * {@see RouteFormDocPolicy::DISCOVERY_ONLY} to explicitly hide the form from docs regardless.
	 *
	 * @param callable|Form      $form   the form definition or factory callable
	 * @param RouteFormDocPolicy $policy documentation policy (AUTO by default)
	 *
	 * @return static
	 */
	public static function make(callable|Form $form, RouteFormDocPolicy $policy = RouteFormDocPolicy::AUTO): self
	{
		$decl           = new self();
		$decl->t_policy = $policy;

		if ($form instanceof Form) {
			// Only register forms that have an explicit stable key set via Form::key().
			// Auto-keyed forms (oz:form:auto:N) are ephemeral and must NOT be discoverable.
			if ($form->isNamed()) {
				$form->register();
			}
			$decl->t_static_form = $form;

			return $decl;
		}

		// Detect callable arity via reflection.
		// 0 required params -> static (can be called at doc-gen time without RouteInfo).
		// 1+ required params -> dynamic (requires RouteInfo at request time).
		$closure    = Closure::fromCallable($form);
		$is_dynamic = true;

		try {
			$is_dynamic = (new ReflectionFunction($closure))->getNumberOfRequiredParameters() > 0;
		} catch (ReflectionException) {
			// Conservative default: treat as dynamic when reflection is unavailable.
		}

		if ($is_dynamic) {
			$decl->t_dynamic_factory = $closure;
		} else {
			$decl->t_static_factory = $closure;
		}

		return $decl;
	}

	/**
	 * Creates an explicitly dynamic declaration with an optional doc-gen preview.
	 *
	 * The runtime $factory receives a live RouteInfo and may return null to skip validation.
	 * Even for zero-arg callables, this named constructor always stores the form as dynamic.
	 *
	 * When $doc_preview is provided, the declaration is documentable: {@see getDocForm()} calls
	 * the preview instead of returning null.  Without it the form is opaque in API docs.
	 *
	 * @param callable(RouteInfo):?Form $factory     runtime factory — receives a live RouteInfo
	 * @param null|(callable():Form)    $doc_preview zero-arg preview factory for doc generation
	 *
	 * @return static
	 */
	public static function dynamic(callable $factory, ?callable $doc_preview = null): self
	{
		$decl                    = new self();
		$decl->t_policy          = RouteFormDocPolicy::AUTO;
		$decl->t_dynamic_factory = Closure::fromCallable($factory);

		if (null !== $doc_preview) {
			$decl->t_doc_preview = Closure::fromCallable($doc_preview);
		}

		return $decl;
	}

	/**
	 * Creates a declaration that is explicitly hidden from API docs.
	 *
	 * The form works normally at request time. The generated OpenAPI spec adds
	 * `x-oz-form: {policy: opaque}` to the operation instead of a `requestBody` schema.
	 *
	 * @param callable|Form $form
	 *
	 * @return static
	 */
	public static function opaque(callable|Form $form): self
	{
		return self::make($form, RouteFormDocPolicy::OPAQUE);
	}

	/**
	 * Creates a declaration that signals clients to use the form discovery endpoint.
	 *
	 * The form works normally at request time. The generated OpenAPI spec adds
	 * `x-oz-form: {policy: discovery_only}` to the operation instead of embedding the schema.
	 *
	 * @param callable|Form $form
	 *
	 * @return static
	 */
	public static function discoveryOnly(callable|Form $form): self
	{
		return self::make($form, RouteFormDocPolicy::DISCOVERY_ONLY);
	}

	/**
	 * Resolves the form at request time. Returns null when the factory returns null.
	 *
	 * @param RouteInfo $ri
	 *
	 * @return null|Form
	 *
	 * @throws RuntimeException when the factory returns an unexpected type
	 */
	public function resolve(RouteInfo $ri): ?Form
	{
		if (null !== $this->t_static_form) {
			return $this->t_static_form;
		}

		if (null !== $this->t_static_factory) {
			$result = ($this->t_static_factory)();
		} elseif (null !== $this->t_dynamic_factory) {
			$result = ($this->t_dynamic_factory)($ri);
		} else {
			return null;
		}

		if (null === $result || $result instanceof Form) {
			return $result;
		}

		throw new RuntimeException(\sprintf(
			'Form factory must return an instance of "%s" or null, got: %s.',
			Form::class,
			\get_debug_type($result),
		));
	}

	/**
	 * Returns the form for API doc generation (no RouteInfo needed). Returns null when:
	 *  - The policy is {@see RouteFormDocPolicy::OPAQUE} or {@see RouteFormDocPolicy::DISCOVERY_ONLY}.
	 *  - The form is a dynamic factory with no preview callable.
	 *
	 * @return null|Form
	 */
	public function getDocForm(): ?Form
	{
		if (RouteFormDocPolicy::OPAQUE === $this->t_policy || RouteFormDocPolicy::DISCOVERY_ONLY === $this->t_policy) {
			return null;
		}

		if (null !== $this->t_static_form) {
			return $this->t_static_form;
		}

		if (null !== $this->t_static_factory) {
			return ($this->t_static_factory)();
		}

		if (null !== $this->t_doc_preview) {
			return ($this->t_doc_preview)();
		}

		// Dynamic factory without a preview: opaque in docs.
		return null;
	}

	/**
	 * Returns the documentation policy for this declaration.
	 *
	 * When the declared policy is {@see RouteFormDocPolicy::AUTO} but the form is a dynamic factory
	 * with no doc preview, no schema can be surfaced at doc-gen time, so this method promotes
	 * the effective policy to {@see RouteFormDocPolicy::OPAQUE} — ensuring the `x-oz-form` extension
	 * is still added to the operation instead of the form silently disappearing from the spec.
	 *
	 * @return RouteFormDocPolicy
	 */
	public function getPolicy(): RouteFormDocPolicy
	{
		if (
			RouteFormDocPolicy::AUTO === $this->t_policy
			&& null !== $this->t_dynamic_factory
			&& null === $this->t_doc_preview
		) {
			return RouteFormDocPolicy::OPAQUE;
		}

		return $this->t_policy;
	}

	/**
	 * Returns true when the form requires a live RouteInfo to be resolved at request time.
	 *
	 * @return bool
	 */
	public function isDynamic(): bool
	{
		return null !== $this->t_dynamic_factory;
	}
}
