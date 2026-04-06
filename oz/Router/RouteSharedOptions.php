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
use OZONE\Core\Forms\Interfaces\ResumableFormProviderInterface;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Http\Response;
use OZONE\Core\Roles\Enums\Role;
use OZONE\Core\Roles\Interfaces\RoleInterface;
use OZONE\Core\Router\Enums\RouteFormDocPolicy;
use OZONE\Core\Router\Guards\AuthenticatedUserRouteGuard;
use OZONE\Core\Router\Guards\AuthorizationProviderRouteGuard;
use OZONE\Core\Router\Guards\CSRFRouteGuard;
use OZONE\Core\Router\Guards\UserAccessRightsRouteGuard;
use OZONE\Core\Router\Guards\UserRoleRouteGuard;
use OZONE\Core\Router\Interfaces\RouteGuardInterface;
use OZONE\Core\Router\Interfaces\RouteGuardProviderInterface;
use OZONE\Core\Router\Interfaces\RouteInterceptorInterface;
use OZONE\Core\Router\Interfaces\RouteMiddlewareInterface;
use OZONE\Core\Router\Interfaces\RouteRateLimitInterface;

/**
 * Class RouteSharedOptions.
 */
class RouteSharedOptions
{
	public const PRIORITY_RUN_LAST    = -1;
	public const PRIORITY_RUN_DEFAULT = 0;

	/**
	 * @var null|RouteSharedOptions
	 */
	protected readonly ?RouteSharedOptions $parent;
	protected readonly string $path;
	protected int $priority = self::PRIORITY_RUN_DEFAULT;

	protected ?RequestScope $csrf_scope = null;

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
	private string $name                              = '';

	/**
	 * @var list<class-string<AuthenticationMethodInterface>>
	 */
	private array $authentication_methods = [];

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
	 * RouteSharedOptions destructor.
	 */
	public function __destruct()
	{
		unset($this->guards, $this->middlewares, $this->form_declaration, $this->route_params, $this->interceptors);
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
		$allowed_methods = self::atLeasOne($allowed_methods, 'authentication method');

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

		return $this;
	}

	/**
	 * Adds a guard that checks if at least one of the authorization providers authorized this request.
	 *
	 * @return $this
	 */
	public function withAuthorization(string ...$allowed_provider_names): static
	{
		$allowed_provider_names = self::atLeasOne($allowed_provider_names, 'authorization provider');

		$this->guard_descriptors[] = [
			'type'               => 'authorization',
			'allowed_providers'  => $allowed_provider_names,
		];

		return $this->guard(static fn () => new AuthorizationProviderRouteGuard($allowed_provider_names));
	}

	/**
	 * Adds a guard that checks if we have an authenticated user.
	 *
	 * > Allowed user type may be empty.
	 *
	 * @return $this
	 */
	public function withAuthenticatedUser(string ...$allowed_auth_user_types): static
	{
		$this->guard_descriptors[] = [
			'type'          => 'authenticated_user',
			'allowed_types' => $allowed_auth_user_types,
		];

		return $this->guard(static fn () => new AuthenticatedUserRouteGuard($allowed_auth_user_types));
	}

	/**
	 * Adds a guard that checks that the user has the given access rights.
	 *
	 * @return $this
	 */
	public function withAccessRights(string ...$rights): static
	{
		$rights = self::atLeasOne($rights, 'access right');

		$this->guard_descriptors[] = [
			'type'   => 'access_rights',
			'rights' => $rights,
		];

		return $this->guard(static fn () => new UserAccessRightsRouteGuard($rights));
	}

	/**
	 * Adds a guard that checks that the user has the given access rights or roles.
	 *
	 * @param string[] $rights
	 * @param string[] $roles
	 *
	 * @return $this
	 */
	public function withAccessRightsOrRoles(array $rights, array $roles): static
	{
		$rights = self::atLeasOne($rights, 'access right');

		$this->guard_descriptors[] = [
			'type'   => 'access_rights',
			'rights' => $rights,
			'roles'  => $roles,
		];

		return $this->guard(static fn () => new UserAccessRightsRouteGuard($rights, $roles));
	}

