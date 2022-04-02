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

namespace OZONE\OZ\Lang;

use OZONE\OZ\Core\Context;
use OZONE\OZ\Router\Events\RouteBeforeRun;
use OZONE\OZ\Router\Interfaces\RouteProviderInterface;
use OZONE\OZ\Router\Router;
use PHPUtils\Events\Event;

/**
 * Class PolyglotRoute.
 */
final class PolyglotRoute implements RouteProviderInterface
{
	public const ROUTE_LANG_PARAM         = 'oz_lang';
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

		RouteBeforeRun::handle(static function (RouteBeforeRun $ev): void {
			$lang = $ev->getRouteInfo()
				->getParam(self::ROUTE_LANG_PARAM);

			if ($lang) {
				Polyglot::setUserLanguage($ev->getContext(), $lang);
			}
		}, Event::RUN_FIRST);
	}
}
