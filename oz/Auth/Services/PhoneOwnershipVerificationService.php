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
use OZONE\Core\Auth\Providers\PhoneOwnershipVerificationProvider;
use OZONE\Core\Columns\Types\TypePhone;
use OZONE\Core\Forms\Form;
use OZONE\Core\Http\Response;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class PhoneOwnershipVerificationService.
 */
class PhoneOwnershipVerificationService extends Service
{
	public const ROUTE_VERIFY_PHONE = 'oz:auth:verify:phone';

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router->post('/auth/verify/phone', static function (RouteInfo $ri) {
			return (new self($ri))->init($ri);
		})
			->name(self::ROUTE_VERIFY_PHONE)
			->form(self::buildInitForm(...));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('Authorization', 'Authorization flow endpoints.');
		$op  = $doc->addOperationFromRoute(self::ROUTE_VERIFY_PHONE, 'POST', 'Verify Phone Ownership', [
			$doc->success(['ref' => $doc->string('The authorization reference.')]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Auth.verifyPhone',
			'description' => 'Start a phone number ownership verification flow.',
		]);
		$op->requestBody = $doc->requestBody([
			'application/json' => $doc->json($doc->object([
				'phone' => $doc->string('The phone number to verify (E.164 format).'),
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

		$fb->field('phone')->type(new TypePhone())->required(true);

		return $fb;
	}
}
