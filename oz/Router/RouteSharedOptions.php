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

use InvalidArgumentException;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\Enums\AuthenticationMethodScheme;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Exceptions\RateLimitReachedException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormDiscoveryRouteInterceptor;
use OZONE\Core\Forms\Resume\FormResumeRouteInterceptor;
use OZONE\Core\Forms\Resume\Interfaces\ResumableFormProviderInterface;
use OZONE\Core\Forms\Resume\ResumableFormProvider;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Enums\RouteFormDocPolicy;
use OZONE\Core\Router\Guards\CSRFRouteGuard;
use OZONE\Core\Router\Interfaces\RouteGuardInterface;
use OZONE\Core\Router\Interfaces\RouteGuardProviderInterface;
use OZONE\Core\Router\Interfaces\RouteInterceptorInterface;
use OZONE\Core\Router\Interfaces\RouteMiddlewareInterface;
use OZONE\Core\Router\Interfaces\RouteRateLimitInterface;
use OZONE\Core\Router\Traits\RouteGuardShortcutsTrait;

/**
 * Class RouteSharedOptions.
 *
 * The options a route or a group declares. What a route inherits from its groups is read
 * through {@see self::resolved()}.
 */
class RouteSharedOptions
{
	use RouteGuardShortcutsTrait;

	public const PRIORITY_RUN_LAST    = -1;
	public const PRIORITY_RUN_DEFAULT = 0;

	/**
	 * @var null|RouteSharedOptions
	 */
	protected readonly ?RouteSharedOptions $parent;
	protected readonly string $path;
	protected int $priority = self::PRIORITY_RUN_DEFAULT;

	protected ?RequestScope $csrf_scope = null;

	protected ?bool $csrf_disabled = null;

	protected ?RequestScope $route_resume_scope = null;

	protected int $route_resume_ttl = 3600;

	/**
	 * @var array<string,string>
	 */
	protected array $route_params = [];

	/**
	 * @var list<(callable(RouteInfo):?RouteGuardInterface)|RouteGuardInterface>
	 */
	protected array $guards = [];

	/**
	 * Structured descriptors for semantic guards set via `withAuthentication`,
	 * `withAuthenticatedUser`, `withAuthorization`, `withRole`,
	 * `withAccessRights`, etc.  Populated in parallel with {@see $guards}.
	 *
	 * Each entry is an associative array with at least a `type` key.
	 *
	 * @var list<array{type: string, ...}>
	 */
	protected array $guard_descriptors = [];

	/**
	 * @var list<(callable(RouteInfo):?Response)|RouteMiddlewareInterface>
	 */
	protected array $middlewares = [];

	/**
	 * @var array<string, class-string<RouteInterceptorInterface>>
	 */
	protected array $interceptors = [];

	protected ?RouteFormDeclaration $form_declaration = null;

	/**
	 * Bumped on every option change, of any route or group: a cached
	 * {@see ResolvedRouteOptions} built at an older revision may miss a group change.
	 */
	private static int $revision = 0;

	private string $name = '';

	/**
	 * @var list<class-string<AuthenticationMethodInterface>>
	 */
	private array $authentication_methods = [];

	private ?ResolvedRouteOptions $resolved = null;

	private int $resolved_revision = -1;

	/**
	 * The cached full path of {@see self::getPath()}, and the option revision it was computed at.
	 */
	private ?string $full_path = null;

	private int $full_path_revision = -1;

	/**
	 * RouteSharedOptions constructor.
	 *
	 * @param string                  $path
	 * @param null|RouteSharedOptions $parent
	 */
	protected function __construct(
		string $path,
		?self $parent = null
	) {
		$this->path   = $path;
		$this->parent = $parent;
	}

