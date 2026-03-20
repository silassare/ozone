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

namespace OZONE\Core\Services;

use OpenApi\Annotations\MediaType;
use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Service;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\Uri;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Utils\Hasher;

/**
 * Class QRCode.
 */
final class QRCode extends Service
{
	public const QR_CODE_KEY = 'oz_qr_code_key';

	public const QR_CODE_ROUTE = 'oz:qr-code';

	/**
	 * Gets qr-code image uri.
	 *
	 * @param Context $context
	 * @param string  $data
	 * @param int     $expire_at
	 *
	 * @return Uri the qr-code info
	 */
	public static function buildQrCodeUri(Context $context, string $data, int $expire_at): Uri
	{
		$qr_code_key = Hasher::hash32();

		$context->requireAuthStore()
			->set('oz.qr_code_cfg.' . $qr_code_key, [
				'expire_at' => $expire_at,
				'data'      => $data,
			]);

		return $context->buildRouteUri(self::QR_CODE_ROUTE, [
			self::QR_CODE_KEY => $qr_code_key,
		]);
	}

	/**
	 * Returns a response with the qrcode image.
	 *
	 * @param Context $context
	 * @param string  $qr_code_key
	 *
	 * @return Response
	 *
	 * @throws NotFoundException
	 */
	public static function generateQrCodeImage(Context $context, string $qr_code_key): Response
	{
		$response = $context->getResponse();
		$key      = 'oz.qr_code_cfg.' . $qr_code_key;
		$data     = $context->requireAuthStore()
			->get($key);

		if (empty($data)) {
			throw new NotFoundException();
		}

		$expire_at = $data['expire_at'];

		if ($expire_at && $expire_at < \time()) {
			throw new NotFoundException('OZ_QR_CODE_HAS_EXPIRED');
		}

		$file = \tmpfile();

		// TODO QRCODE
		// $code = $data['code'];
		// \QRcode::png($code, $file, QR_ECLEVEL_H, 20);

		$body = new Body($file);

		return $response->withHeader('Content-type', 'image/png')
			->withBody($body);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$route_path = Settings::get('oz.paths', 'OZ_QR_CODE_ROUTE_PATH');

		$router
			->get($route_path, static fn (RouteInfo $ri) => self::generateQrCodeImage($ri->getContext(), $ri->param(self::QR_CODE_KEY)))
			->name(self::QR_CODE_ROUTE)
			->param(self::QR_CODE_KEY, '[a-z0-9]{32}');
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('Assets', 'Static asset generation endpoints.');
		$doc->addOperationFromRoute(
			self::QR_CODE_ROUTE,
			'GET',
			'Get QR Code Image',
			[
				$doc->response(200, 'PNG QR code image.', [
					'image/png' => new MediaType([
						'schema' => $doc->type('string', null, ['format' => 'binary']),
					]),
				]),
			],
			[
				'tags'        => [$tag->name],
				'operationId' => 'Assets.qrCode',
				'description' => 'Get a QR code image for the given key.',
			]
		);
	}
}
