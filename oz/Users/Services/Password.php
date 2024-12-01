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
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Users\Users;
use Throwable;

/**
 * Class Password.
 */
final class Password extends Service
{
	public const ROUTE_PASS_EDIT_BY_ADMIN = 'oz:pass-edit-admin';
	public const ROUTE_PASS_EDIT_SELF     = 'oz:pass-edit-self';

	protected const FIELD_PASS_NEW     = 'pass_new';
	protected const FIELD_PASS_CURRENT = 'pass_current';

	/**
	 * Edit password: verified user only.
	 *
	 * @param RouteInfo $ri
	 *
	 * @throws UnauthorizedActionException
	 */
	public function actionEditOwnPass(RouteInfo $ri): void
	{
		$context = $ri->getContext();
		$user    = $context->auth()->user();

		Users::updatePass(
			$user,
			$ri->getCleanFormField(self::FIELD_PASS_NEW),
			$ri->getCleanFormField(self::FIELD_PASS_CURRENT)
		);

		$this->json()
			->setDone('OZ_PASSWORD_EDIT_SUCCESS')
			->setData($user);
	}

	/**
	 * Edit password: admin only.
	 *
	 * @param RouteInfo $ri
	 * @param string    $uid
	 *
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	public function actionEditPassAdmin(RouteInfo $ri, string $uid): void
	{
		$user = Users::identify($uid);

		if (!$user) {
			throw new NotFoundException();
		}

		Users::updatePass($user, $ri->getCleanFormField(self::FIELD_PASS_NEW));

		$this->json()
			->setDone('OZ_PASSWORD_EDIT_SUCCESS')
			->setData($user);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	public static function registerRoutes(Router $router): void
	{
		$router
			->group('/users', static function (Router $router) {
				$router
					->map(['PATCH', 'POST'], '/{uid}/password/edit', static function (RouteInfo $ri) {
						$s = new self($ri);
						$s->actionEditPassAdmin($ri, $ri->param('uid'));

						return $s->respond();
					})
					->name(self::ROUTE_PASS_EDIT_BY_ADMIN)
					->params(['uid' => '\d+'])
					->withRole(Users::ADMIN);

				$router
					->map(['PATCH', 'POST'], '/password/edit', static function (RouteInfo $ri) {
						$s = new self($ri);
						$s->actionEditOwnPass($ri);

						return $s->respond();
					})
					->name(self::ROUTE_PASS_EDIT_SELF)
					->form(self::currentPassForm(...));
			})
			->form(self::newPassForm(...));
	}

	/**
	 * @return Form
	 */
	public static function newPassForm(): Form
	{
		$form = new Form();

		$form->doubleCheck(
			$form
				->field(self::FIELD_PASS_NEW)
				->type(new TypePassword())
				->required()
		);

		return $form;
	}

	/**
	 * @return Form
	 */
	public static function currentPassForm(): Form
	{
		$form = new Form();
		$form->field(self::FIELD_PASS_CURRENT);

		return $form;
	}
}