	/**
	 * Define the route name.
	 *
	 * @param string $name
	 *
	 * @return $this
	 */
	public function name(string $name): static
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * Defines a rate limit.
	 *
	 * @param (callable(RouteInfo):?RouteRateLimitInterface)|RouteRateLimitInterface $limit_provider
	 *
	 * @return $this
	 */
	public function rateLimit(callable|RouteRateLimitInterface $limit_provider): static
	{
		return $this->middleware(static function (RouteInfo $ri) use ($limit_provider) {
			if ($limit_provider instanceof RouteRateLimitInterface) {
				$limit = $limit_provider;
			} else {
				$limit = $limit_provider($ri);

				if (null === $limit) {
					return null;
				}

				if (!$limit instanceof RouteRateLimitInterface) {
					throw (new RuntimeException(\sprintf(
						'Route rate limit provider should return instance of "%s" or "null" not: %s',
						RouteRateLimitInterface::class,
						\get_debug_type($limit)
					)))->suspectCallable($limit_provider);
				}
			}

			$rate_limiter = RouteRateLimiter::get($ri, $limit);

			if (!$rate_limiter->hit()) {
				throw new RateLimitReachedException();
			}

			$status = $rate_limiter->status();

			$context  = $ri->getContext();
			$response = $context->getResponse();

			return $response
				->withHeader('X-RateLimit-Limit', (string) $status['limit'])
				->withHeader('X-RateLimit-Remaining', (string) $status['remaining'])
				->withHeader('X-RateLimit-Reset', (string) $status['reset']);
		});
	}

	/**
	 * Defines allowed authentication methods.
	 *
	 * @param AuthenticationMethodScheme|string ...$allowed_methods
	 *
	 * @return $this
	 */
	public function withAuthentication(AuthenticationMethodScheme|string ...$allowed_methods): static
	{
		$allowed_methods = self::atLeastOne($allowed_methods, 'authentication method');

		foreach ($allowed_methods as $entry) {
			if (!\is_string($entry)) {
				$entry = $entry->value;
			}

			if (\class_exists($entry)) {
				if (!\is_subclass_of($entry, AuthenticationMethodInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Auth method "%s" should be subclass of: %s',
						$entry,
						AuthenticationMethodInterface::class
					));
				}
				$auth = $entry;
			} else {
				$auth = Auth::method($entry);
			}

			$this->authentication_methods[] = $auth;
		}

		self::changed();

