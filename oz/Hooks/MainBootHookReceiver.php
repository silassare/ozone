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
use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Events\SessionHijackingDetected;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\MethodNotAllowedException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\FS\Traits\FileEntityTrait;
use OZONE\Core\Hooks\Events\EndRequestHook;
use OZONE\Core\Hooks\Events\RequestHook;
use OZONE\Core\Hooks\Events\ResponseHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Http\CorsPolicy;
use OZONE\Core\OZone;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\REST\Views\ApiDocView;
use OZONE\Core\Router\Events\RouteMethodNotAllowed;
use OZONE\Core\Router\Events\RouteNotFound;
use OZONE\Core\Stores\Drivers\MemoryStore;
use OZONE\Core\Users\Traits\UserEntityTrait;
use OZONE\Core\Users\UsersRepository;
use OZONE\Core\Web\WebView;
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
				// 1) show api usage doc when all this conditions are met:
				//   - we are in api context
				//   - the uri is root
				//   - allowed in settings

				if (
					Settings::get('oz.api.doc', 'OZ_API_DOC_ENABLED')
					&& Settings::get('oz.api.doc', 'OZ_API_DOC_SHOW_ON_INDEX')
				) {
					$context->redirectRoute(ApiDocView::API_DOC_VIEW_ROUTE);
				}

				throw new ForbiddenException();
			}

			if ($context->isWebContext()) {
				// 2) show welcome page when all these conditions are met:
				//   - we are in web context
				//   - the uri is root
				//   - allowed in settings
				if (Settings::get('oz.config', 'OZ_SHOW_WELCOME_PAGE')) {
					$v = new WebView($context);
					$v->setTemplate('oz://oz.welcome.blate');
					$context->respond($v->respond());
				}

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
	 * Main handler for {@see RequestHook} Event.
	 *
	 * Rejects a request from a disallowed origin before it is routed: rejecting it once
	 * the response is ready would let its handler run (writes, emails, payments) first.
	 *
	 * @param RequestHook $ev
	 *
	 * @throws ForbiddenException
	 */
	public static function onRequest(RequestHook $ev): void
	{
		// Sub-requests are skipped: they often run with the original request environment.
		if (!$ev->context->isSubRequest()) {
			self::assertOriginAllowed($ev->context);
		}
	}

	/**
	 * Throws when the request carries an `Origin` the CORS policy disallows.
	 *
	 * Preflights pass: they only get CORS headers, which the browser enforces.
	 *
	 * @throws ForbiddenException
	 */
	public static function assertOriginAllowed(Context $context): void
	{
		if ($context->getRequest()->isOptions()) {
			return;
		}

		// Only the Origin header the browser sets matters to CORS. Falling back to the
		// Referer would wrongly reject ordinary inbound links to web pages.
		$origin = $context->getRequestOrigin();

		if (null !== $origin && !CorsPolicy::fromContext($context)->allows($origin)) {
			throw new ForbiddenException('OZ_CROSS_SITE_REQUEST_NOT_ALLOWED', [
				'origin' => $origin,
			]);
		}
	}

	/**
	 * Main handler for {@see ResponseHook} Event.
	 *
	 * @param ResponseHook $ev
	 */
	public static function onResponse(ResponseHook $ev): void
	{
		$context  = $ev->context;
		$request  = $context->getRequest();
		$response = $context->getResponse();
		$policy   = CorsPolicy::fromContext($context);
		$origin   = $context->getRequestOrigin();

		// CORS only tells browsers which origins may read responses; requests from
		// disallowed origins were already rejected by onRequest().
		$h_list = $policy->headers($origin);

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
			$h_list['Access-Control-Max-Age']       = Settings::get('oz.request', 'OZ_CORS_ALLOWED_MAX_AGE');
		}

		foreach ($h_list as $key => $value) {
			$response = $response->withHeader($key, (string) $value);
		}

		$is_https = 'https' === $request->getUri()->getScheme();

		foreach ((array) Settings::get('oz.request', 'OZ_SECURITY_HEADERS', []) as $name => $value) {
			$name = (string) $name;

			// A security header the handler already set wins, so a route can relax or tighten it.
			if (null === $value || false === $value || '' === $value || $response->hasHeader($name)) {
				continue;
			}

			// Browsers ignore HSTS over http, and sending it there could pin a dev host to https.
			if (!$is_https && 0 === \strcasecmp($name, 'Strict-Transport-Security')) {
				continue;
			}

			$response = $response->withHeader($name, (string) $value);
		}

		$context->setResponse($response);
	}

	/**
	 * @throws Exception
	 */
	public static function onTableFilesGenerated(ORMTableFilesGenerated $event): void
	{
		$table      = $event->getTable();
		$table_name = $table->getName();
		$trait      = null;
		$interface  = null;

		if (UsersRepository::isTableSupported($table)) {
			$trait     = UserEntityTrait::class;
			$interface = AuthUserInterface::class;
		} elseif ('oz_files' === $table_name) {
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
	#[Override]
	public static function boot(): void
	{
		RouteNotFound::listen([self::class, 'onRouteNotFound'], Event::RUN_LAST);

		RouteMethodNotAllowed::listen([self::class, 'onMethodNotAllowed'], Event::RUN_LAST);

		RequestHook::listen([self::class, 'onRequest'], Event::RUN_FIRST);

		ResponseHook::listen([self::class, 'onResponse'], Event::RUN_FIRST);

		// The framework's own per-request state kept outside the Context: released with each request,
		// or a worker would serve the next request from it.
		EndRequestHook::listen(static function (): void {
			ApiDoc::release();
			BaseException::release();
			MemoryStore::release();
		});

		SessionHijackingDetected::listen(static function (SessionHijackingDetected $ev): void {
			oz_logger()->warning('Session hijacking detected.', [
				'session_id' => $ev->session->id(),
				'user_ip'    => $ev->context->getUserIP(),
			]);
		}, Event::RUN_LAST);

		if (OZone::isCliMode()) {
			ORMTableFilesGenerated::listen([self::class, 'onTableFilesGenerated'], Event::RUN_FIRST);
		}
	}
}
