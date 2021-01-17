<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Lang;

use OZONE\OZ\Core\Context;
use OZONE\OZ\Hooks\HookContext;
use OZONE\OZ\Hooks\HookProvider;
use OZONE\OZ\Hooks\MainHookProvider;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\RouteProviderInterface;
use OZONE\OZ\Router\Router;

final class PolyglotRoute implements RouteProviderInterface
{
	const REG_LANG = '[a-z]{1,8}(-[a-z]{1,8})?';

	const ROUTE_LANG_PLACEHOLDER = 'oz_lang';

	/**
	 * @inheritdoc
	 */
	public static function registerRoutes(Router $router)
	{
		$router->declarePlaceholder(self::ROUTE_LANG_PLACEHOLDER, self::REG_LANG, function (Context $context) {
			return Polyglot::getLanguage($context);
		});

		MainHookProvider::getInstance()
						->onBeforeRouteRun(function (HookContext $hc, RouteInfo $ri) {
							$lang = $ri->getArg(self::ROUTE_LANG_PLACEHOLDER, null);

							if ($lang) {
								Polyglot::setUserLanguage($hc->getContext(), $lang);
							}
						}, HookProvider::RUN_FIRST);
	}
}
