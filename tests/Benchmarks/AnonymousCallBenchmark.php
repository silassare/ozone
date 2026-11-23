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

namespace OZONE\Tests\Benchmarks;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Response;
use OZONE\Core\OZone;
use OZONE\Core\Scopes\ScopeKind;
use OZONE\Tests\Support\AnonymousCallRoutes;

/**
 * Benchmarks for a route called as nobody (`Context::callAnonymously()`) and the scope switch under
 * it (`OZone::inScope()`), warm: what a job or a page pays per call once the scope's settings and
 * routers are built, next to what a sub-request (`callRoute()`) pays for the same route.
 *
 * Each call is made by a request of its own, as a page or a job makes it: a sub-request records its
 * route on the request that made it, and one request making every call of a run would measure a
 * trail growing for the length of the run.
 */
class AnonymousCallBenchmark implements BenchmarkSuiteInterface
{
	private const string SCOPE = 'bench-call';

	#[Override]
	public static function callables(): array
	{
		self::makeScope();

		$scope   = app()->getScope(self::SCOPE);
		$env     = HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
		$request = static function (callable $call) use ($env): Response {
			$tree = Context::detachTree();

			try {
				return $call(new Context($env));
			} finally {
				Context::restoreTree($tree);
			}
		};

		return [
			'scope_switch_enter_leave'        => static fn (): bool => OZone::inScope(
				$scope,
				static fn (): bool => true
			),
			'sub_request_call_route'          => static fn (): Response => $request(
				static fn (Context $context): Response => $context->callRoute('oz:robots', [], [], false)
			),
			'anonymous_call_same_scope_warm'  => static fn (): Response => $request(
				static fn (Context $context): Response => $context->callAnonymously('oz:robots')
			),
			'anonymous_call_cross_scope_warm' => static fn (): Response => $request(
				static fn (Context $context): Response => $context->callAnonymously('anon:where', [], [], self::SCOPE)
			),
		];
	}

	/**
	 * A scope of the sandbox serving the routes the suite calls as nobody.
	 */
	private static function makeScope(): void
	{
		$project = app()->getProjectDir()->getRoot();
		$dir     = \rtrim($project, '/\\') . '/scopes/' . self::SCOPE . '/settings';

		if (!\is_dir($dir)) {
			\mkdir($dir, 0o775, true);
		}

		ScopeKind::API->write($project, self::SCOPE);

		\file_put_contents(
			$dir . '/oz.routes.api.php',
			"<?php\n\nreturn [\\" . AnonymousCallRoutes::class . "::class => true];\n"
		);
		\file_put_contents(
			$dir . '/' . AnonymousCallRoutes::PROBE_GROUP . '.php',
			"<?php\n\nreturn ['WHERE' => 'scope'];\n"
		);
	}
}
