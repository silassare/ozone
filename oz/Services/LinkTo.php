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
use OZONE\OZ\Http\Response;
use OZONE\OZ\Http\Uri;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
use Throwable;

/**
 * Class LinkTo.
 */
final class LinkTo extends Service
{
	public const URI_KEY = 'oz_link_to_key';

	public const MAIN_ROUTE = 'oz:link-to';

	/**
	 * Gets link for authorization.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param \OZONE\OZ\Http\Uri     $next
	 * @param int                    $expire_at
	 *
	 * @return \OZONE\OZ\Http\Uri
	 */
	public static function buildHiddenUri(Context $context, Uri $next, int $expire_at = 0): Uri
	{
		$link_key = Hasher::hash32();

		$context->getSession()
			->getDataStore()
			->set('link_to_cfg.' . $link_key, [
				'expire_at' => $expire_at,
				'next'      => (string) $next,
			]);

		return $context->buildRouteUri(self::MAIN_ROUTE, [
			self::URI_KEY => $link_key,
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$route_path = Configs::get('oz.paths', 'OZ_LINK_TO_ROUTE_PATH');

		$router->get($route_path, function (RouteInfo $ri) {
			return (new self($ri->getContext()))->handleRequest($ri);
		})->name(self::MAIN_ROUTE)->param(self::URI_KEY, '[a-z0-9]{32}');
	}

	/**
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @return \OZONE\OZ\Http\Response
	 *
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 * @throws Throwable
	 */
	private function handleRequest(RouteInfo $ri): Response
	{
		$context       = $ri->getContext();
		$response      = $context->getResponse();
		$session       = $context->getSession();
		$link_key      = $ri->getParam(self::URI_KEY);
		$key           = 'link_to_cfg.' . $link_key;
		$data          = $session->getDataStore()->get($key);

		if (empty($data)) {
			throw new NotFoundException();
		}

		$expire_at = $data['expire_at'];
		$next      = $data['next'];

		if ($expire_at && $expire_at < \time()) {
			throw new NotFoundException('OZ_LINK_EXPIRED');
		}

		return $response->withRedirect($next);
	}
}
