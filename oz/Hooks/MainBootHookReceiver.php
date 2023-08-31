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

namespace OZONE\Core\Hooks;

use Exception;
use Gobl\ORM\Events\ORMTableFilesGenerated;
use Gobl\ORM\Utils\ORMClassKind;
use OZONE\Core\App\Db;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\MethodNotAllowedException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\FS\Traits\FileEntityTrait;
use OZONE\Core\Hooks\Events\ResponseHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Http\Uri;
use OZONE\Core\OZone;
use OZONE\Core\Router\Events\RouteMethodNotAllowed;
use OZONE\Core\Router\Events\RouteNotFound;
use OZONE\Core\Sessions\Session;
use OZONE\Core\Users\Traits\UserEntityTrait;
use PHPUtils\Events\Event;

/**
 * Class MainBootHookReceiver.
 *
 * @internal
 */
final class MainBootHookReceiver implements BootHookReceiverInterface
{
	/**
	 * Main handler for {@see RouteNotFound} Event.
	 *
	 * @param \OZONE\Core\Router\Events\RouteNotFound $ev
	 *
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 * @throws \OZONE\Core\Exceptions\NotFoundException
	 */
	public static function onRouteNotFound(RouteNotFound $ev): void
	{
		$context = $ev->getContext();
		$request = $ev->getRequest();
		$uri     = $request->getUri();

		// is it root
		if ($context->isApiContext() && '/' === $uri->getPath()) {
			// TODO api doc
			// 1) show api usage doc when this condition are met:
			//		- we are in api context
			//		- debugging mode or allowed in settings
			// 2) show welcome friendly page when this conditions are met:
			//		- we are in web context
			throw new ForbiddenException();
		}

		throw new NotFoundException();
	}

	/**
	 * Main handler for {@see RouteMethodNotAllowed} Event.
	 *
	 * @param \OZONE\Core\Router\Events\RouteMethodNotAllowed $ev
	 *
	 * @throws \OZONE\Core\Exceptions\MethodNotAllowedException
	 */
	public static function onMethodNotAllowed(RouteMethodNotAllowed $ev): void
	{
		if (!$ev->getRequest()
			->isOptions()) { // not a prefetch request
			throw new MethodNotAllowedException(null, [
				'method' => $ev->getRequest()
					->getMethod(),
			]);
		}
	}

	/**
	 * Main handler for {@see ResponseHook} Event.
	 *
	 * @param \OZONE\Core\Hooks\Events\ResponseHook $ev
	 *
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 */
	public static function onResponse(ResponseHook $ev): void
	{
		$context        = $ev->getContext();
		$request        = $ev->getRequest();
		$response       = $ev->getResponse();
		$life_time      = Session::lifetime();
		$h_list         = [];
		$allowed_origin = Settings::get('oz.request', 'OZ_CORS_ALLOWED_ORIGIN');
		// header spoofing can help hacker bypass this
		// so don't be 100% sure, :-)
		$request_origin = $context->getRequestOriginOrReferer();

		if ('self' === $allowed_origin) {
			$allowed_origin = $context->getDefaultOrigin();
		} elseif ('*' === $allowed_origin) {
			// CORS doesn't allow wildcard with Access-Control-Allow-Credentials header set to true
			$allowed_origin = $request_origin ?? $context->getDefaultOrigin();
		}

		if ($request->isOptions()) {
			$h_list['Access-Control-Allow-Headers'] = \implode(', ', $context->getAllowedHeadersNameList());
			$h_list['Access-Control-Allow-Methods'] = \implode(', ', [
				'OPTIONS',
				'GET',
				'POST',
				'PATCH',
				'PUT',
				'DELETE',
			]);
			$h_list['Access-Control-Max-Age']       = $life_time;
		} elseif (!$context->isSubRequest()) {
			// we don't check for sub-request here because often sub-request are made
			// with the original request environment
			if (!empty($request_origin) && $allowed_origin !== $request_origin) {
				$allowed_host = Uri::createFromString($allowed_origin)
					->getHost();
				$origin_host  = Uri::createFromString($request_origin)
					->getHost();
				if ($allowed_host !== $origin_host) {
					throw new ForbiddenException('OZ_CROSS_SITE_REQUEST_NOT_ALLOWED', [
						'origin'        => $request_origin,
						'_origin_host'  => $origin_host,
						'_allowed_host' => $allowed_host,
					]);
				}
			}
		}

		// Remember: CORS is not security. Do not rely on CORS to secure your web site/application.
		// The Access-Control-Allow-Origin header in CORS only dictates
		// which origins should be allowed to make cross-origin requests.
		// Its can be spoofed by the client.
		// Don't rely on it for anything more.

		// allow browser to make CORS request
		$h_list['Access-Control-Allow-Origin'] = $allowed_origin;
		$h_list['Vary']                        = 'Origin';

		// allow browser to send CORS request with cookies
		$h_list['Access-Control-Allow-Credentials'] = 'true';

		// let's try to avoid the click-jacking
		$h_list['X-Frame-Options'] = 'DENY';

		foreach ($h_list as $key => $value) {
			$response = $response->withHeader($key, (string) $value);
		}

		$context->setResponse($response);
	}

	/**
	 * @throws Exception
	 */
	public static function onTableFilesGenerated(ORMTableFilesGenerated $event): void
	{
		$table = $event->getTable();

		if (Db::getOZoneDbNamespace() === $table->getNamespace()) {
			$name  = $table->getName();
			$trait = null;

			if ('oz_users' === $name) {
				$trait = UserEntityTrait::class;
			} elseif ('oz_files' === $name) {
				$trait = FileEntityTrait::class;
			}

			if ($trait) {
				$event->getClass(ORMClassKind::ENTITY)
					->useTrait($trait);
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public static function boot(): void
	{
		RouteNotFound::listen([self::class, 'onRouteNotFound'], Event::RUN_LAST);

		RouteMethodNotAllowed::listen([self::class, 'onMethodNotAllowed'], Event::RUN_LAST);

		ResponseHook::listen([self::class, 'onResponse'], Event::RUN_FIRST);

		if (OZone::isCliMode()) {
			ORMTableFilesGenerated::listen([self::class, 'onTableFilesGenerated'], Event::RUN_FIRST);
		}
	}
}
