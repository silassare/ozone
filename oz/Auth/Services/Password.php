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
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use Throwable;

/**
 * Class Password.
 */
final class Password extends Service
{
	public const ROUTE_PASS_EDIT_BY_AS_ADMIN = 'oz:pass-edit-as-admin';
	public const ROUTE_PASS_EDIT_AS_SELF     = 'oz:pass-edit-as-self';

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

		AuthUsers::updatePassword(
			$user,
			$ri->getCleanFormField(self::FIELD_PASS_NEW),
			$ri->getCleanFormField(self::FIELD_PASS_CURRENT)
		);

		$this->json()
			->setDone('OZ_PASSWORD_EDIT_SUCCESS');
	}

	/**
	 * Edit password: admin only.
	 *
	 * @param RouteInfo $ri
	 *
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	public function actionEditPassByAdmin(RouteInfo $ri): void
	{
		$user = AuthUsers::identifyBySelector($ri->getCleanFormData());

		if (!$user) {
			throw new NotFoundException();
		}

		AuthUsers::updatePassword($user, $ri->getCleanFormField(self::FIELD_PASS_NEW));

		$this->json()
			->setDone('OZ_PASSWORD_EDIT_SUCCESS');
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	public static function registerRoutes(Router $router): void
	{
		$router
			->group('/password/edit', static function (Router $router) {
				$router
					->map(['PATCH', 'POST'], '/admin', static function (RouteInfo $ri) {
						$s = new self($ri);
						$s->actionEditPassByAdmin($ri);

						return $s->respond();
					})
					->name(self::ROUTE_PASS_EDIT_BY_AS_ADMIN)
					->form(AuthUsers::selectorForm(...))
					->withRole(AuthUsers::ADMIN);

				$router
					->map(['PATCH', 'POST'], '/self', static function (RouteInfo $ri) {
						$s = new self($ri);
						$s->actionEditOwnPass($ri);

						return $s->respond();
					})
					->name(self::ROUTE_PASS_EDIT_AS_SELF)
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
		$form->field(self::FIELD_PASS_CURRENT)->required();

		return $form;
	}
}
