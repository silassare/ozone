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
use OZONE\Core\Forms\Interfaces\ResumableFormProviderInterface;
use OZONE\Core\Forms\Services\ResumableFormService;
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
 *  - t_provider_class : a class-string<ResumableFormProviderInterface>; form is fully managed
 *                       by the resumable-form pipeline, not validated by the normal bundle logic.
 *
 * An optional `t_doc_preview` (`fn():Form`) can be paired with a dynamic factory to expose
 * an `init_form` hint in the `x-oz-form` DYNAMIC extension for non-provider routes.
 *
 * Policy detection (for `make()` with auto-arity):
 *  - Form instance or zero-arg callable -> {@see RouteFormDocPolicy::STATIC}
 *  - One-arg+ callable                  -> {@see RouteFormDocPolicy::DYNAMIC}
 *
 * Passing {@see RouteFormDocPolicy::OPAQUE} or {@see RouteFormDocPolicy::DYNAMIC} explicitly
 * overrides the auto-detected value. There is no AUTO or EXTERNAL policy.
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

	/**
	 * @var null|class-string<ResumableFormProviderInterface> the provider class when the route
	 *                                                        delegates to the resumable-form pipeline
	 */
	private ?string $t_provider_class = null;

	private RouteFormDocPolicy $t_policy;

	private function __construct() {}

	public function __destruct()
	{
		unset($this->t_static_form, $this->t_static_factory, $this->t_dynamic_factory, $this->t_doc_preview, $this->t_provider_class);
	}

	/**
	 * Creates a declaration from a callable or Form with arity auto-detection.
	 *
	 * Detection rules:
	 *  - A Form instance or a zero-arg callable (`fn(): Form`) is stored as static and assigned
	 *    the {@see RouteFormDocPolicy::STATIC} policy: resolvable and documentable without a live
	 *    RouteInfo.
	 *  - A one-arg+ callable (`fn(RouteInfo $ri): ?Form`) is stored as dynamic and assigned the
	 *    {@see RouteFormDocPolicy::DYNAMIC} policy: requires a live RouteInfo at request time.
	 *
	 * Pass {@see RouteFormDocPolicy::OPAQUE} or {@see RouteFormDocPolicy::DYNAMIC} as `$policy` to
	 * override the auto-detected value. Passing {@see RouteFormDocPolicy::STATIC} has no
	 * effect on detection; use a Form instance or zero-arg callable instead.
	 *
	 * @param callable|Form           $form   the form definition or factory callable
	 * @param null|RouteFormDocPolicy $policy explicit override (OPAQUE or DYNAMIC); null = auto-detect
	 *
	 * @return static
	 */
	public static function make(callable|Form $form, ?RouteFormDocPolicy $policy = null): static
	{
		$decl = new self();

		if ($form instanceof Form) {
			$decl->t_static_form = $form;
			$decl->t_policy      = (RouteFormDocPolicy::OPAQUE === $policy || RouteFormDocPolicy::DYNAMIC === $policy)
				? $policy
				: RouteFormDocPolicy::STATIC;

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
			$decl->t_policy          = (RouteFormDocPolicy::OPAQUE === $policy)
				? RouteFormDocPolicy::OPAQUE
				: RouteFormDocPolicy::DYNAMIC;
		} else {
			$decl->t_static_factory = $closure;
			$decl->t_policy         = (RouteFormDocPolicy::OPAQUE === $policy || RouteFormDocPolicy::DYNAMIC === $policy)
				? $policy
				: RouteFormDocPolicy::STATIC;
		}

		return $decl;
	}

	/**
	 * Creates an explicitly dynamic declaration with an optional doc-gen preview.
	 *
	 * The runtime $factory receives a live RouteInfo and may return null to skip validation.
	 * Even for zero-arg callables, this named constructor always stores the form as dynamic and
	 * assigns the {@see RouteFormDocPolicy::DYNAMIC} policy.
	 *
	 * When $doc_preview is provided it is exposed as the `init_form` hint in the `x-oz-form`
	 * DYNAMIC extension for non-provider routes — useful when the form shape is known ahead of
	 * time despite being resolved at request time.
	 *
	 * @param callable(RouteInfo):?Form $factory     runtime factory - receives a live RouteInfo
	 * @param null|(callable():Form)    $doc_preview zero-arg preview factory for the extension hint
	 *
	 * @return static
	 */
	public static function dynamic(callable $factory, ?callable $doc_preview = null): static
	{
		$decl                    = new self();
		$decl->t_policy          = RouteFormDocPolicy::DYNAMIC;
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
	 * `x-oz-form: {policy:'opaque', ...}` to the operation — no `requestBody` schema.
	 *
	 * @param callable|Form $form
	 *
	 * @return static
	 */
	public static function opaque(callable|Form $form): static
	{
		return self::make($form, RouteFormDocPolicy::OPAQUE);
	}

	/**
	 * Creates a declaration that delegates form resolution entirely to a resumable-form provider.
	 *
	 * The route handler receives the completed {@see FormData} via {@see RouteInfo::getCleanFormData()},
	 * which is populated by {@see ResumableFormService::requireCompletion()}
	 * using the request header. No normal bundle validation is run.
	 *
	 * Policy is always {@see RouteFormDocPolicy::DYNAMIC}: the `x-oz-form` extension carries the
	 * provider name (when not `requiresRealContext`) and the provider's `initForm()` schema.
	 *
	 * @param class-string<ResumableFormProviderInterface> $class the provider class
	 *
	 * @return static
	 */
	public static function provider(string $class): static
	{
		if (!\is_a($class, ResumableFormProviderInterface::class, true)) {
			throw new RuntimeException(\sprintf(
				'Form provider class "%s" must implement %s.',
				$class,
				ResumableFormProviderInterface::class
			));
		}

		$decl                   = new self();
		$decl->t_policy         = RouteFormDocPolicy::DYNAMIC;
		$decl->t_provider_class = $class;

		return $decl;
	}

	/**
	 * Returns the provider class when this declaration was created via {@see provider()}, null otherwise.
	 *
	 * @return null|class-string<ResumableFormProviderInterface>
	 */
	public function getProviderClass(): ?string
	{
		return $this->t_provider_class;
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
	 *  - The policy is {@see RouteFormDocPolicy::OPAQUE} or {@see RouteFormDocPolicy::DYNAMIC}.
	 *  - The form is a dynamic factory (DYNAMIC routes never embed schema in requestBody).
	 *
	 * @return null|Form
	 */
	public function getDocForm(): ?Form
	{
		if (RouteFormDocPolicy::STATIC !== $this->t_policy) {
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

		// Dynamic factory without a preview: nothing to embed.
		return null;
	}

	/**
	 * Returns the doc-gen preview form for use as the `init_form` hint in the `x-oz-form`
	 * DYNAMIC extension on non-provider routes. Returns null when no preview was supplied.
	 *
	 * Only meaningful when the policy is {@see RouteFormDocPolicy::DYNAMIC} and no provider
	 * class is set. For provider-based DYNAMIC declarations the caller should use
	 * `$providerClass::initForm()` instead.
	 *
	 * @return null|Form
	 */
	public function getDocPreviewForm(): ?Form
	{
		if (null !== $this->t_doc_preview) {
			return ($this->t_doc_preview)();
		}

		return null;
	}

	/**
	 * Returns the documentation policy for this declaration.
	 *
	 * Policy is determined at construction time:
	 *  - {@see make()} auto-detects STATIC (Form/zero-arg) or DYNAMIC (one-arg+); OPAQUE or DYNAMIC
	 *    can override.
	 *  - {@see dynamic()} always returns DYNAMIC.
	 *  - {@see opaque()} always returns OPAQUE.
	 *  - {@see provider()} always returns DYNAMIC.
	 *
	 * @return RouteFormDocPolicy
	 */
	public function getPolicy(): RouteFormDocPolicy
	{
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
