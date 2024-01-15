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

use OZONE\Core\App\Context;
use OZONE\Core\App\Service;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\Uri;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Utils\Hasher;
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
	 * @param Context $context
	 * @param Uri     $next
	 * @param int     $expire_at
	 *
	 * @return Uri
	 */
	public static function buildHiddenUri(Context $context, Uri $next, int $expire_at = 0): Uri
	{
		$link_key = Hasher::hash32();

		$context->requireState()
			->set('oz.link_to_cfg.' . $link_key, [
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
		$route_path = Settings::get('oz.paths', 'OZ_LINK_TO_ROUTE_PATH');

		$router
			->get($route_path, static function (RouteInfo $ri) {
				return (new self($ri->getContext()))->handleRequest($ri);
			})
			->name(self::MAIN_ROUTE)
			->param(self::URI_KEY, '[a-z0-9]{32}');
	}

	/**
	 * @param RouteInfo $ri
	 *
	 * @return Response
	 *
	 * @throws NotFoundException
	 * @throws Throwable
	 */
	private function handleRequest(RouteInfo $ri): Response
	{
		$context  = $ri->getContext();
		$response = $context->getResponse();
		$link_key = $ri->param(self::URI_KEY);
		$key      = 'oz.link_to_cfg.' . $link_key;
		$data     = $context->requireState()
			->get($key);

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
