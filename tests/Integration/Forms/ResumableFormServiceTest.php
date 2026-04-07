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
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Integration tests for ResumableFormService HTTP endpoints.
 *
 * A single shared project is created once (setUpBeforeClass) with:
 *  - A file-based SQLite DB so each server request shares persistent state.
 *  - DB schema installed (migrations run).
 *  - TestFormProvider and TestFormRealContextProvider injected into oz.forms.providers.
 *  - The PHP built-in server running for the 'api' scope.
 *
 * TestFormProvider uses RequestScope::HOST so no session-cookie management
 * is needed -- all requests from the same host share the same scope_id.
 *
 * @internal
 *
 * @coversNothing
 */
final class ResumableFormServiceTest extends TestCase
{
	private static OZTestProject $proj;

	/** @var null|Process Background oz project serve process */
	private static ?Process $server = null;

	private static string $host = '127.0.0.1';
	private static int $port    = 0;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj = OZTestProject::create('resumable-form', fresh: true);

		$db_path = $proj->getPath() . '/resumable_form_test.sqlite';
		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $db_path,
		]);

		$ns = $proj->getNamespace();

		// Inject provider stubs.
		$proj->writeFileFromStub('TestFormProvider', 'app/TestFormProvider.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('TestFormRealContextProvider', 'app/TestFormRealContextProvider.php', [
			'namespace' => $ns,
		]);

		// Register providers in oz.forms.providers.
		$proj->setSetting('oz.forms.providers', 'test-wizard', "{$ns}\\TestFormProvider");
		$proj->setSetting('oz.forms.providers', 'test-real-ctx', "{$ns}\\TestFormRealContextProvider");

		// Build ORM classes and install the schema.
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		try {
			[$server, $host, $port] = $proj->startServer('api', self::$host);
		} catch (RuntimeException $e) {
			$proj->destroy();
			self::markTestSkipped($e->getMessage());
		}

		self::$proj   = $proj;
		self::$server = $server;
		self::$port   = $port;
	}

	public static function tearDownAfterClass(): void
	{
		if (null !== self::$server && self::$server->isRunning()) {
			self::$server->stop(3);
		}
		if (isset(self::$proj)) {
			self::$proj->destroy();
		}
		parent::tearDownAfterClass();
	}

	// -------------------------------------------------------------------------
	// Error paths
	// -------------------------------------------------------------------------

	public function testUnknownProviderReturnsError(): void
	{
		[, $body] = $this->request('POST', '/form/does-not-exist/init');
		$data     = \json_decode($body, true);

		self::assertIsArray($data);
		self::assertSame(1, $data['error'], 'Unknown provider should return error=1.');
	}

	public function testRequiresRealContextReturnsError(): void
	{
		[, $body] = $this->request('POST', '/form/test-real-ctx/init');
		$data     = \json_decode($body, true);

		self::assertIsArray($data);
		self::assertSame(1, $data['error'], 'Provider requiring real context should return error=1 from standalone endpoint.');
	}

	public function testMissingResumeRefReturnsError(): void
	{
		// POST /next without X-OZONE-Form-Resume-Ref header.
		[, $body] = $this->request('POST', '/form/test-wizard/next', ['wish' => 'anything']);
		$data     = \json_decode($body, true);

		self::assertIsArray($data);
		self::assertSame(1, $data['error'], 'Missing resume-ref header should return error=1.');
	}

	// -------------------------------------------------------------------------
	// Init endpoint
	// -------------------------------------------------------------------------

	public function testInitReturnsInitFormAndProgress(): void
	{
		[, $body] = $this->request('POST', '/form/test-wizard/init');
		$data     = \json_decode($body, true);

		self::assertIsArray($data, 'Response was not valid JSON: ' . $body);
		self::assertSame(0, $data['error'], 'Init returned error. Body: ' . $body);
		self::assertArrayHasKey('resume_ref', $data['data']);
		self::assertIsString($data['data']['resume_ref']);
		self::assertFalse($data['data']['done']);
		self::assertSame(['step' => 0, 'total_steps' => 3], $data['data']['progress']);

		// Init form must include the 'wish' field.
		self::assertArrayHasKey('form', $data);
		$fieldRefs = \array_column($data['form']['fields'], 'ref');
		self::assertContains('wish', $fieldRefs);
	}

	// -------------------------------------------------------------------------
	// State endpoint
	// -------------------------------------------------------------------------

	public function testGetStateReturnsCurrentForm(): void
	{
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$data      = \json_decode($body, true);
		$resumeRef = $data['data']['resume_ref'];

		// GET /state should return the same init form without advancing.
		[, $body] = $this->request('GET', '/form/test-wizard/state', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error']);
		self::assertArrayHasKey('form', $data);
		$fieldRefs = \array_column($data['form']['fields'], 'ref');
		self::assertContains('wish', $fieldRefs);
	}

	// -------------------------------------------------------------------------
	// Full flow
	// -------------------------------------------------------------------------

	public function testFullResumableFormFlow(): void
	{
		// -- init: returns init form (wish field) at step 0.
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$data      = \json_decode($body, true);
		$resumeRef = $data['data']['resume_ref'];

		self::assertSame(0, $data['error']);
		self::assertFalse($data['data']['done']);
		self::assertSame(0, $data['data']['progress']['step']);

		// -- next (submit init form): wish -> step 0 form (name field).
		[, $body] = $this->request('POST', '/form/test-wizard/next', ['wish' => 'world peace'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error']);
		self::assertFalse($data['data']['done']);
		self::assertSame(['step' => 0, 'total_steps' => 3], $data['data']['progress']);
		$fieldRefs = \array_column($data['form']['fields'], 'ref');
		self::assertContains('name', $fieldRefs);

		// -- next (step 0: name): name -> step 1 form (color + hint fields).
		[, $body] = $this->request('POST', '/form/test-wizard/next', ['name' => 'alice'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error']);
		self::assertFalse($data['data']['done']);
		self::assertSame(['step' => 1, 'total_steps' => 3], $data['data']['progress']);
		$fieldRefs = \array_column($data['form']['fields'], 'ref');
		self::assertContains('color', $fieldRefs);
		self::assertContains('hint', $fieldRefs);

		// -- next (step 1: color+hint): color, hint -> step 2 form (notes field).
		[, $body] = $this->request('POST', '/form/test-wizard/next', ['color' => 'blue', 'hint' => 'sky'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error']);
		self::assertFalse($data['data']['done']);
		self::assertSame(['step' => 2, 'total_steps' => 3], $data['data']['progress']);
		$fieldRefs = \array_column($data['form']['fields'], 'ref');
		self::assertContains('notes', $fieldRefs);

		// -- next (step 2: notes): done = true.
		[, $body] = $this->request('POST', '/form/test-wizard/next', ['notes' => 'take notes'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error']);
		self::assertTrue($data['data']['done']);
		self::assertSame(['step' => 3, 'total_steps' => 3], $data['data']['progress']);
		self::assertArrayNotHasKey('form', $data);
	}

	// -------------------------------------------------------------------------
	// Cancel endpoint
	// -------------------------------------------------------------------------

	public function testCancelDeletesSession(): void
	{
		// Init a session.
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$data      = \json_decode($body, true);
		$resumeRef = $data['data']['resume_ref'];

		// Cancel it.
		[, $body] = $this->request('POST', '/form/test-wizard/cancel', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);
		self::assertSame(0, $data['error']);

		// Subsequent GET /state must fail (session was deleted).
		[, $body] = $this->request('GET', '/form/test-wizard/state', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);
		self::assertSame(1, $data['error'], 'Accessing a cancelled session should return error=1.');
	}

	// -------------------------------------------------------------------------
	// Evaluate endpoint
	// -------------------------------------------------------------------------

	public function testEvaluateReturnsServerSideFieldVisibility(): void
	{
		// Init -> submit init form -> submit step 0 with name='skip'.
		// This puts us on step 1 with the 'hint' field conditionally hidden.
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		$this->request('POST', '/form/test-wizard/next', ['wish' => 'anything'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['name' => 'skip'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);

		// Evaluate on step 1: accumulated cleaned data contains name='skip'.
		// hint's condition: neq('name', DynamicValue(=>'skip')) -> false -> hint is NOT visible.
		[, $body] = $this->request('POST', '/form/test-wizard/evaluate', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error']);
		self::assertArrayHasKey('visibility', $data['data']);
		self::assertFalse(
			$data['data']['visibility']['hint'] ?? true,
			"'hint' should be hidden when name='skip'."
		);
	}

	public function testEvaluateShowsFieldVisibleWhenConditionPasses(): void
	{
		// Init -> submit init form -> submit step 0 with name='alice' (not 'skip').
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		$this->request('POST', '/form/test-wizard/next', ['wish' => 'anything'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['name' => 'alice'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);

		// Evaluate: accumulated cleaned data contains name='alice'.
		// hint's condition: neq('name', DynamicValue(=>'skip')) -> true -> hint IS visible.
		[, $body] = $this->request('POST', '/form/test-wizard/evaluate', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error']);
		self::assertArrayHasKey('visibility', $data['data']);
		self::assertTrue(
			$data['data']['visibility']['hint'] ?? false,
			"'hint' should be visible when name != 'skip'."
		);
	}

	// -------------------------------------------------------------------------
	// Back endpoint
	// -------------------------------------------------------------------------

	public function testBackStepRestoresPreviousForm(): void
	{
		// Advance through init + step 0 + step 1 to land on step 2.
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		$this->request('POST', '/form/test-wizard/next', ['wish' => 'back-test'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['name' => 'bob'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['color' => 'red'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);

		// We are now on step 2 (notes). Go back one step.
		[, $body] = $this->request('POST', '/form/test-wizard/back', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error']);
		self::assertFalse($data['data']['done']);
		// Restored to step 1: progress.step = 1.
		self::assertSame(['step' => 1, 'total_steps' => 3], $data['data']['progress']);
		// Step 1 form fields are color and hint.
		$fieldRefs = \array_column($data['form']['fields'], 'ref');
		self::assertContains('color', $fieldRefs);
	}

	// -------------------------------------------------------------------------
	// Already-done guard
	// -------------------------------------------------------------------------

	public function testAlreadyDoneRejectsNextStep(): void
	{
		// Complete the full flow.
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		$this->request('POST', '/form/test-wizard/next', ['wish' => 'done-test'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['name' => 'charlie'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['color' => 'green'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['notes' => 'done'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);

		// One more next after completion must fail.
		[, $body] = $this->request('POST', '/form/test-wizard/next', ['extra' => 'field'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(1, $data['error'], 'POST /next on a completed session must return error=1.');
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Makes an HTTP request to the running test server.
	 *
	 * @param string               $method  HTTP verb (GET, POST, ...)
	 * @param string               $path    URL path (e.g. '/form/test-wizard/init')
	 * @param array<string,string> $fields  form fields for POST requests
	 * @param array<string,string> $headers extra HTTP headers
	 *
	 * @return array{0: int, 1: string} [status_code, body]
	 */
	private function request(string $method, string $path, array $fields = [], array $headers = []): array
	{
		$url = 'http://' . self::$host . ':' . self::$port . $path;

		$headerStr = "Accept: application/json\r\n";
		foreach ($headers as $name => $value) {
			$headerStr .= "{$name}: {$value}\r\n";
		}

		$opts = [
			'method'          => $method,
			'timeout'         => 10,
			'ignore_errors'   => true,
			'follow_location' => false,
			'header'          => $headerStr,
		];

		if ([] !== $fields) {
			$opts['content'] = \http_build_query($fields);
			$opts['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
		}

		$ctx  = \stream_context_create(['http' => $opts]);
		$body = @\file_get_contents($url, false, $ctx);

		if (false === $body) {
			return [0, ''];
		}

		$status = 0;
		if (
			!empty($http_response_header[0])
			&& \preg_match('/HTTP\/\d+(?:\.\d+)? (\d+)/', $http_response_header[0], $m)
		) {
			$status = (int) $m[1];
		}

		return [$status, $body];
	}
}
