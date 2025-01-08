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
use OZONE\Core\Auth\Providers\EmailVerificationProvider;
use OZONE\Core\Columns\Types\TypeEmail;
use OZONE\Core\Forms\Field;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormRule;
use OZONE\Core\Forms\TypesSwitcher;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class EmailVerificationAuthService.
 */
class EmailVerificationAuthService extends Service
{
	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->post('/auth/verify/email', static function (RouteInfo $ri) {
			return (new self($ri))->init($ri, $ri->getCleanFormData());
		})
			->form(self::buildInitForm(...));
	}

	/**
	 * @param RouteInfo $ri
	 * @param FormData  $fd
	 *
	 * @return Response
	 */
	private function init(RouteInfo $ri, FormData $fd): Response
	{
		$email = $fd->get('email');

		$provider = new EmailVerificationProvider($this->getContext(), $email);

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

		$fb->addField($registered = new Field('registered', new TypeBool(), false));

		$email = new TypesSwitcher();
		$email->when((new FormRule())->isNull($registered), new TypeEmail())
			->when((new FormRule())->eq($registered, true), (new TypeEmail())->registered())
			->when((new FormRule())->eq($registered, false), (new TypeEmail())->notRegistered());

		$fb->addField(new Field('email', $email, true));

		return $fb;
	}
}
