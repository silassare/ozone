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
use OZONE\Core\App\Context;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Http\Uri;
use OZONE\Core\Router\Interfaces\RouteMiddlewareInterface;
use PHPUtils\Store\Store;

/**
 * Class RouteInfo.
 */
final class RouteInfo
{
	private Store $guards_data;
	private FormData $clean_form_data;

	/**
	 * RouteInfo constructor.
	 *
	 * @param Context                   $context       The context
	 * @param Route                     $route         The current route
	 * @param array                     $params        The route params
	 * @param null|callable($this):void $authenticator The authenticator
	 *
	 * @throws InvalidFormException
	 */
	public function __construct(
		private readonly Context $context,
		private readonly Route $route,
		private readonly array $params,
		?callable $authenticator = null
	) {
		$this->guards_data     = new Store([]);
		$this->clean_form_data = new FormData();

		$authenticator && $authenticator($this);
		$this->callGuards();
		$this->runMiddlewares();
		$this->checkForm();
	}

	/**
	 * Gets current request context.
	 *
	 * @return \OZONE\Core\App\Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * Gets current route.
	 *
	 * @return \OZONE\Core\Router\Route
	 */
	public function route(): Route
	{
		return $this->route;
	}

	/**
	 * Gets current route parameters.
	 *
	 * @return array
	 */
	public function params(): array
	{
		return $this->params;
	}

	/**
	 * Gets current route parameter value with a given name.
	 *
	 * @param string     $name
	 * @param null|mixed $def
	 *
	 * @return mixed
	 */
	public function param(string $name, mixed $def = null): mixed
	{
		return $this->params[$name] ?? $def;
	}

	/**
	 * Shortcut for {@see \OZONE\Core\Http\Request::getUri()}.
	 *
	 * @return \OZONE\Core\Http\Uri
	 */
	public function uri(): Uri
	{
		return $this->context->getRequest()
			->getUri();
	}

	/**
	 * Gets validated form data.
	 *
	 * @return \OZONE\Core\Forms\FormData
	 */
	public function getCleanFormData(): FormData
	{
		return $this->clean_form_data;
	}

	/**
	 * Gets validated form field value.
	 *
	 * @param string     $name
	 * @param null|mixed $def
	 *
	 * @return mixed
	 */
	public function getCleanFormField(string $name, mixed $def = null): mixed
	{
		return $this->clean_form_data->get($name, $def);
	}

	/**
	 * Gets guard form data.
	 *
	 * @param string $guard
	 *
	 * @return \OZONE\Core\Forms\FormData
	 */
	public function getGuardFormData(string $guard): FormData
	{
		$guard_class = Guards::get($guard);

		$guard_data = $this->guards_data->get($guard_class);

		if ($guard_data instanceof FormData) {
			return $guard_data;
		}

		throw new InvalidArgumentException(\sprintf('Guard "%s" has no form data.', $guard));
	}

	/**
	 * Shortcut for {@see \OZONE\Core\Http\Request::getUnsafeFormData()}.
	 *
	 * @param bool $include_files
	 *
	 * @return FormData
	 */
	public function getUnsafeFormData(bool $include_files = true): FormData
	{
		return $this->context->getRequest()
			->getUnsafeFormData($include_files);
	}

	/**
	 * Shortcut for {@see \OZONE\Core\Http\Request::getUnsafeFormField()}.
	 *
	 * @param string     $name
	 * @param null|mixed $def
	 *
	 * @return mixed
	 */
	public function getUnsafeFormField(string $name, mixed $def = null): mixed
	{
		return $this->context->getRequest()
			->getUnsafeFormField($name, $def);
	}

	/**
	 * Run all guards.
	 */
	private function callGuards(): void
	{
		$route_guards = $this->route->getOptions()->getGuards($this);

		foreach ($route_guards as $guard) {
			$guard->checkAccess($this);
			$this->guards_data->set($guard::class, $guard->getFormData());
		}
	}

	/**
	 * Run all middlewares.
	 */
	private function runMiddlewares(): void
	{
		$middlewares = $this->route->getOptions()->getMiddlewares();

		foreach ($middlewares as $mdl) {
			if ($mdl instanceof RouteMiddlewareInterface) {
				$response = $mdl->run($this);
			} else {
				$response = $mdl($this);
			}

			if ($response) {
				$this->context->setResponse($response);
			}
		}
	}

	/**
	 * Validates the form data if any.
	 *
	 * @throws \OZONE\Core\Exceptions\InvalidFormException
	 */
	private function checkForm(): void
	{
		$bundle = $this->route->getOptions()->getFormBundle($this);

		if ($bundle) {
			$unsafe_fd = $this->context->getRequest()
				->getUnsafeFormData();

			$clean_fd = $bundle->validate($unsafe_fd);

			$this->clean_form_data->merge($clean_fd);
		}
	}
}