	/**
	 * Adds a guard that checks that the user has one of the given roles.
	 *
	 * @return $this
	 */
	public function withRole(RoleInterface ...$roles): static
	{
		$roles = self::atLeasOne($roles, 'role');

		$this->guard_descriptors[] = [
			'type'   => 'role',
			'roles'  => \array_map(static fn ($r) => $r->value, $roles),
			'strict' => true,
		];

		return $this->guard(static fn () => new UserRoleRouteGuard($roles));
	}

	/**
	 * Adds a guard that checks that the user has one of the given roles or is admin.
	 *
	 * @return $this
	 */
	public function withRoleOrAdmin(RoleInterface ...$roles): static
	{
		$roles = self::atLeasOne($roles, 'role');

		$this->guard_descriptors[] = [
			'type'   => 'role',
			'roles'  => \array_map(static fn ($r) => $r->value, $roles),
			'strict' => false,
		];

		return $this->guard(static fn () => new UserRoleRouteGuard($roles, false));
	}

	/**
	 * Adds a guard that checks if the user has admin or super admin role.
	 *
	 * @return $this
	 */
	public function withAdminRole(): static
	{
		$this->guard_descriptors[] = [
			'type'   => 'role',
			'roles'  => [Role::ADMIN->value, Role::SUPER_ADMIN->value],
			'strict' => true,
		];

		return $this->guard(static fn () => new UserRoleRouteGuard([Role::ADMIN, Role::SUPER_ADMIN]));
	}

	/**
	 * Adds a guard that checks if the user has super admin role.
	 *
	 * @return $this
	 */
	public function withSuperAdminRole(): static
	{
		$this->guard_descriptors[] = [
			'type'   => 'role',
			'roles'  => [Role::SUPER_ADMIN->value],
			'strict' => true,
		];

		return $this->guard(static fn () => new UserRoleRouteGuard([Role::SUPER_ADMIN]));
	}

	/**
	 * Adds a guard that check CSRF token.
	 *
	 * @return $this
	 */
	public function withCSRF(RequestScope $scope): static
	{
		$has_csrf_guard_in_tree = $this->getCSRFScope();

		$this->csrf_scope = $scope;

		$this->guard_descriptors[] = [
			'type'  => 'csrf',
			'scope' => $scope->value,
		];

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
		return $this->csrf_scope ?? $this->parent?->getCSRFScope() ?? null;
	}

