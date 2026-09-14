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

namespace OZONE\Tests\Support\Servers;

use Gobl\ORM\ORMOptions;
use OZONE\Core\Db\OZCountriesQuery;
use OZONE\Core\Db\OZCountry;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\UploadedFile;
use OZONE\Core\Router\Interfaces\RouteProviderInterface;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Runtime\Runtime;
use OZONE\Core\Runtime\WorkerRuntime;
use RuntimeException;

/**
 * Class RuntimeProbe.
 *
 * The routes the worker servers of tests/Runtime/Servers/ answer, each one exercising something a
 * server bridge has to carry across: the request as the client sent it, every response header
 * value, a body too large for one frame, and the ways a request can end.
 *
 * @internal
 */
final class RuntimeProbe implements RouteProviderInterface
{
	/**
	 * Requests this process has answered on `/info`: above one, the process outlived a request.
	 */
	private static int $served = 0;

	public static function registerRoutes(Router $router): void
	{
		$router->group('/runtime-probe', static function () use ($router): void {
			$router->get('/ping', static fn (RouteInfo $ri): Response => $ri->getContext()->getResponse()
				->withHeader('Content-Type', 'text/plain')
				->withBody(Body::fromString('pong')));

			// The JSON route `make benchmark-http` measures, next to Laravel's and Symfony's.
			$router->get('/json', static fn (RouteInfo $ri): Response => $ri->getContext()->getResponse()
				->withJson(['hello' => 'world']));

			// The database route `make benchmark-http` measures: one row read by its primary key through
			// the ORM (docker/bench/ozone/seed.php writes it), as Laravel and Symfony read theirs.
			$router->get('/db', static function (RouteInfo $ri): Response {
				$country  = (new OZCountriesQuery())->whereCc2Is('BJ')
					->find(ORMOptions::makePaginated(1))
					->fetchClass();
				$response = $ri->getContext()->getResponse();

				if (!$country instanceof OZCountry) {
					return $response->withStatus(404);
				}

				return $response->withJson([
					'cc2'          => $country->getCc2(),
					'name'         => $country->getName(),
					'calling_code' => $country->getCallingCode(),
				]);
			});

			$router->get('/info', static function (RouteInfo $ri): Response {
				$runtime = Runtime::current();

				return $ri->getContext()->getResponse()->withJson([
					'runtime'    => $runtime::getName(),
					'loop'       => $runtime instanceof WorkerRuntime ? $runtime->getLoopName() : null,
					'persistent' => $runtime->isPersistent(),
					'console'    => $runtime->isConsole(),
					'pid'        => \getmypid(),
					'served'     => ++self::$served,
				]);
			});

			// `respond(): never` is a published contract: respond, then fall through to a line
			// written to be unreachable. The worker has to unwind through it and keep serving.
			$router->get('/respond-mid', static function (RouteInfo $ri): Response {
				$ri->getContext()->respond($ri->getContext()->getResponse()->withStatus(204));

				throw new RuntimeException('unreachable: respond() is : never');
			});

			// Writes to the session, so the session adds its own `Set-Cookie` lines (session ID and XSRF
			// token) on the way out, next to the handler's.
			$router->get('/headers', static function (RouteInfo $ri): Response {
				$ri->getContext()->requireAuthStore()->set('runtime_probe', true);

				return $ri->getContext()->getResponse()
					->withHeader('X-OZ-Probe', 'yes')
					->withAddedHeader('X-OZ-Multi', 'a')
					->withAddedHeader('X-OZ-Multi', 'b')
					->withAddedHeader('Set-Cookie', 'oz_probe_a=1; Path=/')
					->withAddedHeader('Set-Cookie', 'oz_probe_b=2; Path=/')
					->withJson(['ok' => true]);
			});

			$router->post('/echo', static function (RouteInfo $ri): Response {
				$context = $ri->getContext();
				$request = $context->getRequest();

				return $context->getResponse()->withJson([
					'method'       => $request->getMethod(),
					'query'        => $request->getQueryParams(),
					'parsed'       => $request->getParsedBody(),
					'header'       => $request->getHeaderLine('X-OZ-Echo'),
					'content_type' => $request->getHeaderLine('Content-Type'),
					'client_ip'    => $context->getUserIP(),
					'host'         => $context->getHost(),
				]);
			});

			$router->post('/upload', static function (RouteInfo $ri): Response {
				$request = $ri->getContext()->getRequest();
				$files   = [];

				foreach ($request->getUploadedFiles() as $field => $file) {
					if ($file instanceof UploadedFile) {
						$files[$field] = [
							'name'  => $file->getClientFilename(),
							'size'  => $file->getSize(),
							'error' => $file->getError(),
							'sha1'  => \sha1((string) $file->getStream()),
						];
					}
				}

				return $ri->getContext()->getResponse()->withJson([
					'files'  => $files,
					'fields' => $request->getParsedBody(),
				]);
			});

			$router->get('/large', static function (RouteInfo $ri): Response {
				$size = (int) $ri->getContext()->getRequest()->getQueryParam('size', 0);

				return $ri->getContext()->getResponse()
					->withHeader('Content-Type', 'application/octet-stream')
					->withBody(Body::fromString(self::pattern($size)));
			});

			// Output written around the Response object is refused by the router; under RoadRunner it
			// would also be written to the protocol pipe, if anything let it through.
			$router->get('/stray-output', static function (RouteInfo $ri): Response {
				echo 'stray-bytes';

				return $ri->getContext()->getResponse()->withJson(['ok' => true]);
			});
		});
	}

	/**
	 * The body `/large` answers: `$size` bytes the client can rebuild and compare.
	 */
	public static function pattern(int $size): string
	{
		return \substr(\str_repeat('0123456789abcdef', \intdiv($size, 16) + 1), 0, \max(0, $size));
	}
}
