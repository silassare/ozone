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

use OZONE\Core\App\Service as BaseService;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Db\OZFilesController;
use OZONE\Core\Db\OZFilesCrud;
use OZONE\Core\Db\OZFilesQuery;
use OZONE\Core\Db\OZFilesResults;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * `/first-class/:kind` loads one generated class of the files table before any other ORM class of
 * the request (the database is then initialized while that class loads), then reads the table as a
 * request does, through its controller: checked by the table's CRUD listeners.
 */
final class FirstClassRoutesProvider extends BaseService
{
	public static function registerRoutes(Router $router): void
	{
		$router->get('/first-class/:kind', static function (RouteInfo $ri) {
			match ($ri->param('kind')) {
				'entity'     => new OZFile(),
				'query'      => new OZFilesQuery(),
				'controller' => new OZFilesController(),
				'results'    => \class_exists(OZFilesResults::class),
				'crud'       => OZFilesCrud::new(),
			};

			$count = (new OZFilesController())->getAllItems()->count();

			$s = new self($ri);
			$s->json()->setDone()->setData(['count' => $count]);

			return $s->respond();
		})->param('kind', 'entity|query|controller|results|crud')->name('test:first-class');
	}

	public static function apiDoc(ApiDoc $doc): void {}
}
