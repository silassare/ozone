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

namespace OZONE\OZ\Hooks;

use Exception;
use Gobl\ORM\Events\ORMTableFilesGenerated;
use Gobl\ORM\Utils\ORMClassKind;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\CRUDHandlerTrait;
use OZONE\OZ\Core\DbManager;
use OZONE\OZ\Core\Interfaces\TableCollectionsProviderInterface;
use OZONE\OZ\Core\Interfaces\TableRelationsProviderInterface;
use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Exceptions\MethodNotAllowedException;
use OZONE\OZ\Exceptions\NotFoundException;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\FS\Traits\FileEntityTrait;
use OZONE\OZ\Hooks\Events\DbBeforeLockHook;
use OZONE\OZ\Hooks\Events\ResponseHook;
use OZONE\OZ\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\OZ\Http\Uri;
use OZONE\OZ\OZone;
use OZONE\OZ\Router\Events\RouteMethodNotAllowed;
use OZONE\OZ\Router\Events\RouteNotFound;
use OZONE\OZ\Users\Traits\UserEntityTrait;
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
	 * @param \OZONE\OZ\Router\Events\RouteNotFound $ev
	 *
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 */
	public static function onRouteNotFound(RouteNotFound $ev): void
	{
		$context = $ev->getContext();
		$request = $ev->getRequest();
		$uri     = $request->getUri();

		// is it root
		if ($context->isApiContext() && '/' === $uri->getPath()) {
			// TODO api doc
			// show api usage doc when this condition are met:
			//  - we are in api mode
			//	- debugging mode or allowed in settings
			// show welcome friendly page when this conditions are met:
			//  - we are in web mode
			throw new ForbiddenException();
		}

		throw new NotFoundException();
	}

	/**
	 * Main handler for {@see RouteMethodNotAllowed} Event.
	 *
	 * @param \OZONE\OZ\Router\Events\RouteMethodNotAllowed $ev
	 *
	 * @throws \OZONE\OZ\Exceptions\MethodNotAllowedException
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
	 * @param \OZONE\OZ\Hooks\Events\ResponseHook $ev
	 *
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 */
	public static function onResponse(ResponseHook $ev): void
	{
		$context   = $ev->getContext();
		$request   = $ev->getRequest();
		$response  = $ev->getResponse();
		$session   = $ev->getContext()
			->getSession();
		$life_time = 60 * 60;
		// header spoofing can help hacker bypass this
		// so don't be 100% sure, :-)
		$origin     = $context->getRequestOriginOrReferer();
		$client_url = $origin;
		$client     = null;

		if ($session->isStarted()) {
			$client = $session->getClient();
		}

		if ($client) {
			$client_url = $client->getUrl();
			$life_time  = $client->getSessionLifeTime();
		}

		$h_list = [];

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
			$rule           = Configs::get('oz.clients', 'OZ_CORS_ALLOW_RULE');
			$allowed_origin = $context->getMainUrl();

			switch ($rule) {
				case 'deny':
					if (empty($origin)) {
						$origin = $allowed_origin;
					}

					break;

				case 'any':
					if (empty($origin)) {
						$origin = $allowed_origin;
					} else {
						$allowed_origin = $origin;
					}

					break;

				case 'check':
				default:
					if (empty($origin)) {
						$origin = $allowed_origin;
					} else {
						$allowed_origin = $client_url;
					}
			}

			$allowed_host = Uri::createFromString($allowed_origin)
				->getHost();
			$origin_host  = Uri::createFromString($origin)
				->getHost();

			if ($allowed_host !== $origin_host) {
				// we don't throw this exception in sub-request
				// scenario:
				//  - We want to show error page because the cross site request origin was not allowed
				//  - The sub-request is made with the original request environment
				throw new ForbiddenException('OZ_CROSS_SITE_REQUEST_NOT_ALLOWED', [
					'origin'        => $origin,
					'_origin_host'  => $origin_host,
					'_allowed_host' => $allowed_host,
				]);
			}
		}

		// Remember: CORS is not security. Do not rely on CORS to secure your web site/application.
		// If you are serving protected data, use cookies or OAuth tokens or something
		// other than the Origin header to secure that data. The Access-Control-Allow-Origin header
		// in CORS only dictates which origins should be allowed to make cross-origin requests.
		// Don't rely on it for anything more.

		// allow browser to make CORS request
		$h_list['Access-Control-Allow-Origin'] = $origin;

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

		if (DbManager::OZONE_DB_NAMESPACE === $table->getNamespace()) {
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

		$event->getClass(ORMClassKind::CRUD)
			->useTrait(CRUDHandlerTrait::class);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function boot(): void
	{
		RouteNotFound::handle([self::class, 'onRouteNotFound'], Event::RUN_LAST);

		RouteMethodNotAllowed::handle([self::class, 'onMethodNotAllowed'], Event::RUN_LAST);

		ResponseHook::handle([self::class, 'onResponse'], Event::RUN_FIRST);

		DbBeforeLockHook::handle([self::class, 'registerCustomRelations'], Event::RUN_FIRST);
		DbBeforeLockHook::handle([self::class, 'registerCustomCollections'], Event::RUN_FIRST);

		if (OZone::isCliMode()) {
			ORMTableFilesGenerated::handle([self::class, 'onTableFilesGenerated'], Event::RUN_FIRST);
		}
	}

	/**
	 * Register custom relations.
	 */
	public static function registerCustomRelations(): void
	{
		$relations_settings = Configs::load('oz.db.relations');

		foreach ($relations_settings as $provider => $enabled) {
			if ($enabled) {
				if (!\is_subclass_of($provider, TableRelationsProviderInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Custom relations provider "%s" should implements "%s".',
						$provider,
						TableRelationsProviderInterface::class
					));
				}

				/* @var TableRelationsProviderInterface $provider */
				$provider::defineRelations();
			}
		}
	}

	/**
	 * Register custom collections.
	 */
	public static function registerCustomCollections(): void
	{
		$collections_settings = Configs::load('oz.db.collections');

		foreach ($collections_settings as $provider => $enabled) {
			if ($enabled) {
				if (!\is_subclass_of($provider, TableCollectionsProviderInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Custom collections provider "%s" should implements "%s".',
						$provider,
						TableCollectionsProviderInterface::class
					));
				}

				/* @var TableCollectionsProviderInterface $provider */
				$provider::defineCollections();
			}
		}
	}
}
