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

namespace OZONE\OZ\Services;

use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Core\Service;
use OZONE\OZ\Exceptions\NotFoundException;
use OZONE\OZ\Http\Body;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Http\Uri;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

/**
 * Class QRCode.
 */
final class QRCode extends Service
{
	public const QR_CODE_KEY   = 'oz_qr_code_key';

	public const QR_CODE_ROUTE = 'oz:qr-code';

	/**
	 * Gets qr-code image uri.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param string                 $data
	 * @param int                    $expire_at
	 *
	 * @return \OZONE\OZ\Http\Uri the qr-code info
	 */
	public static function buildQrCodeUri(Context $context, string $data, int $expire_at): Uri
	{
		$qr_code_key = Hasher::hash32();

		$context->getSession()
			->getDataStore()
			->set('qr_code_cfg.' . $qr_code_key, [
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
	 * @param \OZONE\OZ\Core\Context $context
	 * @param string                 $qr_code_key
	 *
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public static function generateQrCodeImage(Context $context, string $qr_code_key): Response
	{
		$response      = $context->getResponse();
		$session       = $context->getSession();
		$key           = 'qr_code_cfg.' . $qr_code_key;
		$data          = $session->getDataStore()->get($key);

		if (empty($data)) {
			throw new NotFoundException();
		}

		$expire_at = $data['expire_at'];
		$code      = $data['code'];

		if ($expire_at && $expire_at < \time()) {
			throw new NotFoundException('OZ_QR_CODE_HAS_EXPIRED');
		}

		$file = \tmpfile();

		// TODO QRCODE
		\QRcode::png($code, $file, QR_ECLEVEL_H, 20);

		$body = new Body($file);

		return $response->withHeader('Content-type', 'image/png')
			->withBody($body);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$route_path = Configs::get('oz.paths', 'OZ_QR_CODE_ROUTE_PATH');

		$router->get($route_path, function (RouteInfo $r) {
			return self::generateQrCodeImage($r->getContext(), $r->getParam(self::QR_CODE_KEY));
		})->name(self::QR_CODE_ROUTE)->param(self::QR_CODE_KEY, '[a-z0-9]{32}');
	}
}
