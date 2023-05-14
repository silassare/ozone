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

namespace OZONE\OZ\FS\Views;

use OZONE\OZ\Core\Configs;
use OZONE\OZ\Exceptions\NotFoundException;
use OZONE\OZ\FS\FileAccess;
use OZONE\OZ\FS\FileStream;
use OZONE\OZ\FS\FilesUtils;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
use OZONE\OZ\Web\WebView;

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
		$format = Configs::get('oz.files', 'OZ_GET_FILE_URI_PATH_FORMAT');

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
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @return \OZONE\OZ\Http\Response
	 *
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 */
	public static function handle(RouteInfo $ri): Response
	{
		$context         = $ri->getContext();
		$req_file_id     = $ri->getParam('oz_file_id');
		$req_file_key    = $ri->getParam('oz_file_key');
		$req_file_ref    = $ri->getParam('oz_file_ref');
		$req_file_filter = $ri->getParam('oz_file_filter');
		$req_file_ext    = $ri->getParam('oz_file_extension');

		$file = FilesUtils::getFileWithId($req_file_id);

		if (!$file || !$file->isValid()) {
			throw new NotFoundException();
		}

		// when the request provide an extension and the extension
		// does not match the file extension
		// we just return a not found
		if ($req_file_ext && $req_file_ext !== $file->getExtension() && FilesUtils::extensionToMimeType($req_file_ext) !== $file->getMimeType()) {
			throw new NotFoundException();
		}

		$fa = new FileAccess($context, $file);
		$fa->check($req_file_key, $req_file_ref);

		$driver = FilesUtils::getFileDriver($file->getDriver());

		$response = $context->getResponse();

		if ($req_file_filter) {
			$response = self::applyFilter($response, $driver->getStream($file), $req_file_filter);
		} else {
			$response = $driver->serve($file, $response);

			if (Configs::get('oz.files', 'OZ_GET_FILE_DOWNLOAD_REAL_NAME')) {
				$filename = $file->getName();
				$response = $response->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\";");
			}
		}

		return $response;
	}

	private static function applyFilter(Response $response, FileStream $file, string $filter): Response
	{
		/* {@see \OZONE\OZ\FS\FilesServer::serve()} */

		return $response;
	}
}
