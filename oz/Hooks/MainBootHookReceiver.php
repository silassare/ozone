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
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\MethodNotAllowedException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\FS\Traits\FileEntityTrait;
use OZONE\Core\Hooks\Events\ResponseHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Http\Uri;
use OZONE\Core\OZone;
use OZONE\Core\REST\Views\ApiDocView;
use OZONE\Core\Router\Events\RouteMethodNotAllowed;
use OZONE\Core\Router\Events\RouteNotFound;
use OZONE\Core\Users\Traits\UserEntityTrait;
use OZONE\Core\Users\UsersRepository;
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
	 * @param RouteNotFound $ev
	 *
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 */
	public static function onRouteNotFound(RouteNotFound $ev): void
	{
		$context = $ev->context;
		$request = $context->getRequest();
		$uri     = $request->getUri();

		if ('/' === $uri->getPath()) {
			if ($context->isApiContext()) {
				// 1) show api usage doc as all this conditions are met:
				//		- we are in api context
				//      - the uri is root
				//		- allowed in settings

				if (
					Settings::get('oz.api.doc', 'OZ_API_DOC_ENABLED')
					&& Settings::get('oz.api.doc', 'OZ_API_DOC_SHOW_ON_INDEX')
				) {
					$context->redirectRoute(ApiDocView::API_DOC_VIEW_ROUTE);

					return;
				}

				throw new ForbiddenException();
			}

			if ($context->isWebContext()) {
				// TODO welcome friendly page
				// 2) show welcome friendly page as all this conditions are met:
				//		- we are in web context
				//      - the uri is root
				throw new ForbiddenException();
			}
		}

		throw new NotFoundException();
	}

	/**
	 * Main handler for {@see RouteMethodNotAllowed} Event.
	 *
	 * @param RouteMethodNotAllowed $ev
	 *
	 * @throws MethodNotAllowedException
	 */
	public static function onMethodNotAllowed(RouteMethodNotAllowed $ev): void
	{
		$request = $ev->context->getRequest();
		if (!$request->isOptions()) { // not a prefetch request
			throw new MethodNotAllowedException(null, [
				'method' => $request->getMethod(),
			]);
		}
	}

	/**
	 * Main handler for {@see ResponseHook} Event.
	 *
	 * @param ResponseHook $ev
	 *
	 * @throws ForbiddenException
	 */
	public static function onResponse(ResponseHook $ev): void
	{
		$context        = $ev->context;
		$request        = $context->getRequest();
		$response       = $context->getResponse();
		$life_time      = Settings::get('oz.request', 'OZ_CORS_ALLOWED_MAX_AGE');
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
		$h_list['X-Frame-Options'] = 'SAMEORIGIN';

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
		$table     = $event->getTable();
		$name      = $table->getName();
		$trait     = null;
		$interface = null;

		if (UsersRepository::supported($name)) {
			$trait     = UserEntityTrait::class;
			$interface = AuthUserInterface::class;
		} elseif ('oz_files' === $name) {
			$trait = FileEntityTrait::class;
		}

		if ($interface) {
			$event->getClass(ORMClassKind::ENTITY)
				->implements($interface);
		}
		if ($trait) {
			$event->getClass(ORMClassKind::ENTITY)
				->useTrait($trait);
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
