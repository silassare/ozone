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
use OZONE\Core\Auth\Providers\PhoneOwnershipVerificationProvider;
use OZONE\Core\Columns\Types\TypePhone;
use OZONE\Core\Forms\Field;
use OZONE\Core\Forms\Form;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class PhoneOwnershipVerificationService.
 */
class PhoneOwnershipVerificationService extends Service
{
	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->post('/auth/verify/phone', static function (RouteInfo $ri) {
			return (new self($ri))->init($ri);
		})
			->form(self::buildInitForm(...));
	}

	/**
	 * @param RouteInfo $ri
	 *
	 * @return Response
	 */
	private function init(RouteInfo $ri): Response
	{
		$fd    = $ri->getCleanFormData();
		$phone = $fd->get('phone');

		$provider = new PhoneOwnershipVerificationProvider($this->getContext(), $phone);

		$provider->generate();

		$this->json()
			->merge($provider->getJSONResponse());

		return $this->respond();
	}

	/**
	 * @return Form
	 */
	private static function buildInitForm(): Form
	{
		$fb = new Form();

		return $fb->addField(new Field('phone', new TypePhone(), true));
	}
}
