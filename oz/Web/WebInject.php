<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Web;

use Exception;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Lang\Polyglot;
use RuntimeException;

\defined('OZ_SELF_SECURITY_CHECK') || die;

class WebInject
{
	/**
	 * @var \OZONE\OZ\Core\Context
	 */
	private $context;

	/**
	 * WebInject constructor.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 */
	public function __construct(Context $context)
	{
		$this->context = $context;
	}

	/**
	 * WebInject destructor.
	 */
	public function __destruct()
	{
		unset($this->context);
	}

	/**
	 * Gets the context.
	 *
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext()
	{
		return $this->context;
	}

	/**
	 * I18n.
	 *
	 * usage: $.i18n( $key [, $data [, $format [, $lang ] ] ] )
	 *
	 * @param string $key
	 * @param array  $data
	 * @param string $lang
	 *
	 * @return string
	 */
	public function i18n($key, array $data = [], $lang = null)
	{
		try {
			return Polyglot::translate($key, $data, $lang, $this->context);
		} catch (Exception $e) {
			throw new RuntimeException(\sprintf('Translation fail for %s:', $key), $e);
		}
	}

	/**
	 * @return \OZONE\OZ\Http\Uri
	 */
	public function getRequestUri()
	{
		return $this->context->getRequest()
							 ->getUri();
	}

	/**
	 * @param string $path
	 * @param array  $query
	 *
	 * @return \OZONE\OZ\Http\Uri
	 */
	public function buildUri($path, array $query = [])
	{
		return $this->context->buildUri($path, $query);
	}

	/**
	 * @param string $route_name
	 * @param array  $args
	 * @param array  $query
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return \OZONE\OZ\Http\Uri
	 */
	public function buildRouteUri($route_name, array $args, array $query = [])
	{
		return $this->context->buildRouteUri($route_name, $args, $query);
	}

	/**
	 * @return string
	 */
	public function getBaseURL()
	{
		return $this->context->getBaseUrl();
	}

	/**
	 * @return string
	 */
	public function getLanguage()
	{
		return Polyglot::getLanguage($this->context);
	}
}
