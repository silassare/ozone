<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\User\Services;

use OZONE\OZ\Core\BaseService;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

/**
 * Class Logout
 */
final class Logout extends BaseService
{
	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 */
	public function actionLogout(Context $context)
	{
		$context->getUsersManager()
				->logUserOut();

		$this->getResponseHolder()
			 ->setDone('OZ_USER_LOGOUT');
	}

	/**
	 * @inheritdoc
	 */
	public static function registerRoutes(Router $router)
	{
		$router->post('/logout', function (RouteInfo $r) {
			$context = $r->getContext();
			$s       = new self($context);
			$s->actionLogout($context);

			return $s->respond();
		});
	}
}
