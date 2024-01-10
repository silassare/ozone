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
use OZONE\Core\Auth\AuthMethodType;
use OZONE\Core\Auth\Interfaces\AuthMethodInterface;
use OZONE\Core\Exceptions\RateLimitReachedException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Guards\TwoFactorRouteGuard;
use OZONE\Core\Router\Guards\UserRoleRouteGuard;
use OZONE\Core\Router\Interfaces\RouteGuardInterface;
use OZONE\Core\Router\Interfaces\RouteGuardProviderInterface;
use OZONE\Core\Router\Interfaces\RouteMiddlewareInterface;
use OZONE\Core\Router\Interfaces\RouteRateLimitInterface;
use OZONE\Core\Users\Users;

/**
 * Class SharedOptions.
 */
class RouteSharedOptions
{
	protected readonly string $path;
	protected array $route_params = [];

	/**
	 * @var array<callable|\OZONE\Core\Router\Interfaces\RouteGuardInterface>
	 */
	protected array $guards = [];

	/**
	 * @var array<callable(RouteInfo):(null|Response)|\OZONE\Core\Router\Interfaces\RouteMiddlewareInterface>
	 */
	protected array $middlewares = [];

	/**
	 * @var null|callable|\OZONE\Core\Forms\Form
	 */
	protected $route_form;

	protected readonly ?RouteGroup $parent;
	private string $name = '';

	/**
	 * @var array<class-string<\OZONE\Core\Auth\Interfaces\AuthMethodInterface>>
	 */
	private array $auths_methods = [];

	/**
	 * RouteSharedOptions constructor.
	 *
	 * @param string                             $path
	 * @param null|\OZONE\Core\Router\RouteGroup $parent
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
	 * Defines allowed auth methods.
	 *
	 * @param AuthMethodType|string ...$auths
	 *
	 * @return $this
	 */
	public function auths(AuthMethodType|string ...$auths): static
	{
		foreach ($auths as $entry) {
			if (!\is_string($entry)) {
				$entry = $entry->value;
			}

			if (\class_exists($entry)) {
				if (!\is_subclass_of($entry, AuthMethodInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Auth method "%s" should be subclass of: %s',
						$entry,
						AuthMethodInterface::class
					));
				}
				$auth = $entry;
			} else {
				$auth = Auth::method($entry);
			}

			$this->auths_methods[] = $auth;
		}

		return $this;
	}

	/**
	 * Adds a 2FA guard.
	 *
	 * @return $this
	 */
	public function with2FA(string ...$allowed_providers_name): static
	{
		return $this->guard(static function () use ($allowed_providers_name) {
			return new TwoFactorRouteGuard(...$allowed_providers_name);
		});
	}

	/**
	 * Adds a guard that check user has one of the given roles.
	 *
	 * @return $this
	 */
	public function withRole(string ...$roles): static
	{
		return $this->guard(static function () use ($roles) {
			return new UserRoleRouteGuard(...$roles);
		});
	}

	/**
	 * Adds a guard that check user has one of the given roles or is admin.
	 *
	 * @return $this
	 */
	public function withRoleOrAdmin(string ...$roles): static
	{
		return $this->guard(static function () use ($roles) {
			return (new UserRoleRouteGuard(...$roles))->strict(false);
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
			return new UserRoleRouteGuard(Users::ADMIN, Users::SUPER_ADMIN);
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
			return new UserRoleRouteGuard(Users::SUPER_ADMIN);
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
	 * @param callable|RouteGuardInterface|string $guard
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
	 * @param callable|\OZONE\Core\Forms\Form $form
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
	 * @return null|\OZONE\Core\Router\RouteGroup
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
	 * @param \OZONE\Core\Router\RouteInfo $ri
	 *
	 * @return null|\OZONE\Core\Forms\Form
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
	 * @param \OZONE\Core\Router\RouteInfo $ri
	 *
	 * @return \OZONE\Core\Forms\Form[]
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
	 * @return array
	 */
	public function getParams(): array
	{
		$params = $this->parent?->getParams() ?? [];

		return \array_merge($params, $this->route_params);
	}

	/**
	 * Gets routes auth methods.
	 *
	 * @return array<class-string<\OZONE\Core\Auth\Interfaces\AuthMethodInterface>>
	 */
	public function getAuthMethods(): array
	{
		$list = $this->parent?->getAuthMethods() ?? [];

		return \array_unique(\array_merge($list, $this->auths_methods));
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
	 * @return array<callable(RouteInfo):(null|Response)|\OZONE\Core\Router\Interfaces\RouteMiddlewareInterface>
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
