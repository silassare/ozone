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

namespace OZONE\Core\FS\Views;

use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\FS\FileAccess;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\FS;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Web\WebView;

/**
 * Class GetFilesView.
 */
class GetFilesView extends WebView
{
	public const MAIN_ROUTE = 'oz:files';

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$format = Settings::get('oz.files', 'OZ_GET_FILE_URI_PATH_FORMAT');

		$router->get($format, function (RouteInfo $r) {
			return self::handle($r);
		})
			->name(self::MAIN_ROUTE)
			->params([
				'oz_file_id'        => '[0-9]+',
				'oz_file_key'       => '[a-z0-9]{32,}',
				'oz_file_ref'       => '[a-z0-9]{32,}',
				'oz_file_filter'    => '[a-z0-9]+',
				'oz_file_extension' => '[a-z0-9]{1,10}',
			]);
	}

	/**
	 * @param \OZONE\Core\Router\RouteInfo $ri
	 *
	 * @return \OZONE\Core\Http\Response
	 *
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 * @throws \OZONE\Core\Exceptions\InvalidFormException
	 * @throws \OZONE\Core\Exceptions\NotFoundException
	 * @throws \OZONE\Core\Exceptions\UnauthorizedActionException
	 * @throws \OZONE\Core\Exceptions\UnverifiedUserException
	 */
	public static function handle(RouteInfo $ri): Response
	{
		$context         = $ri->getContext();
		$req_file_id     = $ri->getParam('oz_file_id');
		$req_file_key    = $ri->getParam('oz_file_key');
		$req_file_ref    = $ri->getParam('oz_file_ref');
		$req_file_filter = $ri->getParam('oz_file_filter');
		$req_file_ext    = $ri->getParam('oz_file_extension');

		$file = FS::getFileWithId($req_file_id);

		if (!$file || !$file->isValid()) {
			throw new NotFoundException();
		}

		// when the request provide an extension and the extension
		// does not match the file extension
		// we just return a not found
		if (
			$req_file_ext
			&& $req_file_ext !== $file->getExtension()
			&& FS::extensionToMimeType($req_file_ext) !== $file->getMimeType()
		) {
			throw new NotFoundException();
		}

		FileAccess::check($file, $ri, $req_file_key, $req_file_ref);

		$driver = FS::getStorage($file->getStorage());

		$response = $context->getResponse();

		if ($req_file_filter) {
			$response = self::applyFilter($response, $driver->getStream($file), $req_file_filter);
		} else {
			$response = $driver->serve($file, $response);

			if (Settings::get('oz.files', 'OZ_GET_FILE_SHOW_REAL_NAME')) {
				$filename = $file->getName();
				$response = $response->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\";");
			}
		}

		return $response;
	}

	private static function applyFilter(Response $response, FileStream $file, string $filter): Response
	{
		/** @see \OZONE\Core\FS\FilesServer::serve() */

		return $response;
	}
}
