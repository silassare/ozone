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

namespace OZONE\Tests\Integration\Forms;

use OZONE\Tests\Integration\Support\OZTestProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Integration tests for form discovery and step-by-step (partial upload) endpoints
 * exposed by {@see \OZONE\Core\Forms\Services\FormsService}.
 *
 * Three named forms are registered via a test route provider written into the
 * generated project:
 *
 *  - `test:contact`   — two required fields, no steps. Covers GET /forms/:key
 *                       and root-step POST /forms/:key/step.
 *  - `test:wizard`    — one required root field + one static step.  Covers
 *                       GET /forms/:key/step and POST /forms/:key/step/:step_name.
 *  - `test:evaluated` — one optional field + one server-only expect rule.
 *                       Covers POST /forms/:key/evaluate.
 *
 * The tests spin up PHP's built-in server against `public/api/` for the
 * duration of the class and tear it down afterwards.
 *
 * @internal
 *
 * @coversNothing
 */
final class FormsDiscoveryTest extends TestCase
{
	private static OZTestProject $proj;

	private static Process $server;

	private static string $base_url;

	// Route provider class name derived from project namespace.
	private const PROVIDER_CLASS = 'FormsDiscoveryTest\\TestRouteProvider';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$name = 'forms-discovery-test';
		$proj = OZTestProject::create($name);
		self::$proj = $proj;