	/**
	 * Enables resume caching for the form bundle assembled by this route or group.
	 *
	 * When set, overrides any resumable() configuration declared on the individual
	 * forms merged into the bundle. Inherited from parent; innermost definition wins.
	 *
	 * @param RequestScope $scope The scoping strategy for the resume cache
	 * @param int          $ttl   Cache TTL in seconds (default: 3600)
	 *
	 * @return $this
	 */
	public function resumable(RequestScope $scope = RequestScope::STATE, int $ttl = 3600): static
	{
		$this->route_resume_scope = $scope;
		$this->route_resume_ttl   = $ttl;

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
		if (\is_string($guard)) { // class FQN or provider name
			if (\class_exists($guard)) { // class FQN
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
		if (\is_string($middleware)) { // class FQN or provider name
			if (\class_exists($middleware)) { // class FQN
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

		return $this;
	}

	/**
	 * Add an interceptor.
	 *
	 * Route interceptors are executed in the order they are defined after guards and middlewares.
	 * The first interceptor that accepts the request by returning true when {@see RouteInterceptorInterface::shouldIntercept()}
	 * is called will short-circuit the rest of the chain and its {@see RouteInterceptorInterface::handle()}
	 * will be called in place of the route handler.
	 *
	 * NOTE: route form will not be validated when a route interceptor intercepts the request,
	 * so it should be used only for special use cases like form discovery...
	 *
	 * They can be added at any level of the route/group tree and are inherited by child routes/groups;
	 * they will be executed in a depth-first manner (i.e. parent interceptors run before child interceptors).
	 *
	 * @param class-string<RouteInterceptorInterface> $interceptor the interceptor FQN class
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
	 *  - A `class-string<ResumableFormProviderInterface>` — creates an EXTERNAL declaration that
	 *    bypasses normal bundle validation; the router injects the completed FormData instead.
	 *  - A Form instance or a zero-arg callable (`fn(): Form`) is treated as static:
	 *    resolvable and documentable without a live {@see RouteInfo}.
	 *  - A one-arg+ callable (`fn(RouteInfo $ri): ?Form`) is treated as dynamic:
	 *    requires a live {@see RouteInfo} at request time and is opaque in API docs
	 *    unless $policy is overridden or a preview is provided via {@see RouteFormDeclaration::dynamic()}.
	 *
	 * The $policy parameter is ignored when $form is already a {@see RouteFormDeclaration} or a
	 * provider class string.
	 *
	 * @param callable|class-string<ResumableFormProviderInterface>|Form|RouteFormDeclaration $form     the form, a factory, a full declaration, or a provider class string
	 * @param RouteFormDocPolicy                                                              $policy   documentation policy (AUTO by default)
	 * @param bool                                                                            $override whether to override an existing form declaration or throw an exception
	 *
	 * @return $this
	 */
	public function form(callable|Form|RouteFormDeclaration|string $form, RouteFormDocPolicy $policy = RouteFormDocPolicy::AUTO, bool $override = false): static
	{
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

		return $this;
	}

	/**
	 * Returns the form declaration set directly on this route or group, without traversing the parent chain.
	 *
	 * @return null|RouteFormDeclaration
	 */
	public function getFormDeclaration(): ?RouteFormDeclaration
	{
		return $this->form_declaration;
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

		// we remove any leading/trailing dots and replace multiple consecutive dots with a single one to avoid issues with empty group names
		// because empty group names are allowed but can lead to messy route names like "admin..list" which should be normalized to "admin.list"
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
		if ($full && $this->parent) {
			$parent_path = $this->parent->getPath();
			if (!empty($parent_path)) {
				return self::safePathConcat($parent_path, $this->path);
			}
		}

		return $this->path;
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
		$forms = $this->getForms($ri);

		if (!empty($forms)) {
			$bundle = new Form();

			foreach ($forms as $form) {
				$bundle->merge($form);
			}

			$req    = $ri->getContext()->getRequest();
			$target = $req->getUri();
			$method = $req->getMethod();

			$resume_config = $this->resolveResumeConfig();

			if (null !== $resume_config) {
				$bundle->resumable($resume_config[0], $resume_config[1]);
			}

			return $bundle->submitTo($target)->method($method);
		}

		return null;
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
		$forms = $this->parent?->getForms($ri) ?? [];

		if (null !== $this->form_declaration) {
			$form = $this->form_declaration->resolve($ri);

			if (null !== $form) {
				$forms[] = $form;
			}
		}

		return $forms;
	}

	/**
	 * Gets the merged form bundle for API doc generation (no live RouteInfo needed).
	 *
	 * Collects doc forms from the entire parent-group chain, merging them into one {@see Form}.
	 * Declarations with policy {@see RouteFormDocPolicy::OPAQUE} or {@see RouteFormDocPolicy::EXTERNAL},
	 * and dynamic factories without a preview callable, contribute nothing.
	 *
	 * @return null|Form
	 */
	public function getStaticFormBundle(): ?Form
	{
		$forms = $this->getDocForms();

		if (empty($forms)) {
			return null;
		}

		$bundle = new Form();

		foreach ($forms as $form) {
			$bundle->merge($form);
		}

		$resume_config = $this->resolveResumeConfig();

		if (null !== $resume_config) {
			$bundle->resumable($resume_config[0], $resume_config[1]);
		}

		return $bundle;
	}

	/**
	 * Returns the effective documentation policy for this route.
	 *
	 * Returns the policy of the innermost (child-most) declaration in the chain.
	 * Falls back to {@see RouteFormDocPolicy::AUTO} when no declaration exists.
	 *
	 * @return RouteFormDocPolicy
	 */
	public function getEffectiveDocPolicy(): RouteFormDocPolicy
	{
		if (null !== $this->form_declaration) {
			return $this->form_declaration->getPolicy();
		}

		return $this->parent?->getEffectiveDocPolicy() ?? RouteFormDocPolicy::AUTO;
	}

	/**
	 * Gets parameters.
	 *
	 * @return array<string,string>
	 */
	public function getParams(): array
	{
		$params = $this->parent?->getParams() ?? [];

		return \array_merge($params, $this->route_params);
	}

	/**
	 * Gets authentication methods.
	 *
	 * @return list<class-string<AuthenticationMethodInterface>>
	 */
	public function getAuthenticationMethods(): array
	{
		$list = $this->parent?->getAuthenticationMethods() ?? [];

		return \array_unique(\array_merge($list, $this->authentication_methods));
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
		$parent = $this->parent?->getGuardDescriptors() ?? [];

		return \array_merge($parent, $this->guard_descriptors);
	}

	/**
	 * Gets guards.
	 *
	 * @return list<RouteGuardInterface>
	 */
	public function getGuards(RouteInfo $ri): array
	{
		$results = $this->parent?->getGuards($ri) ?? [];
		foreach ($this->guards as $guard) {
			if (\is_callable($guard)) {
				$provider = $guard;
				$guard    = $provider($ri);

				if (null === $guard) {
					continue;
				}

				if (!$guard instanceof RouteGuardInterface) {
					throw (new RuntimeException(\sprintf(
						'Route guard provider should return instance of "%s" or "null" not: %s',
						RouteGuardInterface::class,
						\get_debug_type($guard)
					))
					)->suspectCallable($provider);
				}
			}

			if ($guard instanceof RouteGuardInterface) {
				$results[] = $guard;
			}
		}

		return $results;
	}

	/**
	 * Gets middlewares.
	 *
	 * @return list<(callable(RouteInfo):?Response)|RouteMiddlewareInterface>
	 */
	public function getMiddlewares(): array
	{
		$middlewares = $this->parent?->getMiddlewares() ?? [];

		return \array_merge($middlewares, $this->middlewares);
	}

	/**
	 * Gets interceptors.
	 *
	 * @return array<string, class-string<RouteInterceptorInterface>>
	 */
	public function getInterceptors(): array
	{
		$interceptors = $this->parent?->getInterceptors() ?? [];
		$rd           = RouteFormDiscoveryInterceptor::getName();

		return \array_merge(
			[
				// we make sure that the form discovery interceptor is always present and runs after all other interceptors, even if not explicitly added, because it's responsible for populating the RouteInfo with the resolved form which is needed by any interceptor that needs to access the form or its data
				$rd => RouteFormDiscoveryInterceptor::class,
			],
			$interceptors,
			$this->interceptors
		);
	}

	/**
	 * Resolves the effective resume configuration for this route/group chain.
	 *
	 * Innermost (most specific) definition wins.
	 * Returns null when no level defines resumable().
	 *
	 * @return null|array{0: RequestScope, 1: int}
	 */
	protected function resolveResumeConfig(): ?array
	{
		if (null !== $this->route_resume_scope) {
			return [$this->route_resume_scope, $this->route_resume_ttl];
		}

		return $this->parent?->resolveResumeConfig();
	}

	/**
	 * Collects documentable forms from this and all parent.
	 *
	 * Called by {@see getStaticFormBundle()} to build the doc-gen bundle.
	 *
	 * @return list<Form>
	 */
	protected function getDocForms(): array
	{
		$forms = $this->parent?->getDocForms() ?? [];

		if (null !== $this->form_declaration) {
			$doc_form = $this->form_declaration->getDocForm();

			if (null !== $doc_form) {
				$forms[] = $doc_form;
			}
		}

		return $forms;
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

	private static function atLeasOne(array $values, string $message): array
	{
		if (empty($values)) {
			throw new InvalidArgumentException(\sprintf('At least one "%s" is required.', $message));
		}

		return $values;
	}
}
