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
use OZONE\Core\Forms\FormData;
use OZONE\Core\Http\Uri;
use PHPUtils\Store\Store;

/**
 * Class RouteInfo.
 */
final class RouteInfo
{
	private Store    $guards_data;
	private FormData $clean_form_data;

	/**
	 * RouteInfo constructor.
	 *
	 * @param \OZONE\Core\App\Context  $context The context
	 * @param \OZONE\Core\Router\Route $route   The current route
	 * @param array                    $params  The route params
	 *
	 * @throws \OZONE\Core\Exceptions\InvalidFormException
	 */
	public function __construct(
		private readonly Context $context,
		private readonly Route $route,
		private readonly array $params
	) {
		$this->guards_data     = new Store([]);
		$this->clean_form_data = new FormData();

		$this->callGuards();
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
	public function getRoute(): Route
	{
		return $this->route;
	}

	/**
	 * Gets current route parameters.
	 *
	 * @return array
	 */
	public function getParams(): array
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
	public function getParam(string $name, mixed $def = null): mixed
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
		$options      = $this->route->getOptions();
		$route_guards = $options->getGuards($this);

		foreach ($route_guards as $guard) {
			$guard->checkAccess($this);
			$this->guards_data->set($guard::class, $guard->getFormData());
		}
	}

	/**
	 * Validates the form data if any.
	 *
	 * @throws \OZONE\Core\Exceptions\InvalidFormException
	 */
	private function checkForm(): void
	{
		$options = $this->route->getOptions();
		$bundle  = $options->getFormBundle($this);

		if ($bundle) {
			$unsafe_fd = $this->context->getRequest()
				->getUnsafeFormData();

			$clean_fd = $bundle->validate($unsafe_fd);

			$this->clean_form_data->merge($clean_fd);
		}
	}
}
