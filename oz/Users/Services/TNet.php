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
 * Class TNet.
 */
final class TNet extends Service
{
	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 */
	public function actionTNet(Context $context): void
	{
		$data = [];
		$um   = $context->getUsersManager();

		if ($um->userVerified()) {
			$user_obj              = $um->getCurrentUserObject();
			$data['ok']            = 1;
			$data['_current_user'] = $user_obj->toArray();
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
		$router->get('/tnet', function (RouteInfo $r) {
			$context = $r->getContext();
			$s       = new self($context);
			$s->actionTNet($context);

			return $s->respond();
		});
	}
}
