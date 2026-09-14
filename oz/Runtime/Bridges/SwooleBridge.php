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

namespace OZONE\Core\Runtime\Bridges;

use Override;
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Request;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\ResponseEmitter;
use OZONE\Core\Http\UploadedFile;
use OZONE\Core\OZone;
use OZONE\Core\Runtime\Interfaces\ResponseSinkInterface;
use OZONE\Core\Runtime\Runtime;
use OZONE\Core\Runtime\WorkerRuntime;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server;

/**
 * Class SwooleBridge.
 *
 * Serves OZone from a Swoole (or OpenSwoole) HTTP server. Needs `ext-swoole`:
 *
 * ```php
 * // server.php, after the same set-up as the scope's index.php
 * $server = new Swoole\Http\Server('0.0.0.0', 8080);
 * $server->set(['worker_num' => 4]);
 *
 * SwooleBridge::attach($server, $app);
 *
 * $server->start();
 * ```
 *
 * Each Swoole worker process bootstraps OZone once, when it starts -- never the master before the
 * fork, which would share one database connection between every worker. Requests are handed over
 * as objects; the bridge builds OZone's `Request` from Swoole's and is the request's response sink.
 *
 * OZone keeps one request per process (the context tree is process state), so `attach()` turns
 * coroutines off for request handlers: with them, a worker could interleave two requests.
 */
final class SwooleBridge implements ResponseSinkInterface
{
	/**
	 * Bodies up to this size are sent with one `end()`; larger ones are written in chunks.
	 */
	private const STREAM_THRESHOLD = 1048576;

	private const CHUNK_SIZE = 65536;

	/**
	 * Headers Swoole computes itself: sending them too would duplicate or contradict its own.
	 */
	private const SERVER_HEADERS = ['content-length' => true, 'transfer-encoding' => true, 'connection' => true];

	private function __construct(private readonly SwooleResponse $response) {}

	/**
	 * Registers OZone's handlers on the server: bootstrap per worker, then one request at a time.
	 *
	 * @param Server       $server the server, not started yet
	 * @param AppInterface $app    the application
	 */
	public static function attach(Server $server, AppInterface $app): void
	{
		$server->set(['enable_coroutine' => false]);

		$server->on('workerStart', static function () use ($app): void {
			Runtime::set(new WorkerRuntime('swoole'));

			OZone::bootstrap($app);
		});

		$server->on('request', static function (SwooleRequest $request, SwooleResponse $response): void {
			self::handle($request, $response);
		});
	}

	/**
	 * Serves one request.
	 */
	public static function handle(SwooleRequest $request, SwooleResponse $response): void
	{
		OZone::handleRequest(self::toRequest($request), new self($response));
	}

	/**
	 * Builds OZone's request from Swoole's.
	 */
	public static function toRequest(SwooleRequest $request): Request
	{
		$server = $request->server ?? [];
		$query  = (string) ($server['query_string'] ?? '');
		$uri    = (string) ($server['request_uri'] ?? '/') . ('' === $query ? '' : '?' . $query);

		$env = HTTPEnvironment::fromParts((string) ($server['request_method'] ?? 'GET'), $uri, $request->header ?? [], [
			'REMOTE_ADDR'        => $server['remote_addr'] ?? null,
			'REMOTE_PORT'        => $server['remote_port'] ?? null,
			'SERVER_PORT'        => $server['server_port'] ?? null,
			'SERVER_PROTOCOL'    => $server['server_protocol'] ?? null,
			'REQUEST_TIME'       => $server['request_time'] ?? null,
			'REQUEST_TIME_FLOAT' => $server['request_time_float'] ?? null,
		]);

		$raw = $request->rawContent();

		return Request::createFromHTTPEnvironment(
			$env,
			Body::fromString(\is_string($raw) ? $raw : ''),
			$request->post,
			// Swoole's temporary files were not registered by PHP's upload handling.
			UploadedFile::fromFilesArray($request->files ?? [], false)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function send(Response $response): void
	{
		$out = $this->response;

		$out->status($response->getStatusCode(), $response->getReasonPhrase());

		foreach ($response->getHeaders() as $name => $values) {
			if (!isset(self::SERVER_HEADERS[\strtolower((string) $name)])) {
				// An array is sent as one header line per value, which `Set-Cookie` needs.
				$out->header((string) $name, $values, false);
			}
		}

		$size = $response->getBody()->getSize();

		if (null !== $size && $size <= self::STREAM_THRESHOLD) {
			$body = '';

			foreach (ResponseEmitter::bodyChunks($response, self::CHUNK_SIZE) as $chunk) {
				$body .= $chunk;
			}

			$out->end($body);

			return;
		}

		foreach (ResponseEmitter::bodyChunks($response, self::CHUNK_SIZE) as $chunk) {
			if (!$out->write($chunk)) {
				// The client went away.
				break;
			}
		}

		$out->end();
	}
}