		// -- DB: use a file-based SQLite so each request shares the same data. --
		$db_path = $proj->getPath() . \DIRECTORY_SEPARATOR . 'data' . \DIRECTORY_SEPARATOR . 'oz_test.db';
		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $db_path,
			'OZ_DB_NAME'  => 'oz_test',
			'OZ_DB_USER'  => '',
			'OZ_DB_PASS'  => '',
		]);

		// -- ORM + migrations ---------------------------------------------------
		// oz db build may exit non-zero on PHP 8.4 due to a readline_add_history() warning
		// inside symfony/process even when the actual build succeeds.  Accept a non-zero exit
		// code as long as the success marker is present in the output.
		// --build-all also generates the OZone core DB classes required for migrations.
		// --class-only skips the auto-migration-create step so we control it below.
		$build = $proj->oz('db', 'build', '--build-all', '--class-only');
		$build->run();

		if (0 !== $build->getExitCode() && !\str_contains($build->getOutput(), 'database classes generated')) {
			throw new \RuntimeException('oz db build failed: ' . $build->getErrorOutput());
		}

		// Ensure the data directory exists before migrations attempt to create the SQLite file.
		$data_dir = $proj->getPath() . \DIRECTORY_SEPARATOR . 'data';

		if (!\is_dir($data_dir)) {
			\mkdir($data_dir, 0o775, true);
		}

		$proj->oz('migrations', 'create', '--label=init')->mustRun();
		// --skip-backup prevents the MySQL-only db-backup step from running on SQLite.
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		// -- Write test route provider into the project -------------------------
		$proj->writeFile('app/TestRouteProvider.php', self::routeProviderSource());

		// -- Register it in the api scope's route map ---------------------------
		$proj->setSetting('oz.routes.api', self::PROVIDER_CLASS, true);

		// -- Find a free TCP port and start PHP built-in server ----------------
		$port          = self::findFreePort();
		self::$base_url = "http://127.0.0.1:{$port}";

		$ozone_root   = \dirname(__DIR__, 3);
		$server_router = $ozone_root . \DIRECTORY_SEPARATOR . 'oz' . \DIRECTORY_SEPARATOR . 'server.php';
		$public_dir    = $proj->getPath() . \DIRECTORY_SEPARATOR . 'public' . \DIRECTORY_SEPARATOR . 'api';

		self::$server = new Process(
			[\PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $public_dir, $server_router],
			$proj->getPath(),
		);
		self::$server->start();

		// Wait up to 5 s for the server to become responsive.
		self::waitForServer(self::$base_url, 5);
	}

	public static function tearDownAfterClass(): void
	{
		if (isset(self::$server) && self::$server->isRunning()) {
			self::$server->stop(3);
		}
		if (isset(self::$proj)) {
			self::$proj->destroy();
		}
		parent::tearDownAfterClass();
	}

	// -----------------------------------------------------------------------
	// GET /forms/:key  — discovery
	// -----------------------------------------------------------------------

	public function testGetFormReturns200ForRegisteredKey(): void
	{
		$resp = self::httpGet('/forms/test:contact');

		self::assertSame(200, $resp['status']);
		self::assertSame(0, $resp['body']['error']);
	}

	public function testGetFormReturns404ForUnknownKey(): void
	{
		$resp = self::httpGet('/forms/unknown:form:key');

		self::assertSame(404, $resp['status']);
	}

	public function testGetFormResponseBodyContainsFormKey(): void
	{
		$resp = self::httpGet('/forms/test:contact');
		$form = $resp['body']['data']['form'] ?? null;

		self::assertIsArray($form);
		self::assertSame('test:contact', $form['key']);
	}

	public function testGetFormResponseContainsExpectedFields(): void
	{
		$resp   = self::httpGet('/forms/test:contact');
		$form   = $resp['body']['data']['form'];
		$fields = $form['fields'] ?? [];

		$field_refs = \array_column($fields, 'ref');

		self::assertContains('name', $field_refs);
		self::assertContains('email', $field_refs);
	}

	public function testGetFormResponseContainsVersion(): void
	{
		$resp = self::httpGet('/forms/test:contact');
		$form = $resp['body']['data']['form'];

		self::assertArrayHasKey('version', $form);
		self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $form['version']);
	}

	public function testGetWizardFormResponseContainsSteps(): void
	{
		$resp  = self::httpGet('/forms/test:wizard');
		$form  = $resp['body']['data']['form'];
		$steps = $form['steps'] ?? [];

		// Steps are keyed by step name (associative array), not indexed.
		self::assertCount(1, $steps);
		self::assertArrayHasKey('info', $steps);
		self::assertSame('info', $steps['info']['name'] ?? null);
	}

	public function testGetFormDoesNotExposeAutoKeyedForm(): void
	{
		// Auto-keyed forms (oz:form:auto:N) must NOT be discoverable;
		// only forms with an explicit key() call are registered.
		$resp = self::httpGet('/forms/oz:form:auto:1');

		self::assertSame(404, $resp['status']);
	}

	// -----------------------------------------------------------------------
	// POST /forms/:key/step  — root-step validation
	// -----------------------------------------------------------------------

	public function testRootStepReturns200WithAllRequiredFields(): void
	{
		$resp = self::httpPost('/forms/test:contact/step', [
			'name'  => 'Alice',
			'email' => 'alice@example.com',
		]);

		self::assertSame(200, $resp['status']);
		self::assertSame(0, $resp['body']['error']);
	}

	public function testRootStepReturnsDoneTrueWhenNoStepsExist(): void
	{
		// test:contact has no steps -> first step call returns done:true immediately.
		$resp = self::httpPost('/forms/test:contact/step', [
			'name'  => 'Alice',
			'email' => 'alice@example.com',
		]);

		self::assertSame(true, $resp['body']['data']['done'] ?? null);
		// PHP's ?? returns the right side for null, so check key existence explicitly.
		self::assertArrayHasKey('next_step_name', $resp['body']['data']);
		self::assertNull($resp['body']['data']['next_step_name']);
	}

	public function testRootStepReturns400WhenRequiredFieldMissing(): void
	{
		// InvalidFormException maps to HTTP 400 (BaseException::INVALID_FORM -> 400).
		$resp = self::httpPost('/forms/test:contact/step', []);

		self::assertSame(400, $resp['status']);
	}

	public function testRootStepReturns404ForUnknownForm(): void
	{
		$resp = self::httpPost('/forms/unknown:form/step', []);

		self::assertSame(404, $resp['status']);
	}

	public function testRootStepReturnsNextStepWhenWizardHasSteps(): void
	{
		// test:wizard root field: type (required). After validating root, the
		// first step (info) should be returned as the next step.
		$resp = self::httpPost('/forms/test:wizard/step', ['type' => 'advanced']);

		self::assertSame(200, $resp['status']);
		self::assertSame(false, $resp['body']['data']['done'] ?? true);
		self::assertSame('info', $resp['body']['data']['next_step_name'] ?? null);
		self::assertIsArray($resp['body']['data']['next_step'] ?? null);
	}

	// -----------------------------------------------------------------------
	// POST /forms/:key/step/:step_name  — named step validation
	// -----------------------------------------------------------------------

	public function testNamedStepReturnsDoneTrueAfterLastStep(): void
	{
		// Validate root + step "info" -> no more steps -> done: true.
		$resp = self::httpPost('/forms/test:wizard/step/info', [
			'type'    => 'advanced',
			'details' => 'some details',
		]);

		self::assertSame(200, $resp['status']);
		self::assertSame(true, $resp['body']['data']['done'] ?? false);
	}

	public function testNamedStepReturns400WhenStepFieldMissing(): void
	{
		// 'details' is required in the 'info' step but absent.
		// InvalidFormException maps to HTTP 400 (BaseException::INVALID_FORM -> 400).
		$resp = self::httpPost('/forms/test:wizard/step/info', ['type' => 'advanced']);

		self::assertSame(400, $resp['status']);
	}

	public function testNamedStepReturns404ForUnknownStep(): void
	{
		$resp = self::httpPost('/forms/test:wizard/step/nonexistent', [
			'type'    => 'advanced',
			'details' => 'x',
		]);

		self::assertSame(404, $resp['status']);
	}

	// -----------------------------------------------------------------------
	// POST /forms/:key/evaluate  — server-only rule evaluation
	// -----------------------------------------------------------------------

	public function testEvaluateReturnsPassedTrueWhenRuleSatisfied(): void
	{
		// test:evaluated has a server-only rule: plan must equal 'enterprise'.
		$resp = self::httpPost('/forms/test:evaluated/evaluate', ['plan' => 'enterprise']);

		self::assertSame(200, $resp['status']);
		$results = $resp['body']['data']['results'] ?? [];
		self::assertCount(1, $results);
		self::assertTrue($results[0]['passed']);
		self::assertNull($results[0]['message']);
	}

	public function testEvaluateReturnsPassedFalseWhenRuleViolated(): void
	{
		$resp = self::httpPost('/forms/test:evaluated/evaluate', ['plan' => 'basic']);

		self::assertSame(200, $resp['status']);
		$results = $resp['body']['data']['results'] ?? [];
		self::assertCount(1, $results);
		self::assertFalse($results[0]['passed']);
		self::assertSame('PLAN_REQUIRED', $results[0]['message']);
	}

	public function testEvaluateReturns404ForUnknownForm(): void
	{
		$resp = self::httpPost('/forms/unknown:form/evaluate', []);

		self::assertSame(404, $resp['status']);
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Makes an HTTP GET request and returns ['status' => int, 'body' => array].
	 *
	 * @return array{status: int, body: array}
	 */
	private static function httpGet(string $path): array
	{
		return self::request('GET', $path, null);
	}

	/**
	 * Makes an HTTP POST request with a JSON body and returns ['status' => int, 'body' => array].
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array{status: int, body: array}
	 */
	private static function httpPost(string $path, array $data): array
	{
		return self::request('POST', $path, $data);
	}

	/**
	 * Performs an HTTP request using PHP stream context.
	 *
	 * @param string               $method
	 * @param string               $path
	 * @param null|array<string,mixed> $data JSON payload (POST only)
	 *
	 * @return array{status: int, body: array}
	 */
	private static function request(string $method, string $path, ?array $data): array
	{
		$url  = self::$base_url . $path;
		$opts = [
			'http' => [
				'method'        => $method,
				'ignore_errors' => true,  // return body even on 4xx/5xx
				'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
			],
		];

		if (null !== $data) {
			$opts['http']['content'] = \json_encode($data, \JSON_THROW_ON_ERROR);
		}

		$ctx      = \stream_context_create($opts);
		$raw      = \file_get_contents($url, false, $ctx);
		$raw      = false === $raw ? '{}' : $raw;
		$body     = \json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

		// $http_response_header is populated by file_get_contents with an HTTP context.
		$status = 200;
		if (!empty($http_response_header[0])) {
			\preg_match('~HTTP/\S+\s+(\d+)~', $http_response_header[0], $m);
			$status = isset($m[1]) ? (int) $m[1] : 200;
		}

		return ['status' => $status, 'body' => $body];
	}

	/**
	 * Polls the server until it responds or the timeout elapses.
	 *
	 * @param string $base_url
	 * @param int    $timeout_seconds
	 */
	private static function waitForServer(string $base_url, int $timeout_seconds): void
	{
		$deadline = \time() + $timeout_seconds;

		while (\time() < $deadline) {
			\usleep(100_000); // 100 ms

			$ctx = \stream_context_create([
				'http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 1],
			]);

			// Probe any path — we just need a TCP connection to succeed.
			$result = @\file_get_contents($base_url . '/', false, $ctx);

			if (false !== $result) {
				return;
			}
		}

		throw new \RuntimeException(
			\sprintf('PHP built-in server at %s did not become ready within %d seconds.', $base_url, $timeout_seconds)
		);
	}

	/**
	 * Returns a free TCP port by binding to port 0.
	 */
	private static function findFreePort(): int
	{
		$sock = \stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

		if (false === $sock) {
			throw new \RuntimeException("Cannot find free port: {$errstr} ({$errno})");
		}

		$name = \stream_socket_get_name($sock, false);
		\fclose($sock);

		$port = (int) \substr($name, \strrpos($name, ':') + 1);

		if ($port <= 0) {
			throw new \RuntimeException('Could not determine free port from socket name: ' . $name);
		}

		return $port;
	}

	/**
	 * Returns the PHP source for the test route provider written into the project.
	 *
	 * Contains three named forms:
	 *  - test:contact   (two required fields, no steps)
	 *  - test:wizard    (one required root field + one static step)
	 *  - test:evaluated (one optional field + one server-only expect rule)
	 */
	private static function routeProviderSource(): string
	{
		// The project namespace is derived from the project name in OZTestProject::create().
		// 'forms-discovery-test' -> 'FormsDiscoveryTest'
		return <<<'PHP'
<?php

declare(strict_types=1);

namespace FormsDiscoveryTest;

use OZONE\Core\App\Service;
use OZONE\Core\Forms\DynamicValue;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Interfaces\RouteProviderInterface;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Minimal concrete Service subclass for tests — no extra logic needed.
 */
final class TestFormService extends Service {}

/**
 * Registers three named forms used by FormsDiscoveryTest.
 */
final class TestRouteProvider implements RouteProviderInterface
{
	public static function registerRoutes(Router $router): void
	{
		// --- Form 1: simple contact form (discovery + root step) ---
		$contact = (new Form())->key('test:contact');
		$contact->field('name')->required(true);
		$contact->field('email')->required(true);

		$router->post('/contact', static function (RouteInfo $ri): Response {
			return (new TestFormService($ri))->respond();
		})->form($contact);

		// --- Form 2: wizard with one static step ---
		$wizard = (new Form())->key('test:wizard');
		$wizard->field('type')->required(true);

		$step_form = new Form();
		$step_form->field('details')->required(true);
		$wizard->step('info', $step_form);

		$router->post('/wizard', static function (RouteInfo $ri): Response {
			return (new TestFormService($ri))->respond();
		})->form($wizard);

		// --- Form 3: form with a server-only expect rule ---
		$evaluated = (new Form())->key('test:evaluated');
		$evaluated->field('plan');
		$evaluated->expect()->eq('plan', new DynamicValue(
			static fn (FormData $fd) => 'enterprise'
		), 'PLAN_REQUIRED');

		$router->post('/evaluated', static function (RouteInfo $ri): Response {
			return (new TestFormService($ri))->respond();
		})->form($evaluated);
	}
}
PHP;
	}
}
