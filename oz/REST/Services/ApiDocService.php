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

namespace OZONE\Core\REST\Services;

use OZONE\Core\App\Service;
use OZONE\Core\App\Settings;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\REST\Interfaces\ApiDocProviderInterface;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class ApiDocService.
 */
final class ApiDocService extends Service implements ApiDocProviderInterface
{
	public const API_DOC_SPEC_ROUTE = 'oz:api-doc-spec';

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		if (Settings::get('oz.api.doc', 'OZ_API_DOC_ENABLED')) {
			$router
				->get('/api-doc-spec.json', static function (RouteInfo $ri) {
					$s = new self($ri);

					$s->json()
						->setData(ApiDoc::get($ri->getContext()));

					return $s->respond();
				})
				->name(self::API_DOC_SPEC_ROUTE);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('API Doc', 'API documentation & specification.');
		$doc->addOperationFromRoute(
			self::API_DOC_SPEC_ROUTE,
			'GET',
			'Get Specification',
			[
				$doc->success([
					'spec' => $doc->object([], ['description' => 'The OpenAPI specification.']),
				]),
			],
			[
				'tags'        => [$tag->name],
				'description' => 'Get the OpenAPI specification.',
			]
		);
	}
}
