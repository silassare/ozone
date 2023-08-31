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

use Gobl\DBAL\Types\TypeBool;
use OZONE\Core\App\Service;
use OZONE\Core\Auth\Providers\PhoneVerificationAuthProvider;
use OZONE\Core\Columns\Types\TypePhone;
use OZONE\Core\Forms\Field;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormRule;
use OZONE\Core\Forms\TypesSwitcher;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class PhoneVerificationAuthService.
 */
class PhoneVerificationAuthService extends Service
{
	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->post('/auth/verify/phone', static function (RouteInfo $ri) {
			return (new self($ri))->init($ri, $ri->getCleanFormData());
		})
			->form(self::buildInitForm(...));
	}

	/**
	 * @param \OZONE\Core\Router\RouteInfo $ri
	 * @param \OZONE\Core\Forms\FormData   $fd
	 *
	 * @return \OZONE\Core\Http\Response
	 */
	private function init(RouteInfo $ri, FormData $fd): Response
	{
		$phone = $fd->get('phone');

		$provider = new PhoneVerificationAuthProvider($this->getContext(), $phone);

		$provider->generate();

		$this->json()
			->merge($provider->getJSONResponse());

		return $this->respond();
	}

	/**
	 * @return \OZONE\Core\Forms\Form
	 */
	private static function buildInitForm(): Form
	{
		$fb = new Form();

		$fb->addField($registered = new Field('registered', new TypeBool(), false));

		$phone = new TypesSwitcher();
		$phone->when((new FormRule())->isNull($registered), new TypePhone())
			->when((new FormRule())->eq($registered, true), (new TypePhone())->registered())
			->when((new FormRule())->eq($registered, false), (new TypePhone())->notRegistered());

		$fb->addField(new Field('phone', $phone, true));

		return $fb;
	}
}
