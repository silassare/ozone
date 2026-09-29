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

namespace OZONE\Core\Router;

use OZONE\Core\OZone;

/**
 * Class RouteExport.
 *
 * What a client needs to call a router's routes by name: their methods, path and parameters, whether
 * they declare a form (discovered at runtime, since a form may depend on the request) and a
 * resumable one, and their guards. A client builds requests from it, and a build checks the route
 * names a definition uses and types them.
 *
 * Only explicitly named routes: an auto name follows the path, so no client may rely on it. Internal
 * routes (sub-requests only) are left out.
 *
 * @psalm-type ExportedRoute = array{
 *     name: string,
 *     methods: list<string>,
 *     path: string,
 *     params: list<array{name: string, pattern: string}>,
 *     form: bool,
 *     resumable: bool,
 *     guards: list<array{type: string, ...}>,
 *     opaque_guards: int,
 * }
 */
final class RouteExport
{
	/**
	 * The routes of a router, by name.
	 *
	 * `guards` describes the guards set with a shortcut (`withAuthenticatedUser()`, ...);
	 * `opaque_guards` counts the others (a closure, a provider), which only the server can decide.
	 *
	 * @return list<ExportedRoute>
	 */
	public static function of(Router $router): array
	{
		$out = [];

		foreach ($router->getRoutes() as $route) {
			$options = $route->getOptions();

			if (!$options->isNameExplicit() || OZone::isInternalPath($route->getPath())) {
				continue;
			}

			$resolved = $options->resolved();
			$declared = $route->getDeclaredParams();
			$params   = [];

			foreach ($route->getPathParams() as $name) {
				$params[] = ['name' => $name, 'pattern' => $declared[$name] ?? Route::DEFAULT_PARAM_PATTERN];
			}

			$out[] = [
				'name'          => $route->getName(),
				'methods'       => \array_values($route->getMethods()),
				'path'          => $route->getPath(),
				'params'        => $params,
				'form'          => null !== $resolved->formDeclaration(),
				'resumable'     => $resolved->hasResumeSupport(),
				'guards'        => $resolved->guard_descriptors,
				'opaque_guards' => \max(0, \count($resolved->guard_entries) - \count($resolved->guard_descriptors)),
			];
		}

		\usort($out, static fn (array $a, array $b): int => \strcmp($a['name'], $b['name']));

		return $out;
	}
}
