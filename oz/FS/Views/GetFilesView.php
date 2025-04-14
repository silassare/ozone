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
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\FS\FileAccess;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\FS;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Router\RouteSharedOptions;
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
		$format      = Settings::get('oz.files', 'OZ_GET_FILE_URI_PATH_FORMAT');
		$format_alts = Settings::get('oz.files', 'OZ_GET_FILE_URI_PATH_FORMAT_ALTS');

		self::defineRouteParams(
			$router
				->get($format, static function (RouteInfo $r) {
					return self::handle($r);
				})
				->name(self::MAIN_ROUTE)
		);

		foreach ($format_alts as $alt) {
			self::defineRouteParams(
				$router->get($alt, static function (RouteInfo $ri) {
					return self::handle($ri, true);
				})
			);
		}
	}

	/**
	 * Define params for the route or route group.
	 *
	 * > This is public so developers can use it in their custom routes.
	 *
	 * @param RouteSharedOptions $routeOrGroup
	 */
	public static function defineRouteParams(RouteSharedOptions $routeOrGroup): void
	{
		$routeOrGroup->params([
			'oz_file_id'        => '[0-9]+',
			'oz_file_auth_ref'  => '[a-z0-9]{32,}',
			'oz_file_auth_key'  => '[a-z0-9]{32,}',
			'oz_file_filters'   => '[a-z0-9\~]+',
			'oz_file_extension' => '[a-z0-9]{1,10}',
		]);
	}

	/**
	 * Handle file request.
	 *
	 * > This is public so developers can use it in their custom routes.
	 *
	 * @param RouteInfo $ri                   The route info
	 * @param bool      $force_show_real_name When true, the file will be served with the real name even if configured otherwise
	 *
	 * @return Response
	 *
	 * @throws InvalidFormException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	public static function handle(RouteInfo $ri, bool $force_show_real_name = false): Response
	{
		$context           = $ri->getContext();
		$req_file_id       = $ri->param('oz_file_id');
		$req_file_auth_key = $ri->param('oz_file_auth_key');
		$req_file_auth_ref = $ri->param('oz_file_auth_ref');
		$req_file_filters  = $ri->param('oz_file_filters');
		$req_file_name     = $ri->param('oz_file_name');
		$req_file_ext      = $ri->param('oz_file_extension');

		$file = FS::getFileByID($req_file_id);

		if (!$file || !$file->isValid()) {
			throw new NotFoundException();
		}

		// when the request provide an extension and the extension
		// does not match the file extension
		// we just return a not found
		if (
			$req_file_ext
			&& $req_file_ext !== $file->getExtension()
			&& FS::extensionToMimeType($req_file_ext) !== $file->getMime()
		) {
			throw new NotFoundException();
		}

		// when the request provide a name and the name
		// does not match the file name
		// we just return a not found
		if ($req_file_name && $req_file_name !== $file->getName()) {
			throw new NotFoundException();
		}

		FileAccess::check($file, $ri, $req_file_auth_key, $req_file_auth_ref);

		$driver = FS::getStorage($file->getStorage());

		$response = $context->getResponse();

		if ($req_file_filters) {
			$response = self::applyFilters($response, $driver->getStream($file), $req_file_filters);
		} else {
			$response = $driver->serve($file, $response);

			if ($force_show_real_name || Settings::get('oz.files', 'OZ_GET_FILE_SHOW_REAL_NAME')) {
				$filename = $file->getRealName();
				$response = $response->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\";");
			}
		}

		return $response;
	}

	/**
	 * Should apply filters to the file.
	 *
	 * @param Response   $response The request response object
	 * @param FileStream $file     The file stream
	 * @param string     $filters  The filters to apply
	 *
	 * @return Response
	 */
	private static function applyFilters(Response $response, FileStream $file, string $filters): Response
	{
		/** @see \OZONE\Core\FS\FilesServer::serve() */
		$filters_list = \explode(FS::FILTERS_SEPARATOR, $filters);

		return $response;
	}
}
