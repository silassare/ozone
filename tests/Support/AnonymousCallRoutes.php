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

namespace OZONE\Tests\Support;

use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Interfaces\RouteProviderInterface;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * The routes of a scope the suite calls into as nobody, enabled by that scope's own settings only.
 *
 * Each one answers what it saw in headers, so a test reads a response the way a visitor's client
 * would: what scope it ran in, whose settings it read, who it took the visitor for.
 *
 * @internal
 */
final class AnonymousCallRoutes implements RouteProviderInterface
{
	/**
	 * The settings group a test scope overrides, to tell whose settings a route read.
	 */
	public const string PROBE_GROUP = 'anon.probe';

	#[Override]
	public static function routesVersion(): ?string
	{
		return null;
	}

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router->get('/anon/where', static function (RouteInfo $ri): Response {
			$context = $ri->getContext();

			return $context->getResponse()
				->withHeader('X-Scope', scope()->getName())
				->withHeader('X-Where', (string) Settings::get(self::PROBE_GROUP, 'WHERE'))
				->withHeader('X-User', $context->hasAuthenticatedUser() ? 'yes' : 'no')
				->withHeader('X-Sub', $context->isSubRequest() ? 'yes' : 'no')
				->withHeader('X-IP', (string) $context->getUserIP())
				->withHeader('X-Host', $context->getHost(true))
				->withHeader('X-Accept-Language', $context->getRequest()->getHeaderLine('Accept-Language'));
		})->name('anon:where');

		$router->get('/anon/members', static fn (RouteInfo $ri): Response => $ri->getContext()->getResponse())
			->name('anon:members')
			->withAuthentication(HeaderTestAuth::class)
			->withAuthenticatedUser();

		$router->get('/anon/session', static function (RouteInfo $ri): Response {
			$context = $ri->getContext();

			$context->requireAuthStore()->set('anon_probe', true);

			return $context->getResponse();
		})->name('anon:session');

		$router->get('/anon/redirect', static function (RouteInfo $ri): never {
			$ri->getContext()->redirect('/anon/where');
		})->name('anon:redirect');

		$router->get('/anon/fail', static function (): never {
			throw new RuntimeException('Failing on purpose.');
		})->name('anon:fail');

		$router->get('/anon/fail-twice', static function (RouteInfo $ri): Response {
			$context = $ri->getContext();
			$first   = $context->callRoute('anon:fail', [], [], false);
			$second  = $context->callRoute('anon:fail', [], [], false);

			return $context->getResponse()
				->withHeader('X-Statuses', $first->getStatusCode() . ',' . $second->getStatusCode());
		})->name('anon:fail-twice');

		$router->get('/anon/depth/:n', static function (RouteInfo $ri): Response {
			$context = $ri->getContext();
			$n       = (int) $ri->param('n');

			if (0 === $n) {
				return $context->getResponse()->withHeader('X-Reached', 'yes');
			}

			try {
				return $context->callAnonymously('anon:depth', ['n' => $n - 1]);
			} catch (RuntimeException $e) {
				return $context->getResponse()->withHeader('X-Refused', $e->getMessage());
			}
		})->param('n', '[0-9]+')->name('anon:depth');

		$router->get('/anon/cycle', static function (RouteInfo $ri): Response {
			$context = $ri->getContext();

			try {
				return $context->callAnonymously('anon:cycle');
			} catch (RuntimeException $e) {
				return $context->getResponse()->withHeader('X-Refused', $e->getMessage());
			}
		})->name('anon:cycle');

		$router->get('/anon/back', static function (RouteInfo $ri): Response {
			$context = $ri->getContext();
			$back    = $context->callAnonymously('oz:robots', [], [], 'root');

			return $context->getResponse()
				->withHeader('X-Back', (string) $back->getStatusCode())
				->withHeader('X-Scope', scope()->getName())
				->withHeader('X-Where', (string) Settings::get(self::PROBE_GROUP, 'WHERE'));
		})->name('anon:back');
	}
}
