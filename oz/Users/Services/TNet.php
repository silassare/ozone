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
use OZONE\Core\OZone;
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
		$context = $this->getContext();
		$health  = [
			'is_installed'     => OZone::isInstalled(),
			'has_db_access'    => OZone::hasDbAccess(),
			'has_db_installed' => OZone::hasDbInstalled(),
			'has_super_admin'  => OZone::hasSuperAdmin(),
		];

		$user = null;
		if ($context->hasAuthenticatedUser()) {
			$user = $context->auth()->user();
		}

		$this->json()
			->setData([
				'ok'            => null !== $user,
				'_current_user' => $user?->toArray(),
				'_health'       => $health,
			]);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router
			->get('/tnet', static function (RouteInfo $ri) {
				$s = new self($ri);
				$s->actionTNet();

				return $s->respond();
			})
			->name(self::ROUTE_TNET);
	}
}
