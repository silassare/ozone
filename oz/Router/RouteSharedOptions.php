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
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Enums\AuthenticationMethodType;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Exceptions\RateLimitReachedException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Guards\AuthorizationProviderRouteGuard;
use OZONE\Core\Router\Guards\UserAccessRightsRouteGuard;
use OZONE\Core\Router\Guards\UserRoleRouteGuard;
use OZONE\Core\Router\Interfaces\RouteGuardInterface;
use OZONE\Core\Router\Interfaces\RouteGuardProviderInterface;
use OZONE\Core\Router\Interfaces\RouteMiddlewareInterface;
use OZONE\Core\Router\Interfaces\RouteRateLimitInterface;

/**
 * Class SharedOptions.
 */
class RouteSharedOptions
{
	protected readonly string $path;

	/**
	 * @var array<string,string>
	 */
	protected array $route_params = [];

	/**
	 * @var array<callable|RouteGuardInterface>
	 */
	protected array $guards = [];

	/**
	 * @var array<callable(RouteInfo):(null|Response)|RouteMiddlewareInterface>
	 */
	protected array $middlewares = [];

	/**
	 * @var null|callable|Form
	 */
	protected $route_form;

	protected readonly ?RouteGroup $parent;
	private string $name = '';

	/**
	 * @var array<class-string<AuthenticationMethodInterface>>
	 */
	private array $authentication_methods = [];

	/**
	 * RouteSharedOptions constructor.
	 *
	 * @param string          $path
	 * @param null|RouteGroup $parent
	 */
	protected function __construct(
		string $path,
		?RouteGroup $parent = null
	) {
		$this->path   = $path;
		$this->parent = $parent;
	}

	/**
	 * RouteSharedOptions destructor.
	 */
	public function __destruct()
	{
		unset($this->guards, $this->middlewares, $this->route_form, $this->route_params);
	}

	/**
	 * Define the route name.
	 *
	 * @param string $name
	 *
	 * @return static
	 */
	public function name(string $name): static
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * Defines a rate limit.
	 *
	 * @param callable(RouteInfo):(null|RouteRateLimitInterface)|RouteRateLimitInterface $limit_provider
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
	 * @param AuthenticationMethodType|string ...$allowed_methods
	 *
	 * @return $this
	 */
	public function withAuthentication(AuthenticationMethodType|string ...$allowed_methods): static
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
	 * Adds a guard that check if at least one of the authorization providers authorized this request.
	 *
	 * @return $this
	 */
	public function withAuthorization(string ...$allowed_provider_names): static
	{
		$allowed_provider_names = self::atLeasOne($allowed_provider_names, 'authorization provider');

		return $this->guard(static function () use ($allowed_provider_names) {
			return new AuthorizationProviderRouteGuard($allowed_provider_names);
		});
	}

	/**
	 * Adds a guard that check that user has the given access rights.
	 *
	 * @return $this
	 */
	public function withAccessRights(string ...$rights): static
	{
		$rights = self::atLeasOne($rights, 'access right');

		return $this->guard(static function () use ($rights) {
			return new UserAccessRightsRouteGuard($rights);
		});
	}

	/**
	 * Adds a guard that check user has the given access rights or roles.
	 *
	 * @param string[] $rights
	 * @param string[] $roles
	 *
	 * @return $this
	 */
	public function withAccessRightsOrRoles(array $rights, array $roles): static
	{
		$rights = self::atLeasOne($rights, 'access right');

		return $this->guard(static function () use ($rights, $roles) {
			return new UserAccessRightsRouteGuard($rights, $roles);
		});
	}

	/**
	 * Adds a guard that check user has one of the given roles.
	 *
	 * @return $this
	 */
	public function withRole(string ...$roles): static
	{
		$roles = self::atLeasOne($roles, 'role');

		return $this->guard(static function () use ($roles) {
			return new UserRoleRouteGuard($roles);
		});
	}

	/**
	 * Adds a guard that check user has one of the given roles or is admin.
	 *
	 * @return $this
	 */
	public function withRoleOrAdmin(string ...$roles): static
	{
		$roles = self::atLeasOne($roles, 'role');

		return $this->guard(static function () use ($roles) {
			return new UserRoleRouteGuard($roles, false);
		});
	}

	/**
	 * Adds a guard that check if user has admin or super admin role.
	 *
	 * @return $this
	 */
	public function withAdminRole(): static
	{
		return $this->guard(static function () {
			return new UserRoleRouteGuard([AuthUsers::ADMIN, AuthUsers::SUPER_ADMIN]);
		});
	}

