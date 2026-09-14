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
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Http\HttpWorkerInterface;
use Spiral\RoadRunner\Http\Request as RoadRunnerRequest;
use Spiral\RoadRunner\Worker;
use Throwable;

/**
 * Class RoadRunnerBridge.
 *
 * Serves OZone from a RoadRunner HTTP worker. Needs `spiral/roadrunner-http`:
 *
 * ```php
 * // worker.php, the `server.command` of .rr.yaml, after the same set-up as the scope's index.php
 * RoadRunnerBridge::serve($app);
 * ```
 *
 * RoadRunner hands the worker each request as an object and reads the response from the worker's
 * protocol pipe -- which is the process output, so nothing may be written to it. The bridge builds
 * OZone's `Request` from RoadRunner's and is the request's response sink: the finished `Response`
 * is sent through the worker, streamed when it is large.
 */
final class RoadRunnerBridge implements ResponseSinkInterface
{
	/**
	 * Bodies up to this size are sent in one frame; larger ones are streamed in chunks.
	 */
	private const STREAM_THRESHOLD = 1048576;

	private const CHUNK_SIZE = 65536;

	public function __construct(private readonly HttpWorkerInterface $worker) {}

	/**
	 * Bootstraps once, then serves requests until RoadRunner stops the worker.
	 *
	 * @param AppInterface             $app    the application
	 * @param null|HttpWorkerInterface $worker the worker; one on RoadRunner's relay by default
	 */
	public static function serve(AppInterface $app, ?HttpWorkerInterface $worker = null): void
	{
		Runtime::set(new WorkerRuntime('roadrunner'));

		$bridge = new self($worker ?? new HttpWorker(Worker::create()));

		OZone::bootstrap($app);

		while (true) {
			try {
				$request = $bridge->worker->waitRequest();
			} catch (Throwable $t) {
				// A payload that could not be decoded: report it to RoadRunner, keep the worker.
				$bridge->worker->getWorker()->error((string) $t);

				continue;
			}

			if (null === $request) {
				// RoadRunner asked the worker to stop.
				break;
			}

			OZone::handleRequest(self::toRequest($request), $bridge);
		}
	}

	/**
	 * Builds OZone's request from RoadRunner's.
	 *
	 * `REMOTE_ADDR` is RoadRunner's TCP peer, not its `ipAddress` attribute: OZone resolves
	 * forwarding headers itself, from the proxies listed in `oz.proxies`.
	 */
	public static function toRequest(RoadRunnerRequest $request): Request
	{
		$env = HTTPEnvironment::fromParts($request->method, $request->uri, $request->headers, [
			'REMOTE_ADDR'     => $request->remoteAddr,
			'SERVER_PROTOCOL' => $request->protocol,
		]);

		// A form RoadRunner parsed arrives as its fields, JSON-encoded in place of the raw body.
		$parsed = $request->getParsedBody();

		return Request::createFromHTTPEnvironment(
			$env,
			Body::fromString(null === $parsed ? $request->body : ''),
			$parsed,
			self::uploads($request->uploads)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function send(Response $response): void
	{
		$status  = $response->getStatusCode();
		$headers = $response->getHeaders();
		$size    = $response->getBody()->getSize();

		if (null !== $size && $size <= self::STREAM_THRESHOLD) {
			$body = '';

			foreach (ResponseEmitter::bodyChunks($response, self::CHUNK_SIZE) as $chunk) {
				$body .= $chunk;
			}

			$this->worker->respond($status, $body, $headers);

			return;
		}

		$this->worker->respond($status, ResponseEmitter::bodyChunks($response, self::CHUNK_SIZE), $headers);
	}

	/**
	 * RoadRunner's upload tree, as OZone's.
	 *
	 * Its leaves are `{name, mime, size, error, tmpName}`, in RoadRunner's own temporary directory:
	 * files PHP did not receive, so they are moved with `rename()`, never `move_uploaded_file()`.
	 */
	private static function uploads(array $tree): array
	{
		$files = [];

		foreach ($tree as $key => $item) {
			if (!\is_array($item)) {
				continue;
			}

			if (\array_key_exists('tmpName', $item) && \array_key_exists('error', $item)) {
				$files[$key] = new UploadedFile(
					(string) $item['tmpName'],
					isset($item['name']) ? (string) $item['name'] : null,
					isset($item['mime']) ? (string) $item['mime'] : null,
					isset($item['size']) ? (int) $item['size'] : null,
					(int) $item['error'],
					false
				);
			} else {
				$files[$key] = self::uploads($item);
			}
		}

		return $files;
	}
}
