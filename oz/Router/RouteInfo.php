<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Router;

use OZONE\OZ\Core\Context;

class RouteInfo
{
	/**
	 * @var \OZONE\OZ\Core\Context
	 */
	private $context;

	/**
	 * @var \OZONE\OZ\Router\Route
	 */
	private $route;

	/**
	 * @var array
	 */
	private $args;

	/**
	 * RouteContext constructor.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param \OZONE\OZ\Router\Route $route
	 * @param array                  $args
	 */
	public function __construct(Context $context, Route $route, array $args)
	{
		$this->context = $context;
		$this->route   = $route;
		$this->args    = $args;
	}

	/**
	 * @return \OZONE\OZ\Http\Uri
	 */
	public function getUri()
	{
		return $this->context->getRequest()
							 ->getUri();
	}

	/**
	 * @return \OZONE\OZ\Http\UploadedFile[]
	 */
	public function getUploadedFiles()
	{
		return $this->context->getRequest()
							 ->getUploadedFiles();
	}

	/**
	 * @return array
	 */
	public function getFormData()
	{
		return $this->context->getRequest()
							 ->getFormData();
	}

	/**
	 * @param string $name
	 * @param mixed  $def
	 *
	 * @return mixed
	 */
	public function getFormField($name, $def = null)
	{
		return $this->context->getRequest()
							 ->getFormField($name, $def);
	}

	/**
	 * @return array
	 */
	public function getQueryParams()
	{
		return $this->context->getRequest()
							 ->getQueryParams();
	}

	/**
	 * @param string $key
	 * @param mixed  $def
	 *
	 * @return array
	 */
	public function getQueryParam($key, $def = null)
	{
		return $this->context->getRequest()
							 ->getQueryParam($key, $def);
	}

	/**
	 * @return array
	 */
	public function getArgs()
	{
		return $this->args;
	}

	/**
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext()
	{
		return $this->context;
	}

	/**
	 * @param string $name
	 * @param mixed  $def
	 *
	 * @return mixed
	 */
	public function getArg($name, $def = null)
	{
		if (isset($this->args[$name])) {
			return $this->args[$name];
		}

		return $def;
	}

	/**
	 * @return \OZONE\OZ\Router\Route
	 */
	public function getRoute()
	{
		return $this->route;
	}
}