	/**
	 * Adds a guard that check if user super admin role.
	 *
	 * @return $this
	 */
	public function withSuperAdminRole(): static
	{
		return $this->guard(static function () {
			return new UserRoleRouteGuard([AuthUsers::SUPER_ADMIN]);
		});
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
	 * @param callable():RouteGuardInterface|RouteGuardInterface|string $guard
	 *
	 * @return static
	 */
	public function guard(callable|RouteGuardInterface|string $guard): static
	{
		if (\is_string($guard)) { // class FQN or provider name
			if (\class_exists($guard)) {// class FQN
				$provider_class = $guard;
				if (!\is_subclass_of($provider_class, RouteGuardProviderInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Route guard provider "%s" should be subclass of: %s',
						$provider_class,
						RouteGuardProviderInterface::class
					));
				}
			} else {// provider name
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
	 * @param callable(RouteInfo):void|RouteMiddlewareInterface|string $middleware
	 *
	 * @return static
	 */
	public function middleware(callable|RouteMiddlewareInterface|string $middleware): static
	{
		if (\is_string($middleware)) { // class FQN or provider name
			if (\class_exists($middleware)) {// class FQN
				$mdl_class = $middleware;
				if (!\is_subclass_of($mdl_class, RouteMiddlewareInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Route middleware "%s" should be subclass of: %s',
						$mdl_class,
						RouteMiddlewareInterface::class
					));
				}
			} else {// middleware name
				$mdl_class = Middlewares::get($middleware);
			}

			/** @var RouteMiddlewareInterface $mdl_class */
			$mdl = $mdl_class::get();

			$this->middlewares[] = $mdl;
		} else {
			$this->middlewares[] = $middleware;
		}

		return $this;
	}

	/**
	 * Sets form.
	 *
	 * @param callable|Form $form
	 *
	 * @return static
	 */
	public function form(callable|Form $form): static
	{
		$this->route_form = $form;

		return $this;
	}

	/**
	 * Add parameter.
	 *
	 * @param string $name
	 * @param string $pattern
	 *
	 * @return static
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
	 * @param array $params
	 *
	 * @return static
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
	 * @return null|RouteGroup
	 */
	public function getParent(): ?RouteGroup
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
	 * Gets route form bundle.
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

			return $bundle;
		}

		return null;
	}

	/**
	 * Gets route forms.
	 *
	 * @param RouteInfo $ri
	 *
	 * @return Form[]
	 */
	public function getForms(RouteInfo $ri): array
	{
		$forms = $this->parent?->getForms($ri) ?? [];

		if (isset($this->route_form)) {
			if ($this->route_form instanceof Form) {
				$forms[] = $this->route_form;
			} else {
				$form_builder = $this->route_form;
				$result       = $form_builder($ri);

				if (null !== $result) {
					if ($result instanceof Form) {
						$forms[] = $result;
					} else {
						throw (new RuntimeException(\sprintf(
							'Route form builder should return instance of "%s" or "null" not: %s',
							Form::class,
							\get_debug_type($result)
						))
						)->suspectCallable($form_builder);
					}
				}
			}
		}

		return $forms;
	}

	/**
	 * Gets route parameters.
	 *
	 * @return array<string,string>
	 */
	public function getParams(): array
	{
		$params = $this->parent?->getParams() ?? [];

		return \array_merge($params, $this->route_params);
	}

	/**
	 * Gets routes authentication methods.
	 *
	 * @return array<class-string<AuthenticationMethodInterface>>
	 */
	public function getAuthenticationMethods(): array
	{
		$list = $this->parent?->getAuthenticationMethods() ?? [];

		return \array_unique(\array_merge($list, $this->authentication_methods));
	}

	/**
	 * Gets route guards.
	 *
	 * @return RouteGuardInterface[]
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
	 * Gets route middlewares.
	 *
	 * @return array<callable(RouteInfo):(null|Response)|RouteMiddlewareInterface>
	 */
	public function getMiddlewares(): array
	{
		$middlewares = $this->parent?->getMiddlewares() ?? [];

		return \array_merge($middlewares, $this->middlewares);
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

	private static function atLeasOne(array $values, string $message): array
	{
		if (empty($values)) {
			throw new InvalidArgumentException(\sprintf('At least one "%s" is required.', $message));
		}

		return $values;
	}

	/**
	 * Concat two path.
	 *
	 * @param string $prefix
	 * @param string $path
	 *
	 * @return string
	 */
	private static function safePathConcat(string $prefix, string $path): string
	{
		if (empty($prefix)) {
			return $path;
		}

		if (empty($path)) {
			return $prefix;
		}

		return \rtrim($prefix, '/') . '/' . \ltrim($path, '/');
	}
}
