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
use OZONE\OZ\Forms\FormData;
use OZONE\OZ\Http\Uri;

/**
 * Class RouteInfo.
 */
final class RouteInfo
{
	private FormData $auth_form_data;
	private FormData $clean_form_data;

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
		private readonly Context $context,
		private readonly Route $route,
		private readonly array $params,
		?FormData $auth_form_data = null,
		?FormData $clean_form_data = null
	) {
		$this->auth_form_data  = $auth_form_data ?? new FormData();
		$this->clean_form_data = $clean_form_data ?? new FormData();
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
	 * Gets current route guard authorization form data.
	 *
	 * @return \OZONE\OZ\Forms\FormData
	 */
	public function getAuthFormData(): FormData
	{
		return $this->auth_form_data;
	}

	/**
	 * Gets auth form field value.
	 *
	 * @param string     $name
	 * @param null|mixed $def
	 *
	 * @return mixed
	 */
	public function getAuthFormField(string $name, mixed $def = null): mixed
	{
		return $this->clean_form_data->get($name, $def);
	}

	/**
	 * Shortcut for {@see \OZONE\OZ\Http\Request::getUnsafeFormData()}.
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
	 * Shortcut for {@see \OZONE\OZ\Http\Request::getUnsafeFormField()}.
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
}
