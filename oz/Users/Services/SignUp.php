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

use OZONE\OZ\Core\Service;
use OZONE\OZ\Db\OZUser;
use OZONE\OZ\Db\OZUsersController;
use OZONE\OZ\Forms\Form;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

/**
 * Class SignUp.
 */
final class SignUp extends Service
{
	/**
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	public function actionSignUp(RouteInfo $ri): void
	{
		$controller = new OZUsersController();
		$user       = $controller->addItem($ri->getCleanFormData()->getData());

		$ri->getContext()
			->getUsersManager()
			->logUserIn($user);

		$this->getJSONResponse()
			->setDone('OZ_SIGNUP_SUCCESS')
			->setData($user);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->post('/signup', function (RouteInfo $r) {
			$s       = new self($r);
			$s->actionSignUp($r);

			return $s->respond();
		})->form(Form::fromTable(OZUser::TABLE_NAME))
			->with2FA();
	}
}
