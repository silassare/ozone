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

namespace OZONE\Core\Users\Services;

use OZONE\Core\App\Service;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class TNet.
 */
final class TNet extends Service
{
	public const ROUTE_TNET = 'oz:tnet';

	public function actionTNet(): void
	{
		$data    = [];
		$context = $this->getContext();

		if ($context->hasAuthenticatedUser()) {
			$data['ok']            = 1;
			$data['_current_user'] = $context->user()
				->toArray();
		} else {
			$data['ok'] = 0;
		}

		$this->getJSONResponse()
			->setData($data);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->get('/tnet', function (RouteInfo $ri) {
			$s = new self($ri);
			$s->actionTNet();

			return $s->respond();
		})
			->name(self::ROUTE_TNET);
	}
}
