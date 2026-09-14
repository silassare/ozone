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

/**
 * A worker loop, run as its own process by {@see WorkerLoopTest}.
 *
 * It has the shape of a FrankenPHP or RoadRunner entry script -- bootstrap once, then one
 * `OZone::handleRequest()` per request -- and it runs outside PHPUnit on purpose: the framework
 * clears output buffers down to level 1 before writing an error response, which is correct (level 1
 * is the buffer a worker bridge owns) but makes the emitted bytes unobservable from inside a test.
 *
 * Every request writes one line to STDERR, so the test can see that each was served and that the
 * process reached the end of the loop. Reaching it at all is the claim: under the classic runtime
 * `Context::finish()` exits, so the first response is also the last.
 */

use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Hooks\Events\FinishHook;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Request;
use OZONE\Core\Http\Response;
use OZONE\Core\OZone;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Runtime\Interfaces\ResponseSinkInterface;
use OZONE\Core\Runtime\Runtime;
use OZONE\Core\Runtime\WorkerRuntime;
use OZONE\Tests\Runtime\WorkerLoopTest;

require __DIR__ . '/../../vendor/autoload.php';

require __DIR__ . '/../autoload.php';

Runtime::set(new WorkerRuntime('test-loop'));

\fwrite(\STDERR, \sprintf(
	"runtime=%s persistent=%s console=%s\n",
	Runtime::current()::getName(),
	Runtime::isPersistent() ? 'yes' : 'no',
	Runtime::isConsole() ? 'yes' : 'no',
));

$pid    = \getmypid();
$router = OZone::getApiRouter();

// The contract `respond(): never` publishes: respond, then fall through to a line written to be
// unreachable. Application handlers, guards and views do this as legitimately as the framework, so a
// worker has to unwind through it -- and has to leave no output buffer behind when it does.
$router->get('/responds-mid-handler', static function (RouteInfo $ri): void {
	$ri->getContext()->respond($ri->getContext()->getResponse()->withStatus(204));

	throw new RuntimeException('unreachable: respond() is : never');
})->name('worker-loop:mid');

// A mix on purpose: a route that answers, and one that fails. A failure used to be permanent --
// `BaseException::$just_die` is set by the first error a process reports and was never unset.
$paths = ['/', '/no-such-route', '/responds-mid-handler', '/no-such-route', '/responds-mid-handler'];

foreach ($paths as $index => $path) {
	// Measured before this script opens anything: if the framework leaves an output buffer open per
	// request, this is where it shows, growing without bound in a process that never exits.
	$level_before = \ob_get_level();

	\ob_start();

	OZone::handleRequest(HTTPEnvironment::mock([
		'REQUEST_METHOD' => 'GET',
		'REQUEST_URI'    => $path,
	]));

	// Measured before any cleanup of ours, which would hide exactly what is being looked for. One
	// buffer above where this iteration started is this script's own; anything more the request left
	// behind, and in a process that never exits that grows without bound.
	$leaked = \ob_get_level() - $level_before - 1;

	// The framework closes buffers down to level 1 before writing an error response (level 1 is the
	// buffer a worker bridge owns, and is left alone by design), so what the request wrote is
	// wherever it ended up. Collect everything above where this iteration started, then put the
	// level back so the next iteration measures the framework and not this loop.
	$body = '';

	while (\ob_get_level() > $level_before) {
		$body = (string) \ob_get_clean() . $body;
	}

	while (\ob_get_level() < $level_before) {
		\ob_start();
	}

	\fwrite(\STDERR, \sprintf(
		"served %d path=%s pid=%d bytes=%d leaked_ob=%d same_router=%s\n",
		$index,
		$path,
		(int) $pid,
		\strlen($body),
		$leaked,
		OZone::getApiRouter() === $router ? 'yes' : 'no',
	));
}

// The same loop with a response sink, the way RoadRunner and Swoole are served: the Response object is
// handed over instead of being written, and FinishHook still runs after it was sent.
$sink = new class implements ResponseSinkInterface {
	/** @var list<Response> */
	public array $sent = [];

	/** @var list<string> */
	public array $events = [];

	public function send(Response $response): void
	{
		$this->sent[]   = $response;
		$this->events[] = 'send';
	}
};

FinishHook::listen(static function () use ($sink): void {
	$sink->events[] = 'finish';
});

$router->get('/critical', static function (): never {
	// The last resort, used when reporting an error failed: it must not bypass the sink either.
	BaseException::dieWithAnUnhandledErrorOccurred();
})->name('worker-loop:critical');

$router->post('/echo', static fn (RouteInfo $ri) => $ri->getContext()->getResponse()->withJson([
	'parsed'    => $ri->getContext()->getRequest()->getParsedBody(),
	'client_ip' => $ri->getContext()->getUserIP(),
]))->name('worker-loop:echo');

$get = static fn (string $path): HTTPEnvironment => HTTPEnvironment::mock([
	'REQUEST_METHOD' => 'GET',
	'REQUEST_URI'    => $path,
]);

$requests = [
	'/'                     => $get('/'),
	'/no-such-route'        => $get('/no-such-route'),
	'/responds-mid-handler' => $get('/responds-mid-handler'),
	'/critical'             => $get('/critical'),
	// A request built from parts, as a bridge builds it: PHP never saw this body.
	'/echo'                 => Request::createFromHTTPEnvironment(
		HTTPEnvironment::fromParts('POST', 'http://worker.test/echo', ['Content-Type' => 'application/json'], [
			'REMOTE_ADDR' => '10.1.2.3',
		]),
		Body::fromString('{"a":1}')
	),
];

$index = 0;

foreach ($requests as $path => $request) {
	$sink->events = [];
	$sink->sent   = [];

	\ob_start();

	OZone::handleRequest($request, $sink);

	$output = '';

	while (\ob_get_level() > 0) {
		$output = (string) \ob_get_clean() . $output;
	}

	\ob_start();

	$response = $sink->sent[0] ?? null;

	\fwrite(\STDERR, \sprintf(
		"sink %d path=%s status=%d sent=%d output=%d events=%s body=%s\n",
		$index++,
		$path,
		null === $response ? 0 : $response->getStatusCode(),
		\count($sink->sent),
		\strlen($output),
		\implode(',', $sink->events) ?: '-',
		null === $response ? '' : \str_replace("\n", ' ', \substr((string) $response->getBody(), 0, 4000)),
	));
}

\fwrite(\STDERR, \sprintf("loop ended pid=%d\n", (int) $pid));
