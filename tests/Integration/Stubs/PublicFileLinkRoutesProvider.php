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
use OZONE\Core\FS\FS;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * A route that stores a public file and answers the link its storage builds for it, for a test to
 * fetch that link as a client would.
 */
final class PublicFileLinkRoutesProvider extends BaseService
{
	public static function registerRoutes(Router $router): void
	{
		$router->get('/pf-link', static function (RouteInfo $ri) {
			$context = $ri->getContext();
			$content = 'public file ' . \bin2hex(\random_bytes(8));
			$storage = FS::getStorage(FS::PUBLIC_STORAGE);
			$file    = $storage->saveRaw($content, 'text/plain', 'note.txt');

			$s = new self($ri);
			$s->json()->setDone()->setData([
				'uri'     => (string) $storage->publicUri($context, $file),
				'ref'     => $file->getRef(),
				'content' => $content,
			]);

			return $s->respond();
		})->name('pf:link');
	}

	public static function apiDoc(ApiDoc $doc): void {}
}
