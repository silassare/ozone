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

namespace OZONE\OZ\Web;

use OZONE\OZ\Core\Context;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Http\Uri;
use OZONE\OZ\Lang\I18n;
use OZONE\OZ\Lang\Polyglot;
use Throwable;

/**
 * Class WebInject.
 */
class WebInject
{
	private Context $context;

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
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * Shortcut for {@see I18n::t()}.
	 *
	 * usage: $.i18n( $key [, $data [, $format [, $lang ] ] ] )
	 *
	 * @param string      $key
	 * @param array       $data
	 * @param null|string $lang
	 *
	 * @return string
	 */
	public function i18n(string $key, array $data = [], string $lang = null): string
	{
		try {
			return I18n::t($key, $data, $lang, $this->context);
		} catch (Throwable $t) {
			throw new RuntimeException('Translation failed.', [
				'key'  => $key,
				'data' => $data,
				'lang' => $lang,
			], $t);
		}
	}

	/**
	 * Shortcut for {@see \OZONE\OZ\Http\Request::getUri()}.
	 *
	 * @return \OZONE\OZ\Http\Uri
	 */
	public function getRequestUri(): Uri
	{
		return $this->context->getRequest()
			->getUri();
	}

	/**
	 * Shortcut for {@see Context::buildUri()}.
	 *
	 * @param string $path
	 * @param array  $query
	 *
	 * @return \OZONE\OZ\Http\Uri
	 */
	public function buildUri(string $path, array $query = []): Uri
	{
		return $this->context->buildUri($path, $query);
	}

	/**
	 * Shortcut for {@see Context::buildRouteUri()}.
	 *
	 * @param string $route_name
	 * @param array  $params
	 * @param array  $query
	 *
	 * @return \OZONE\OZ\Http\Uri
	 */
	public function buildRouteUri(string $route_name, array $params, array $query = []): Uri
	{
		return $this->context->buildRouteUri($route_name, $params, $query);
	}

	/**
	 * @return string
	 */
	public function getBaseURL(): string
	{
		return $this->context->getBaseUrl();
	}

	/**
	 * Gets current language.
	 *
	 * @return string
	 */
	public function getLanguage(): string
	{
		return Polyglot::getLanguage($this->context);
	}
}