		return $this;
	}

	/**
	 * Adds a guard that check CSRF token.
	 *
	 * @return $this
	 */
	public function withCSRF(RequestScope $scope): static
	{
		$has_csrf_guard_in_tree = $this->getCSRFScope();

		$this->csrf_scope    = $scope;
		$this->csrf_disabled = false;

		$this->guard_descriptors[] = [
			'type'  => 'csrf',
			'scope' => $scope->value,
		];

		self::changed();

		if ($has_csrf_guard_in_tree) {
			// Don't add another guard if we already have one in parent.
			return $this;
		}

		return $this->guard(fn () => new CSRFRouteGuard($this->csrf_scope));
	}

	/**
	 * Gets CSRF scope defined for this route or its parent.
	 *
	 * @return null|RequestScope
	 */
	public function getCSRFScope(): ?RequestScope
	{
		return $this->resolved()->csrf_scope;
	}

	/**
	 * Opts this route (or group, with its routes) out of the default CSRF check of session
	 * requests (`OZ_CSRF_SESSION_DEFAULT`), e.g. for a webhook or a form another site posts on
	 * purpose. A route of the group can opt back in with {@see self::withCSRF()}.
	 *
	 * @return $this
	 */
	public function withoutCSRF(): static
	{
		$this->csrf_disabled = true;

		self::changed();

		return $this;
	}

	/**
	 * Whether this route or its parent opted out of the default CSRF check.
	 */
	public function isCSRFDisabled(): bool
	{
		return $this->resolved()->csrf_disabled;
	}

	/**
	 * Makes this route (or group) resumable through the form session state machine.
	 *
	 * Resume requests on the route are handled by {@see FormResumeRouteInterceptor}
	 * with {@see ResumableFormProvider}, whose single step is the route's form
	 * bundle; the completed session, bound to this route, is then submitted with the
	 * resume-ref header. Inherited from parent; innermost definition wins.
	 *
	 * This is independent of {@see Form::resumable()}: that per-form cache is replayed
	 * by {@see RouteInfo::checkRouteForm()} only when a form itself opts in.
	 *
	 * @param RequestScope $scope The scoping strategy for the resume session
	 * @param int          $ttl   Session TTL in seconds (default: 3600)
	 *
	 * @return $this
	 */
	public function resumable(RequestScope $scope = RequestScope::STATE, int $ttl = 3600): static
	{
		$this->route_resume_scope = $scope;
		$this->route_resume_ttl   = $ttl;

		self::changed();

		return $this;
	}

	/**
	 * Add guard.
	 *
	 * The route guard may be a:
	 * - guard provider name in configs `oz.guards.providers`
	 * - a fully qualified classname implementing {@see RouteGuardProviderInterface}
	 * - an instance of {@see RouteGuardInterface}
	 * - or a callable that will be called with {@see RouteInfo} as argument
	 *   and should return an instance of {@see RouteGuardInterface} or null.
	 *
	 * @param (callable(RouteInfo):?RouteGuardInterface)|RouteGuardInterface|string $guard
	 *
	 * @return $this
	 */
	public function guard(callable|RouteGuardInterface|string $guard): static
	{
		if (\is_string($guard)) { // class FQCN or provider name
			if (\class_exists($guard)) { // class FQCN
				$provider_class = $guard;
				if (!\is_subclass_of($provider_class, RouteGuardProviderInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Route guard provider "%s" should be subclass of: %s',
						$provider_class,
						RouteGuardProviderInterface::class
					));
				}
			} else { // provider name
				$provider_class = Guards::provider($guard);
			}

			/** @var RouteGuardProviderInterface $provider_class */
			$guard = [$provider_class, 'getGuard'];
		}

		$this->guards[] = $guard;

		self::changed();

		return $this;
	}

	/**
	 * Add a middleware.
	 *
	 * Middlewares are executed in the order they are defined, after guards.
	 * They can be added at any level of the route/group tree and are inherited by child routes/groups;
	 * they will be executed in a depth-first manner (i.e. parent middlewares run before child middlewares).
	 *
	 * @param (callable(RouteInfo):?Response)|RouteMiddlewareInterface|string $middleware
	 *
	 * @return $this
	 */
	public function middleware(callable|RouteMiddlewareInterface|string $middleware): static
	{
		if (\is_string($middleware)) { // class FQCN or provider name
			if (\class_exists($middleware)) { // class FQCN
				if (!\is_subclass_of($middleware, RouteMiddlewareInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Route middleware "%s" should be subclass of: %s',
						$middleware,
						RouteMiddlewareInterface::class
					));
				}

				/**
				 * @psalm-suppress UnnecessaryVarAnnotation
				 *
				 * @var class-string<RouteMiddlewareInterface> $mdl_class
				 */
				$mdl_class = $middleware;
			} else { // middleware name
				$mdl_class = Middlewares::get($middleware);
			}

			$mdl = $mdl_class::instance();

			$this->middlewares[] = $mdl;
		} else {
			$this->middlewares[] = $middleware;
		}

		self::changed();

		return $this;
	}

	/**
	 * Add an interceptor.
	 *
	 * Route interceptors are executed in the order they are defined after guards and middlewares.
	 * The first interceptor whose {@see RouteInterceptorInterface::shouldIntercept()} returns true
	 * short-circuits the rest of the chain: its {@see RouteInterceptorInterface::handle()} is
	 * called in place of the route handler.
	 *
	 * NOTE: route form will not be validated when a route interceptor intercepts the request,
	 * so it should be used only for special use cases like form discovery...
	 *
	 * They can be added at any level of the route/group tree and are inherited by child routes/groups;
	 * they will be executed in a depth-first manner (i.e. parent interceptors run before child interceptors).
	 *
	 * @param class-string<RouteInterceptorInterface> $interceptor the interceptor FQCN
	 *
	 * @return $this
	 */
	public function interceptor(string $interceptor): static
	{
		if (!\class_exists($interceptor)) {
			throw new RuntimeException(\sprintf(
				'Route interceptor class "%s" does not exist.',
				$interceptor
			));
		}

		if (!\is_subclass_of($interceptor, RouteInterceptorInterface::class)) {
			throw new RuntimeException(\sprintf(
				'Route interceptor "%s" should be subclass of: %s',
				$interceptor,
				RouteInterceptorInterface::class
			));
		}

		$this->interceptors[$interceptor::getName()] = $interceptor;

		self::changed();

		return $this;
	}

	/**
	 * Sets the route's form declaration.
	 *
	 * Accepts a {@see Form} instance, a callable (with arity auto-detected via reflection),
	 * a pre-built {@see RouteFormDeclaration} for full control, or a
	 * `class-string<ResumableFormProviderInterface>` to delegate the entire form lifecycle to
	 * the resumable-form pipeline.
	 *
	 * Detection rules (when $form is not a RouteFormDeclaration):
	 *  - A `class-string<ResumableFormProviderInterface>` -- creates a DYNAMIC declaration that
	 *    bypasses normal bundle validation; the router injects the completed FormData instead.
	 *  - A Form instance or a zero-arg callable (`fn(): Form`) -> {@see RouteFormDocPolicy::STATIC}:
	 *    resolvable and documentable without a live {@see RouteInfo}.
	 *  - A one-arg+ callable (`fn(RouteInfo $ri): ?Form`) -> {@see RouteFormDocPolicy::DYNAMIC}:
	 *    requires a live {@see RouteInfo} at request time.
	 *
	 * Pass {@see RouteFormDocPolicy::OPAQUE} or {@see RouteFormDocPolicy::DYNAMIC} as `$policy` to
	 * override the auto-detected value. The $policy parameter is ignored when $form is already a
	 * {@see RouteFormDeclaration} or a provider class string.
	 *
	 * @param callable|Form|RouteFormDeclaration|string $form     a form, a factory, a declaration, or a
	 *                                                            resumable form provider class name
	 * @param null|RouteFormDocPolicy                   $policy   explicit override (OPAQUE or DYNAMIC),
	 *                                                            or null to detect it
	 * @param bool                                      $override whether to replace an existing
	 *                                                            declaration instead of throwing
	 *
	 * @return $this
	 */
	public function form(
		callable|Form|RouteFormDeclaration|string $form,
		?RouteFormDocPolicy $policy = null,
		bool $override = false
	): static {
		if (null !== $this->form_declaration && !$override) {
			throw new RuntimeException('Form declaration is already set for this route.');
		}

		if ($form instanceof RouteFormDeclaration) {
			$this->form_declaration = $form;
		} elseif (\is_string($form)) {
			$this->form_declaration = RouteFormDeclaration::provider($form);
		} else {
			$this->form_declaration = RouteFormDeclaration::make($form, $policy);
		}

		self::changed();

		return $this;
	}

	/**
	 * Returns the form declaration defined for this route or its parent.
	 *
	 * @return null|RouteFormDeclaration
	 */
	public function getFormDeclaration(): ?RouteFormDeclaration
	{
		return $this->resolved()->formDeclaration();
	}

	/**
	 * Set priority.
	 *
	 * This method sets the priority of the route. Routes with higher priority values
	 * will be matched before those with lower values.
	 *
	 * @param int $priority
	 *
	 * @return $this
	 */
	public function priority(int $priority): static
	{
		if ($priority < self::PRIORITY_RUN_LAST) {
			throw new InvalidArgumentException(\sprintf(
				'Priority must be greater than or equal to %d, %s given',
				self::PRIORITY_RUN_LAST,
				$priority
			));
		}

		$this->priority = $priority;

		return $this;
	}

	/**
	 * Get route priority.
	 *
	 * @param bool $include_parent whether to include the priority of parent in the calculation
	 *
	 * @return int
	 */
	public function getPriority(bool $include_parent): int
	{
		$parent_priority = $include_parent && $this->parent ? $this->parent->getPriority(true) : 0;

		return $parent_priority + $this->priority;
	}

	/**
	 * Add parameter.
	 *
	 * @param string $name
	 * @param string $pattern
	 *
	 * @return $this
	 */
	public function param(string $name, string $pattern = Route::DEFAULT_PARAM_PATTERN): static
	{
		if (!self::checkPattern($pattern, $reason)) {
			throw new InvalidArgumentException(\sprintf(
				'Route parameter name "%s" pattern is not valid or is too complex. Keep it simple: %s',
				$name,
				$reason
			));
		}

		$this->route_params[$name] = $pattern;

		self::changed();

		return $this;
	}

	/**
	 * Add parameters.
	 *
	 * @param array<string, string> $params
	 *
	 * @return $this
	 */
	public function params(array $params): static
	{
		foreach ($params as $param => $pattern) {
			$this->param($param, $pattern);
		}

		return $this;
	}

	/**
	 * Gets parent.
	 *
	 * @return null|RouteSharedOptions
	 */
	public function getParent(): ?self
	{
		return $this->parent;
	}

	/**
	 * Gets name.
	 *
	 * @param bool $full
	 *
	 * @return string
	 */
	public function getName(bool $full = true): string
	{
		$name = $this->name;
		if ($full && $this->parent) {
			$parent_name = $this->parent->getName();
			if (!empty($parent_name)) {
				$name = $parent_name . '.' . $this->name;
			}
		}

		// Empty group names are allowed, so collapse repeated dots and trim leading/trailing
		// ones: "admin..list" becomes "admin.list".
		return \trim(\str_replace('..', '.', $name), '.');
	}

	/**
	 * Gets path.
	 *
	 * @param bool $full
	 *
	 * @return string
	 */
	public function getPath(bool $full = true): string
	{
		// The full path is read at every route comparison: computed once, until an option changes.
		if ($full && null !== $this->full_path && $this->full_path_revision === self::$revision) {
			return $this->full_path;
		}

		if ($full) {
			$this->full_path          = $this->computeFullPath();
			$this->full_path_revision = self::$revision;

			return $this->full_path;
		}

		return $this->path;
	}

	/**
	 * The options of this route or group merged with those it inherits, built on first use
	 * and rebuilt after any option change.
	 */
	public function resolved(): ResolvedRouteOptions
	{
		if (null === $this->resolved || $this->resolved_revision !== self::$revision) {
			$this->resolved          = $this->resolve();
			$this->resolved_revision = self::$revision;
		}

		return $this->resolved;
	}

	/**
	 * Gets form bundle.
	 *
	 * @param RouteInfo $ri
	 *
	 * @return null|Form
	 */
	public function getFormBundle(RouteInfo $ri): ?Form
	{
		return $this->resolved()->formBundle($ri);
	}

	/**
	 * Gets forms at request time.
	 *
	 * @param RouteInfo $ri
	 *
	 * @return list<Form>
	 */
	public function getForms(RouteInfo $ri): array
	{
		return $this->resolved()->forms($ri);
	}

	/**
	 * Gets the merged form bundle for API doc generation (no live RouteInfo needed).
	 *
	 * Collects doc forms from the entire parent-group chain, merging them into one {@see Form}.
	 * Declarations with policy {@see RouteFormDocPolicy::OPAQUE} or {@see RouteFormDocPolicy::DYNAMIC},
	 * and dynamic factories without a preview callable, contribute nothing.
	 *
	 * @return null|Form
	 */
	public function getStaticFormBundle(): ?Form
	{
		return $this->resolved()->staticFormBundle();
	}

	/**
	 * Returns the effective documentation policy for this route.
	 *
	 * Returns the policy of the innermost (child-most) declaration in the chain.
	 * Returns null when no form declaration exists anywhere in the chain.
	 *
	 * @return null|RouteFormDocPolicy
	 */
	public function getEffectiveDocPolicy(): ?RouteFormDocPolicy
	{
		return $this->resolved()->docPolicy();
	}

	/**
	 * Gets parameters.
	 *
	 * @return array<string,string>
	 */
	public function getParams(): array
	{
		return $this->resolved()->params;
	}

	/**
	 * Gets authentication methods.
	 *
	 * @return list<class-string<AuthenticationMethodInterface>>
	 */
	public function getAuthenticationMethods(): array
	{
		return $this->resolved()->authentication_methods;
	}

	/**
	 * Gets structured descriptors for the semantic guards registered via
	 * `withAuthentication`, `withAuthenticatedUser`, `withAuthorization`,
	 * `withRole`, `withAccessRights`, etc.
	 *
	 * Each descriptor is an associative array with at least a `type` key.
	 * Parent descriptors are prepended before the route's own descriptors.
	 *
	 * @return list<array{type: string, ...}>
	 */
	public function getGuardDescriptors(): array
	{
		return $this->resolved()->guard_descriptors;
	}

	/**
	 * Gets guards.
	 *
	 * @return list<RouteGuardInterface>
	 */
	public function getGuards(RouteInfo $ri): array
	{
		return $this->resolved()->guards($ri);
	}

	/**
	 * Gets middlewares.
	 *
	 * @return list<(callable(RouteInfo):?Response)|RouteMiddlewareInterface>
	 */
	public function getMiddlewares(): array
	{
		return $this->resolved()->middlewares;
	}

	/**
	 * Gets interceptors, the built-in ones included.
	 *
	 * @return array<string, class-string<RouteInterceptorInterface>>
	 */
	public function getInterceptors(): array
	{
		return $this->resolved()->interceptors;
	}

	/**
	 * Returns true when this route/group chain has resume support — i.e. when at
	 * least one level has called resumable() or declared a resumable form provider.
	 *
	 * @return bool
	 */
	public function hasResumeSupport(): bool
	{
		return $this->resolved()->hasResumeSupport();
	}

	/**
	 * Resolves the provider that drives resumption on this route: the declared
	 * provider, else {@see ResumableFormProvider} when the chain called
	 * resumable(), else null (no resume support).
	 *
	 * @return null|class-string<ResumableFormProviderInterface>
	 */
	public function resolveResumeProviderClass(): ?string
	{
		return $this->resolved()->resumeProviderClass();
	}

	/**
	 * Resolves the provider class of the innermost form declaration.
	 *
	 * @return null|class-string<ResumableFormProviderInterface>
	 */
	public function resolveProviderClass(): ?string
	{
		return $this->resolved()->providerClass();
	}

	/**
	 * Resolves the effective resume configuration for this route/group chain.
	 *
	 * Innermost (most specific) definition wins.
	 * Returns null when no level defines resumable().
	 *
	 * @return null|array{0: RequestScope, 1: int}
	 */
	public function resolveResumeConfig(): ?array
	{
		return $this->resolved()->resume_config;
	}

	/**
	 * The path, prefixed by every parent's.
	 */
	protected function computeFullPath(): string
	{
		if ($this->parent) {
			$parent_path = $this->parent->getPath();
			if (!empty($parent_path)) {
				return self::safePathConcat($parent_path, $this->path);
			}
		}

		return $this->path;
	}

	/**
	 * Checks if the parameter pattern is complex or is invalid.
	 *
	 * Should be valid regex pattern
	 * Should not starts with ^
	 * Should not ends with $
	 *
	 * @psalm-suppress InvalidArgument
	 *
	 * @param string      $pattern
	 * @param null|string &$reason
	 *
	 * @return bool
	 */
	protected static function checkPattern(string $pattern, ?string &$reason = null): bool
	{
		if (\str_starts_with($pattern, '^')) {
			$reason = 'should not start with "^"';

			return false;
		}

		if (\str_ends_with($pattern, '$')) {
			$reason = 'should not end with "$"';

			return false;
		}

		\set_error_handler(static function (): void {}, \E_WARNING);
		$pattern    = \preg_quote($pattern, Route::REG_DELIMITER);
		$is_invalid = false === \preg_match(Route::REG_DELIMITER . $pattern . Route::REG_DELIMITER, '');
		$reason     = \preg_last_error_msg();
		\restore_error_handler();

		return !$is_invalid;
	}

	/**
	 * Concat two path.
	 *
	 * @param string $prefix
	 * @param string $path
	 *
	 * @return string
	 */
	protected static function safePathConcat(string $prefix, string $path): string
	{
		if (empty($prefix)) {
			return $path;
		}

		if (empty($path)) {
			return $prefix;
		}

		return \rtrim($prefix, '/') . '/' . \ltrim($path, '/');
	}

	/**
	 * Invalidates every cached {@see ResolvedRouteOptions}: to be called by each option setter.
	 */
	protected static function changed(): void
	{
		++self::$revision;
	}

	/**
	 * @template T
	 *
	 * @param array<T> $values
	 *
	 * @return non-empty-array<T>
	 */
	protected static function atLeastOne(array $values, string $message): array
	{
		if (empty($values)) {
			throw new InvalidArgumentException(\sprintf('At least one "%s" is required.', $message));
		}

		return $values;
	}

	/**
	 * Merges this level's options into the parent's resolved ones.
	 */
	private function resolve(): ResolvedRouteOptions
	{
		$parent = $this->parent?->resolved();

		$form_declarations = $parent->form_declarations ?? [];

		if (null !== $this->form_declaration) {
			$form_declarations[] = $this->form_declaration;
		}

		return new ResolvedRouteOptions(
			authentication_methods: \array_values(\array_unique(\array_merge(
				$parent->authentication_methods ?? [],
				$this->authentication_methods
			))),
			guard_descriptors: \array_merge($parent->guard_descriptors ?? [], $this->guard_descriptors),
			guard_entries: \array_merge($parent->guard_entries ?? [], $this->guards),
			middlewares: \array_merge($parent->middlewares ?? [], $this->middlewares),
			interceptors: \array_merge($parent->interceptors ?? self::builtinInterceptors(), $this->interceptors),
			params: \array_merge($parent->params ?? [], $this->route_params),
			form_declarations: $form_declarations,
			csrf_scope: $this->csrf_scope ?? $parent?->csrf_scope,
			csrf_disabled: $this->csrf_disabled ?? $parent->csrf_disabled ?? false,
			resume_config: null !== $this->route_resume_scope
				? [$this->route_resume_scope, $this->route_resume_ttl]
				: $parent?->resume_config,
		);
	}

	/**
	 * The interceptors every route has, before any declared one.
	 *
	 * @return array<string, class-string<RouteInterceptorInterface>>
	 */
	private static function builtinInterceptors(): array
	{
		return [
			// Discovery runs last (priority 0), so any higher-priority interceptor (including
			// resume, priority 1) can fire first. It populates the resolved form on RouteInfo.
			FormDiscoveryRouteInterceptor::getName() => FormDiscoveryRouteInterceptor::class,
			// Resume only activates when the route has resume support and the request carries
			// the form-resume header.
			FormResumeRouteInterceptor::getName() => FormResumeRouteInterceptor::class,
		];
	}
}
