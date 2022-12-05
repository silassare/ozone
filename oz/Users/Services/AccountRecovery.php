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

use OZONE\OZ\Columns\Types\TypePassword;
use OZONE\OZ\Core\Service;
use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Forms\Form;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

/**
 * Class AccountRecovery.
 */
final class AccountRecovery extends Service
{
	public const ROUTE_ACCOUNT_RECOVERY = 'oz:account-recovery';

	/**
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	public function actionRecover(RouteInfo $ri): void
	{
		$context = $ri->getContext();

		/** @var \OZONE\OZ\Db\OZAuth $auth */
		$auth = $ri->getAuthFormField('2fa');

		$um  = $context->getUsersManager();
		$for = $auth->getFor();

		$user = $um::searchUserWithPhone($for) ?? $um::searchUserWithEmail($for);

		if (!$user || !$user->getValid()) {
			throw new ForbiddenException();
		}

		$new_pass = $ri->getCleanFormField('pass');

		$um->updateUserPass($user, $new_pass)
			->logUserIn($user);

		$this->getJSONResponse()
			->setDone('OZ_PASSWORD_EDIT_SUCCESS')
			->setData($user);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->post('/account-recovery', function (RouteInfo $ri) {
			$s = new self($ri);

			$s->actionRecover($ri);

			return $s->respond();
		})
			->name(self::ROUTE_ACCOUNT_RECOVERY)
			->with2FA()
			->form(self::editPassForm(...));
	}

	/**
	 * @return \OZONE\OZ\Forms\Form
	 */
	public static function editPassForm(): Form
	{
		$form = new Form();
		$pass = $form->field('pass')
			->type(new TypePassword())
			->required();

		return $form->doubleCheck($pass);
	}
}
