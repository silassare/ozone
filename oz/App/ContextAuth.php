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

namespace OZONE\Core\App;

use LogicException;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodStatefulInterface;
use OZONE\Core\Auth\StatefulAuthenticationMethodStore;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Router\RouteInfo;
use Throwable;

/**
 * Class ContextAuth.
 *
 * The authentication state of a request ({@see Context::authState()}): the method that
 * authenticated it, its stateful store when it has one, and the request's users.
 */
final class ContextAuth
{
	private ?AuthenticationMethodInterface $method = null;
	private ?RouteInfo $route_info                 = null;
	private bool $authenticated                    = false;

	/**
	 * ContextAuth constructor.
	 */
	public function __construct(private readonly AuthUsers $users) {}

	/**
	 * Authenticates the request with the route's authentication methods.
	 *
	 * Without methods, nothing happens. Otherwise the first method the request satisfies is
	 * used; a sub-request reuses its parent's method when the route allows it.
	 *
	 * @param RouteInfo $ri     the matched route
	 * @param null|self $parent the parent request's state, for sub-requests
	 *
	 * @throws ForbiddenException when the request satisfies none of the methods
	 */
	public function authenticate(RouteInfo $ri, ?self $parent = null): void
	{
		if ($this->authenticated) {
			throw new LogicException('Authentication already done.');
		}

		$this->authenticated = true;
		$this->route_info    = $ri;
		$methods             = $ri->route()->getOptions()->getAuthenticationMethods();

		if (empty($methods)) {
			return;
		}

		$parent_method = $parent?->current();

		if (null !== $parent_method && \in_array($parent_method::class, $methods, true)) {
			$this->method = $parent_method;

			return;
		}

		foreach ($methods as $class) {
			$instance = $class::get($ri, 'Authentication required.');

			if ($instance->satisfied()) {
				$instance->authenticate();

				$this->method = $instance;

				return;
			}
		}

		throw new ForbiddenException('Authentication required.');
	}

	/**
	 * The method that authenticated the request, or null (no route yet, or none defined).
	 */
	public function current(): ?AuthenticationMethodInterface
	{
		return $this->method;
	}

	/**
	 * The method that authenticated the request.
	 *
	 * @throws RuntimeException when the route defines no method, or no route was matched yet
	 */
	public function method(): AuthenticationMethodInterface
	{
		if (null !== $this->method) {
			return $this->method;
		}

		if (null === $this->route_info) {
			throw new RuntimeException('No auth method yet: the router has not matched a route.');
		}

		throw (new RuntimeException('The current route defines no auth method.'))
			->suspectCallable($this->route_info->getEffectiveHandler());
	}

	/**
	 * Whether a user is authenticated.
	 */
	public function hasAuthenticatedUser(): bool
	{
		try {
			return (bool) $this->method?->user();
		} catch (Throwable) {
			return false;
		}
	}

	/**
	 * Whether the request is authenticated by a stateful method.
	 */
	public function isStateful(): bool
	{
		return $this->method instanceof AuthenticationMethodStatefulInterface;
	}

	/**
	 * The stateful method that authenticated the request.
	 *
	 * @throws RuntimeException when the method is not stateful
	 */
	public function requireStateful(): AuthenticationMethodStatefulInterface
	{
		$method = $this->method();

		if ($method instanceof AuthenticationMethodStatefulInterface) {
			return $method;
		}

		throw new RuntimeException('The current auth method is not stateful.', [
			'auth' => $method::class,
		]);
	}

	/**
	 * The store of the stateful method, or null.
	 */
	public function store(): ?StatefulAuthenticationMethodStore
	{
		return $this->isStateful() ? $this->requireStore() : null;
	}

	/**
	 * The store of the stateful method.
	 *
	 * @throws RuntimeException when the method is not stateful
	 */
	public function requireStore(): StatefulAuthenticationMethodStore
	{
		return $this->requireStateful()->store();
	}

	/**
	 * The request's users.
	 */
	public function users(): AuthUsers
	{
		return $this->users;
	}
}
