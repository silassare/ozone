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

use Override;
use OZONE\Core\App\Service;
use OZONE\Core\Auth\Providers\EmailOwnershipVerificationProvider;
use OZONE\Core\Columns\Types\TypeEmail;
use OZONE\Core\Forms\Field;
use OZONE\Core\Forms\Form;
use OZONE\Core\Http\Response;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class EmailOwnershipVerificationService.
 */
class EmailOwnershipVerificationService extends Service
{
	public const ROUTE_VERIFY_EMAIL = 'oz:auth:verify:email';

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router->post('/auth/verify/email', static function (RouteInfo $ri) {
			return (new self($ri))->init($ri);
		})
			->name(self::ROUTE_VERIFY_EMAIL)
			->form(self::buildInitForm(...));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('Authorization', 'Authorization flow endpoints.');
		$op  = $doc->addOperationFromRoute(self::ROUTE_VERIFY_EMAIL, 'POST', 'Verify Email Ownership', [
			$doc->success(['ref' => $doc->string('The authorization reference.')]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Auth.verifyEmail',
			'description' => 'Start an email address ownership verification flow.',
		]);
		$op->requestBody = $doc->requestBody([
			'application/json' => $doc->json($doc->object([
				'email' => $doc->string('The email address to verify.'),
			])),
		]);
	}

	/**
	 * @param RouteInfo $ri
	 *
	 * @return Response
	 */
	private function init(RouteInfo $ri): Response
	{
		$fd    =   $ri->getCleanFormData();
		$email = $fd->get('email');

		$provider = new EmailOwnershipVerificationProvider($this->getContext(), $email);

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

		return $fb->addField(new Field('email', new TypeEmail(), true));
	}
}
