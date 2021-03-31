<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
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
 * Class TNet
 */
final class TNet extends BaseService
{
	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 */
	public function actionTNet(Context $context)
	{
		$data = [];
		$um   = $context->getUsersManager();

		if ($um->userVerified()) {
			$user_obj              = $um->getCurrentUserObject();
			$data['ok']            = 1;
			$data['_current_user'] = $user_obj->asArray();
		} else {
			$data['ok'] = 0;
		}

		$this->getResponseHolder()
			 ->setData($data);
	}

	/**
	 * @inheritdoc
	 */
	public static function registerRoutes(Router $router)
	{
		$router->get('/tnet', function (RouteInfo $r) {
			$context = $r->getContext();
			$s       = new self($context);
			$s->actionTNet($context);

			return $s->respond();
		});
	}
}
