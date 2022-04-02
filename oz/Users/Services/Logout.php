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

namespace OZONE\OZ\Users\Services;

use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\Service;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

/**
 * Class Logout.
 */
final class Logout extends Service
{
	/**
	 * @param \OZONE\OZ\Core\Context $context
	 */
	public function actionLogout(Context $context): void
	{
		$context->getUsersManager()
			->logUserOut();

		$this->getJSONResponse()
			->setDone('OZ_USER_LOGOUT');
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->post('/logout', function (RouteInfo $r) {
			$context = $r->getContext();
			$s       = new self($context);
			$s->actionLogout($context);

			return $s->respond();
		});
	}
}
