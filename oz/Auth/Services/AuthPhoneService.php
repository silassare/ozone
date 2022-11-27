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

namespace OZONE\OZ\Auth\Services;

use Gobl\DBAL\Types\TypeBool;
use OZONE\OZ\Auth\Auth;
use OZONE\OZ\Auth\AuthScope;
use OZONE\OZ\Auth\Providers\AuthPhone;
use OZONE\OZ\Columns\Types\TypePhone;
use OZONE\OZ\Core\Service;
use OZONE\OZ\Forms\Field;
use OZONE\OZ\Forms\Form;
use OZONE\OZ\Forms\FormData;
use OZONE\OZ\Forms\FormRule;
use OZONE\OZ\Forms\TypesSwitcher;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

/**
 * Class AuthPhoneService.
 */
class AuthPhoneService extends Service
{
	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->post('/auth/phone', static function (RouteInfo $ri) {
			return (new self($ri->getContext()))->init($ri, $ri->getCleanFormData());
		})
			   ->form(self::buildInitForm(...));
	}

	/**
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 * @param \OZONE\OZ\Forms\FormData   $fd
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	private function init(RouteInfo $ri, FormData $fd): Response
	{
		$phone = $fd->get('phone');
		$scope = new AuthScope($phone);

		$provider = Auth::getAuthProvider(AuthPhone::NAME, $ri->getContext(), $scope);

		$provider->generate();

		$this->getJSONResponse()
			 ->merge($provider->getJSONResponse());

		return $this->respond();
	}

	/**
	 * @return \OZONE\OZ\Forms\Form
	 */
	private static function buildInitForm(): Form
	{
		$fb = new Form();

		$fb->addField(new Field('registered', new TypeBool(), false));

		$phone = new TypesSwitcher();
		$phone->when((new FormRule())->isNull('registered'), new TypePhone())
			  ->when((new FormRule())->eq('registered', true), (new TypePhone())->registered())
			  ->when((new FormRule())->eq('registered', false), (new TypePhone())->notRegistered());

		$fb->addField(new Field('phone', $phone, true));

		return $fb;
	}
}
