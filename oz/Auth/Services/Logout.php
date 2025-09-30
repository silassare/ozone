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

namespace OZONE\Core\Auth\Services;

use OZONE\Core\App\Service;
use OZONE\Core\App\Settings;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class Logout.
 */
final class Logout extends Service
{
	public const ROUTE_LOGOUT = 'oz:logout';

	public function actionLogout(): void
	{
		$this->getContext()
			->getAuthUsers()
			->logUserOut();

		$this->json()
			->setDone('OZ_USER_LOGOUT_DONE');
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router
			->post('/logout', static function (RouteInfo $ri) {
				$s = new self($ri);
				$s->actionLogout();

				$response = $s->respond();

				if (Settings::get('oz.cache', 'OZ_CLEAR_SITE_DATA_HEADER_ON_LOGOUT')) {
					$value = Settings::get('oz.cache', 'OZ_CLEAR_SITE_DATA_HEADER_VALUE');

					return $response->withHeader('Clear-Site-Data', $value);
				}

				return $response;
			})
			->name(self::ROUTE_LOGOUT);
	}
}
