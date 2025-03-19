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

namespace OZONE\Core\REST\Views;

use OZONE\Core\App\Settings;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Web\WebView;

/**
 * Class ApiDocView.
 */
final class ApiDocView extends WebView
{
	public const API_DOC_VIEW_ROUTE = 'oz:api-doc-view';

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		if (Settings::get('oz.api.doc', 'OZ_API_DOC_ENABLED')) {
			$router
				->get('/api-doc-view.html', static function (RouteInfo $ri) {
					$v = (new self($ri));

					$v->setTemplate('oz://oz.api.doc.view.blate')
						->inject(
							ApiDoc::get($ri->getContext())->viewInject()
						);

					return $v->respond();
				})
				->name(self::API_DOC_VIEW_ROUTE);
		}
	}
}
