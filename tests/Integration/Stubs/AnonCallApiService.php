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

namespace __PLH_NAMESPACE__;

use Gobl\ORM\ORMOptions;
use OZONE\Core\App\Service as BaseService;
use OZONE\Core\Db\OZCountriesQuery;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * The service of the `api` scope that other scopes and jobs call as nobody: what it answers reads
 * the database and says who it took the visitor for.
 */
final class AnonCallApiService extends BaseService
{
	public static function registerRoutes(Router $router): void
	{
		$router->get('/anon-call/service', static function (RouteInfo $ri) {
			$context   = $ri->getContext();
			$countries = new OZCountriesQuery()->find(ORMOptions::makePaginated(10))->getTotal();

			$s = new self($ri);
			$s->json()->setDone()->setData([
				'scope'     => scope()->getName(),
				'signed_in' => $context->hasAuthenticatedUser(),
				'countries' => $countries,
				'ip'        => $context->getUserIP(),
			]);

			return $s->respond();
		})->name('anoncall:service');

		$router->get('/anon-call/members', static function (RouteInfo $ri) {
			if (!$ri->getContext()->hasAuthenticatedUser()) {
				throw new ForbiddenException();
			}

			$s = new self($ri);
			$s->json()->setDone()->setData(['secret' => 'ok']);

			return $s->respond();
		})->name('anoncall:members');
	}

	public static function apiDoc(ApiDoc $doc): void {}
}
