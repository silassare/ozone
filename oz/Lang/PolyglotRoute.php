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

namespace OZONE\Core\Lang;

use OZONE\Core\App\Context;
use OZONE\Core\Router\Events\RouteBeforeRun;
use OZONE\Core\Router\Interfaces\RouteProviderInterface;
use OZONE\Core\Router\Router;
use PHPUtils\Events\Event;

/**
 * Class PolyglotRoute.
 */
final class PolyglotRoute implements RouteProviderInterface
{
	public const ROUTE_LANG_PARAM         = 'lang';
	public const ROUTE_LANG_PARAM_PATTERN = '[a-z]{1,8}(-[a-z]{1,8})?';

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->addGlobalParam(
			self::ROUTE_LANG_PARAM,
			self::ROUTE_LANG_PARAM_PATTERN,
			static function (Context $context) {
				return Polyglot::getLanguage($context);
			}
		);

		RouteBeforeRun::listen(static function (RouteBeforeRun $ev): void {
			$lang = $ev->getRouteInfo()
				->param(self::ROUTE_LANG_PARAM);

			if ($lang) {
				Polyglot::setUserLanguage($ev->getContext(), $lang);
			}
		}, Event::RUN_FIRST);
	}
}
