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

namespace OZONE\Core\Web;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Interfaces\RouteProviderInterface;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Web\Interfaces\RobotsTxtProviderInterface;

/**
 * Class RobotsTxt.
 *
 * The `oz:robots` route: `GET /robots.txt`, in every scope, answered with what the provider named in
 * `OZ_ROBOTS_TXT_PROVIDER` (`oz.request`) says ({@see RobotsTxtProviderInterface}).
 *
 * A route and not a file in the document root, so a plugin or a project answers per scope and per
 * host without writing anything there. The web server configurations OZone generates send
 * `/robots.txt` to the entry point even where a file of that name exists, so what a provider answers
 * is what a crawler reads.
 */
final class RobotsTxt implements RouteProviderInterface
{
	public const string ROUTE = 'oz:robots';

	public const string PATH = '/robots.txt';

	/**
	 * The provider `OZ_ROBOTS_TXT_PROVIDER` names.
	 */
	public static function provider(): RobotsTxtProviderInterface
	{
		$class = Settings::get('oz.request', 'OZ_ROBOTS_TXT_PROVIDER', DefaultRobotsTxtProvider::class);

		if (!\is_string($class) || !\is_subclass_of($class, RobotsTxtProviderInterface::class)) {
			throw new RuntimeException(\sprintf(
				'The robots.txt provider "%s" should implement "%s".',
				\is_string($class) ? $class : \get_debug_type($class),
				RobotsTxtProviderInterface::class
			))->suspectConfig('oz.request', 'OZ_ROBOTS_TXT_PROVIDER');
		}

		return new $class();
	}

	/**
	 * The answer to a request for `/robots.txt`.
	 */
	public static function answer(Context $context): Response
	{
		$text = self::provider()->robotsTxt($context);

		return $context->getResponse()
			->withHeader('Content-Type', 'text/plain; charset=utf-8')
			->withBody(Body::fromString($text));
	}

	/**
	 * Null: the route is written in code.
	 */
	#[Override]
	public static function routesVersion(): ?string
	{
		return null;
	}

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router
			->get(self::PATH, static fn (RouteInfo $ri): Response => self::answer($ri->getContext()))
			->name(self::ROUTE);
	}
}
