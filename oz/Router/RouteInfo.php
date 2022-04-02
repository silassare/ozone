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

namespace OZONE\OZ\Router;

use OZONE\OZ\Core\Context;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Forms\FormData;
use OZONE\OZ\Http\Uri;

/**
 * Class RouteInfo.
 */
final class RouteInfo
{
	/**
	 * RouteInfo constructor.
	 *
	 * @param \OZONE\OZ\Core\Context        $context         The context
	 * @param \OZONE\OZ\Router\Route        $route           The current route
	 * @param array                         $params          The route params
	 * @param null|\OZONE\OZ\Forms\FormData $auth_form_data  The route guard authorization form data
	 * @param null|\OZONE\OZ\Forms\FormData $clean_form_data The clean form data
	 */
	public function __construct(
		private Context $context,
		private Route $route,
		private array $params,
		private ?FormData $auth_form_data = null,
		private ?FormData $clean_form_data = null
	) {
	}

	/**
	 * Gets current request context.
	 *
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * Gets current route.
	 *
	 * @return \OZONE\OZ\Router\Route
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
	 * Shortcut for {@see \OZONE\OZ\Http\Request::getUri()}.
	 *
	 * @return \OZONE\OZ\Http\Uri
	 */
	public function getUri(): Uri
	{
		return $this->context->getRequest()
			->getUri();
	}

	/**
	 * Gets validated form data.
	 *
	 * @return \OZONE\OZ\Forms\FormData
	 */
	public function getCleanFormData(): FormData
	{
		if (null === $this->clean_form_data) {
			throw new RuntimeException('Undefined clean form data as no route form was provided.');
		}

		return $this->clean_form_data;
	}

	/**
	 * Gets current route guard authorization form data.
	 *
	 * @return null|\OZONE\OZ\Forms\FormData
	 */
	public function getAuthFormData(): ?FormData
	{
		return $this->auth_form_data;
	}

	/**
	 * Shortcut for {@see \OZONE\OZ\Http\Request::getFormData()}.
	 *
	 * @param bool $include_files
	 *
	 * @return FormData
	 */
	public function getFormData(bool $include_files = true): FormData
	{
		return $this->context->getRequest()
			->getFormData($include_files);
	}

	/**
	 * Shortcut for {@see \OZONE\OZ\Http\Request::getFormField()}.
	 *
	 * @param string     $name
	 * @param null|mixed $def
	 *
	 * @return mixed
	 */
	public function getFormField(string $name, mixed $def = null): mixed
	{
		return $this->context->getRequest()
			->getFormField($name, $def);
	}
}
