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

use OZONE\Core\Testing\OZTestProject;
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

	/** Absolute path; writing a UNIX timestamp here activates notBefore() on the irreversible provider. */
	private static string $notBeforeFile = '';

	/** Absolute path; writing a UNIX timestamp here activates deadline() on the irreversible provider. */
	private static string $deadlineFile = '';

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

		// Config files used by TestFormIrreversibleProvider (read server-side per request).
		$notBeforeFile        = $proj->getPath() . '/irreversible_not_before.txt';
		$deadlineFile         = $proj->getPath() . '/irreversible_deadline.txt';
		self::$notBeforeFile  = $notBeforeFile;
		self::$deadlineFile   = $deadlineFile;

		// Inject provider stubs.
		$proj->writeFileFromStub('TestFormProvider', 'app/TestFormProvider.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('TestFormRealContextProvider', 'app/TestFormRealContextProvider.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('TestFormIrreversibleProvider', 'app/TestFormIrreversibleProvider.php', [
			'namespace'       => $ns,
			'not_before_file' => $notBeforeFile,
			'deadline_file'   => $deadlineFile,
		]);
		$proj->writeFileFromStub('TestFormConsumerRoutesProvider', 'app/TestFormConsumerRoutesProvider.php', [
			'namespace' => $ns,
		]);

		// Register providers in oz.forms.providers.
		$proj->setSetting('oz.forms.providers', 'test-wizard', "{$ns}\\TestFormProvider");
		$proj->setSetting('oz.forms.providers', 'test-real-ctx', "{$ns}\\TestFormRealContextProvider");
		$proj->setSetting('oz.forms.providers', 'test-irreversible', "{$ns}\\TestFormIrreversibleProvider");

		// Register consumer routes (requireCompletion / drop test endpoints).
		$proj->setSetting('oz.routes.api', "{$ns}\\TestFormConsumerRoutesProvider", true);

		// Build ORM classes and install the schema.
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		try {
			[$server, $host, $port] = $proj->startServer('api', self::$host);
		} catch (RuntimeException $e) {
			$proj->destroy();
			self::fail($e->getMessage());
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
		// Clean up temp config files created for the irreversible-provider tests.
		foreach ([self::$notBeforeFile, self::$deadlineFile] as $tmpFile) {
			if ('' !== $tmpFile && \file_exists($tmpFile)) {
				\unlink($tmpFile);
			}
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
		// hint's condition: neq('name', AsyncValue(=>'skip')) -> false -> hint is NOT visible.
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
		// hint's condition: neq('name', AsyncValue(=>'skip')) -> true -> hint IS visible.
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

	public function testEvaluateReturnsServerSideExpectResult(): void
	{
		// Advance to step 2 (notes).
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		$this->request('POST', '/form/test-wizard/next', ['wish' => 'anything'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['name' => 'alice'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['color' => 'blue'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);

		// expect() reads the raw payload of this request, so the rule is evaluated
		// against the notes value sent here: notes='forbidden-notes' -> must fail.
		[, $body] = $this->request('POST', '/form/test-wizard/evaluate', ['notes' => 'forbidden-notes'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error']);
		self::assertArrayHasKey('expect', $data['data']);
		self::assertNotEmpty($data['data']['expect'], 'expect array must contain at least one entry.');
		self::assertFalse(
			$data['data']['expect'][0]['passes'] ?? true,
			"Expect rule must fail when notes='forbidden-notes'."
		);

		// Same step, acceptable raw input -> the rule must pass.
		[, $body] = $this->request('POST', '/form/test-wizard/evaluate', ['notes' => 'ok-notes'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertTrue(
			$data['data']['expect'][0]['passes'] ?? false,
			"Expect rule must pass when notes != 'forbidden-notes'."
		);
	}

	public function testEvaluateReturnsServerSideCrossStepEnsureResult(): void
	{
		// Advance to step 2 (notes) with color='forbidden' accumulated.
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		$this->request('POST', '/form/test-wizard/next', ['wish' => 'anything'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['name' => 'alice'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['color' => 'forbidden'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);

		// ensure()[1] reads the accumulated cleaned data, which still holds the
		// step 1 color -> the cross-step rule must fail.
		[, $body] = $this->request('POST', '/form/test-wizard/evaluate', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error']);
		self::assertArrayHasKey('ensure', $data['data']);
		self::assertCount(2, $data['data']['ensure'], 'step 2 declares two ensure rules.');
		self::assertFalse(
			$data['data']['ensure'][1]['passes'] ?? true,
			"Cross-step ensure rule must fail when color='forbidden'."
		);
	}

	public function testEvaluateReturnsServerSideEnsureResult(): void
	{
		// Advance to step 2 (notes). The first ensure rule checks notes != 'bad-notes'.
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		$this->request('POST', '/form/test-wizard/next', ['wish' => 'anything'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['name' => 'alice'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['color' => 'blue'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);

		// Evaluate with notes='bad-notes' in the raw input -> ensure rule must fail.
		[, $body] = $this->request('POST', '/form/test-wizard/evaluate', ['notes' => 'bad-notes'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error']);
		self::assertArrayHasKey('ensure', $data['data']);
		self::assertNotEmpty($data['data']['ensure'], 'ensure array must contain at least one entry.');
		self::assertFalse(
			$data['data']['ensure'][0]['passes'] ?? true,
			"Ensure rule must fail when notes='bad-notes'."
		);

		// Evaluate with notes='good-notes' -> ensure rule must pass.
		[, $body] = $this->request('POST', '/form/test-wizard/evaluate', ['notes' => 'good-notes'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertTrue(
			$data['data']['ensure'][0]['passes'] ?? false,
			"Ensure rule must pass when notes != 'bad-notes'."
		);
	}

	public function testEvaluateReturnsFieldsetServerSideVisibility(): void
	{
		// Advance to step 2 (notes) with wish='skip-details' -> fieldset should be hidden.
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		$this->request('POST', '/form/test-wizard/next', ['wish' => 'skip-details'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['name' => 'alice'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['color' => 'blue'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);

		[, $body] = $this->request('POST', '/form/test-wizard/evaluate', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error']);
		self::assertArrayHasKey('fieldsets', $data['data']);
		self::assertFalse(
			$data['data']['fieldsets']['extra_details'] ?? true,
			"'extra_details' fieldset must be hidden when wish='skip-details'."
		);
	}

	public function testEvaluateFieldsetShownAndIntraFieldsetFieldVisibility(): void
	{
		// wish='skip-detail' -> fieldset IS shown, but conditional_detail inside is hidden.
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		$this->request('POST', '/form/test-wizard/next', ['wish' => 'skip-detail'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['name' => 'alice'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['color' => 'blue'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);

		[, $body] = $this->request('POST', '/form/test-wizard/evaluate', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error']);
		// Fieldset itself: wish='skip-detail' != 'skip-details' -> fieldset IS visible.
		self::assertTrue(
			$data['data']['fieldsets']['extra_details'] ?? false,
			"'extra_details' fieldset must be shown when wish='skip-detail' (not 'skip-details')."
		);
		// Field inside fieldset: wish='skip-detail' == AsyncValue('skip-detail') -> hidden.
		self::assertFalse(
			$data['data']['visibility']['extra_details.conditional_detail'] ?? true,
			"'extra_details.conditional_detail' must be hidden when wish='skip-detail'."
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
	// Invalid init-form data
	// -------------------------------------------------------------------------

	public function testInvalidInitFormDataReturnsError(): void
	{
		// Open a session to reach INIT phase (initForm() returns the 'wish' form).
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		// Submit /next without the required 'wish' field -- form validation must fail.
		[, $body] = $this->request('POST', '/form/test-wizard/next', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(1, $data['error'], 'Missing required init-form field should return error=1.');
	}

	// -------------------------------------------------------------------------
	// Spoofed scope_id (wrong host)
	// -------------------------------------------------------------------------

	public function testSpoofedScopeIdReturnsError(): void
	{
		// Open a session; scope_id stored = getHost() of the normal 127.0.0.1 request.
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		// Submit /next with a different Host header so scope_id does not match.
		// PHP stream context "header" overrides the auto-generated Host: value
		// (documented: "Values in this option will override other options such as Host:").
		[, $body] = $this->request('POST', '/form/test-wizard/next', ['wish' => 'spy'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
			'Host'                    => 'evil.example.com:9999',
		]);
		$data = \json_decode($body, true);

		self::assertSame(1, $data['error'], 'Spoofed scope_id (different Host) should return error=1.');
	}

	// -------------------------------------------------------------------------
	// Unknown resume_ref
	// -------------------------------------------------------------------------

	public function testUnknownResumeRefReturnsError(): void
	{
		// Fabricate a ref that was never issued.
		[, $body] = $this->request('POST', '/form/test-wizard/next', ['wish' => 'ghost'], [
			'X-OZONE-Form-Resume-Ref' => 'aaaabbbbccccddddeeeeffffgggghhhh',
		]);
		$data = \json_decode($body, true);

		self::assertSame(1, $data['error'], 'Unknown resume_ref should return error=1.');
	}

	// -------------------------------------------------------------------------
	// requireCompletion
	// -------------------------------------------------------------------------

	public function testRequireCompletionOnDoneSession(): void
	{
		// Complete the full test-wizard flow: init + submit init + 3 step submissions.
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		$this->request('POST', '/form/test-wizard/next', ['wish' => 'completion-test'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['name' => 'dave'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['color' => 'purple'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['notes' => 'final'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);

		// Downstream consumer calls requireCompletion via the test route.
		[, $body] = $this->request('GET', '/test/require-completion/' . $resumeRef);
		$data     = \json_decode($body, true);

		self::assertSame(0, $data['error'], 'requireCompletion on a done session should return error=0. Body: ' . $body);
		self::assertArrayHasKey('values', $data['data']);
		self::assertSame('completion-test', $data['data']['values']['wish'] ?? null);
		self::assertSame('dave', $data['data']['values']['name'] ?? null);
	}

	public function testRequireCompletionOnNotDoneSessionReturnsError(): void
	{
		// Open a session but do not advance past INIT.
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		[, $body] = $this->request('GET', '/test/require-completion/' . $resumeRef);
		$data     = \json_decode($body, true);

		self::assertSame(1, $data['error'], 'requireCompletion on a not-done session should return error=1.');
	}

	public function testRequireCompletionRejectsSessionOfAnotherProvider(): void
	{
		$resumeRef = $this->completeWizard('other-provider');

		// The consumer expects a test-irreversible flow; a completed test-wizard
		// session must not stand in for it.
		[, $body] = $this->request('GET', '/test/require-completion-as-irreversible/' . $resumeRef);
		$data     = \json_decode($body, true);

		self::assertSame(1, $data['error'], 'requireCompletion must reject a session of another provider. Body: ' . $body);
	}

	// -------------------------------------------------------------------------
	// Consuming a completed session on a provider route
	// -------------------------------------------------------------------------

	public function testProviderRouteConsumesCompletedSessionOnce(): void
	{
		$resumeRef = $this->completeWizard('route-consume');

		[, $body] = $this->request('POST', '/test/wizard-route', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error'], 'The provider route should accept its own completed session. Body: ' . $body);
		self::assertSame('route-consume', $data['data']['values']['wish'] ?? null);

		// The successful response dropped the session: it cannot be replayed.
		[, $body] = $this->request('POST', '/test/wizard-route', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(1, $data['error'], 'A consumed session must not be accepted twice. Body: ' . $body);
	}

	public function testProviderRouteRejectsSessionOfAnotherProvider(): void
	{
		$resumeRef = $this->completeWizard('route-cross');

		[, $body] = $this->request('POST', '/test/irreversible-route', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(1, $data['error'], 'A route must reject a session completed for another provider. Body: ' . $body);

		// The rejected attempt did not consume it: the right route still accepts it.
		[, $body] = $this->request('POST', '/test/wizard-route', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(0, $data['error'], 'The session should still be usable on its own route. Body: ' . $body);
	}

	// -------------------------------------------------------------------------
	// Form-level resume cache (Form::resumable()) replayed by RouteInfo
	// -------------------------------------------------------------------------

	public function testFormResumeReplaysValuesAfterAFailedAttempt(): void
	{
		// `second` is missing: the attempt fails but `first` is kept.
		[, $body] = $this->request('POST', '/test/incremental-a', ['first' => 'one']);
		self::assertSame(1, \json_decode($body, true)['error'], 'The incomplete attempt should fail. Body: ' . $body);

		// Only the missing field is sent; `first` is replayed.
		[, $body] = $this->request('POST', '/test/incremental-a', ['second' => 'two']);
		$data     = \json_decode($body, true);

		self::assertSame(0, $data['error'], 'The replayed attempt should succeed. Body: ' . $body);
		self::assertSame(['first' => 'one', 'second' => 'two'], $data['data']['values']);

		// The success cleared the entry: `first` is no longer replayed.
		[, $body] = $this->request('POST', '/test/incremental-a', ['second' => 'two']);
		self::assertSame(1, \json_decode($body, true)['error'], 'A consumed entry must not be replayed. Body: ' . $body);
	}

	public function testFormResumeIsScopedToTheRoute(): void
	{
		// Both routes use the same form (same id) under the same scope.
		[, $body] = $this->request('POST', '/test/incremental-a', ['first' => 'from-a']);
		self::assertSame(1, \json_decode($body, true)['error'], 'Body: ' . $body);

		[, $body] = $this->request('POST', '/test/incremental-b', ['second' => 'x']);
		self::assertSame(1, \json_decode($body, true)['error'], 'Route B must not replay route A values. Body: ' . $body);

		[, $body] = $this->request('POST', '/test/incremental-a', ['second' => 'y']);
		$data     = \json_decode($body, true);

		self::assertSame(0, $data['error'], 'Route A should still replay its own values. Body: ' . $body);
		self::assertSame('from-a', $data['data']['values']['first'] ?? null);
	}

	public function testFormResumeReplaysUploadedFiles(): void
	{
		// Both files validate (the temporary one is moved to TempFS, the other is
		// persisted), then `note` is missing: the files are saved for the next attempt.
		[, $body] = $this->requestMultipart('/test/incremental-upload', [], [
			'doc'   => ['name' => 'doc.txt', 'type' => 'text/plain', 'content' => 'temporary content'],
			'photo' => ['name' => 'photo.txt', 'type' => 'text/plain', 'content' => 'persisted content'],
		]);
		self::assertSame(1, \json_decode($body, true)['error'], 'The attempt without note should fail. Body: ' . $body);

		// Only `note` is sent; both files come back from the cache and are re-checked.
		[, $body] = $this->request('POST', '/test/incremental-upload', ['note' => 'hi']);
		$data     = \json_decode($body, true);

		self::assertSame(0, $data['error'], 'The uploaded files should be replayed. Body: ' . $body);
		self::assertSame([
			'note'               => 'hi',
			'doc_is_temp'        => true,
			'doc_exists'         => true,
			'photo_is_persisted' => true,
			'photo_loads'        => true,
		], $data['data']['values']);
	}

	// -------------------------------------------------------------------------
	// Irreversible provider -- POST /back
	// -------------------------------------------------------------------------

	public function testBackOnIrreversibleProviderReturnsError(): void
	{
		// test-irreversible has no initForm() so the session starts directly in STEPS.
		[, $body]  = $this->request('POST', '/form/test-irreversible/init');
		$data      = \json_decode($body, true);
		self::assertSame(0, $data['error'], 'POST /init for irreversible provider should succeed. Body: ' . $body);
		$resumeRef = $data['data']['resume_ref'];

		// POST /back must be rejected immediately because isReversible() = false.
		[, $body] = $this->request('POST', '/form/test-irreversible/back', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(1, $data['error'], 'POST /back on an irreversible provider should return error=1.');
	}

	// -------------------------------------------------------------------------
	// notBefore() in the future
	// -------------------------------------------------------------------------

	public function testNotBeforeInFutureReturnsError(): void
	{
		// Activate notBefore: write a timestamp 1 hour in the future.
		\file_put_contents(self::$notBeforeFile, (string) (\time() + 3600));

		try {
			[, $body] = $this->request('POST', '/form/test-irreversible/init');
			$data     = \json_decode($body, true);

			self::assertSame(1, $data['error'], 'POST /init with notBefore in the future should return error=1.');
		} finally {
			\unlink(self::$notBeforeFile);
		}
	}

	// -------------------------------------------------------------------------
	// deadline() in the past
	// -------------------------------------------------------------------------

	public function testDeadlineInPastReturnsError(): void
	{
		// Activate deadline: write a timestamp 100 seconds in the past.
		// doInit() stores expires_at = min(time()+TTL, deadline) = past timestamp.
		\file_put_contents(self::$deadlineFile, (string) (\time() - 100));

		try {
			// POST /init itself succeeds (notBefore is not checked here).
			[, $body] = $this->request('POST', '/form/test-irreversible/init');
			$initData = \json_decode($body, true);
			self::assertSame(0, $initData['error'], 'POST /init should succeed even with a past deadline. Body: ' . $body);
			$resumeRef = $initData['data']['resume_ref'];

			// Any handler that loads the session checks expires_at and must throw FormResumeExpiredException.
			[, $body] = $this->request('GET', '/form/test-irreversible/state', [], [
				'X-OZONE-Form-Resume-Ref' => $resumeRef,
			]);
			$data = \json_decode($body, true);

			self::assertSame(1, $data['error'], 'GET /state after a past deadline should return error=1.');
		} finally {
			\unlink(self::$deadlineFile);
		}
	}

	// -------------------------------------------------------------------------
	// drop() invalidates the ref
	// -------------------------------------------------------------------------

	public function testDropSessionInvalidatesRef(): void
	{
		// Complete the full flow so we have a done session.
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		$this->request('POST', '/form/test-wizard/next', ['wish' => 'drop-test'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['name' => 'will-drop'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$this->request('POST', '/form/test-wizard/next', ['color' => 'yellow'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		[, $nextBody] = $this->request('POST', '/form/test-wizard/next', ['notes' => 'bye'], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		self::assertSame(0, \json_decode($nextBody, true)['error'], 'Completing test-wizard should return error=0.');

		// Downstream consumer drops the session.
		[, $dropBody] = $this->request('POST', '/test/drop-session/' . $resumeRef);
		self::assertSame(0, \json_decode($dropBody, true)['error'], 'drop() should return error=0.');

		// After drop, any access with the old ref must fail.
		[, $body] = $this->request('GET', '/form/test-wizard/state', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		$data = \json_decode($body, true);

		self::assertSame(1, $data['error'], 'GET /state after drop() should return error=1.');
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
	 * Runs the whole test-wizard flow through the standalone endpoints and returns
	 * the resume_ref of the now-DONE session.
	 */
	private function completeWizard(string $wish): string
	{
		[, $body]  = $this->request('POST', '/form/test-wizard/init');
		$resumeRef = \json_decode($body, true)['data']['resume_ref'];

		foreach ([['wish' => $wish], ['name' => 'erin'], ['color' => 'blue'], ['notes' => 'n']] as $step) {
			[, $body] = $this->request('POST', '/form/test-wizard/next', $step, [
				'X-OZONE-Form-Resume-Ref' => $resumeRef,
			]);
		}

		self::assertTrue(\json_decode($body, true)['data']['done'] ?? false, 'test-wizard should be done. Body: ' . $body);

		return $resumeRef;
	}

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
		$headerStr = "Accept: application/json\r\n";
		foreach ($headers as $name => $value) {
			$headerStr .= "{$name}: {$value}\r\n";
		}

		$content = null;

		if ([] !== $fields) {
			$content = \http_build_query($fields);
			$headerStr .= "Content-Type: application/x-www-form-urlencoded\r\n";
		}

		return $this->send($method, $path, $headerStr, $content);
	}

	/**
	 * Makes a multipart/form-data POST, for file uploads.
	 *
	 * @param string                                                            $path   request path
	 * @param array<string, string>                                             $fields plain fields
	 * @param array<string, array{name: string, type: string, content: string}> $files  files by field name
	 *
	 * @return array{0: int, 1: string} [status, body]
	 */
	private function requestMultipart(string $path, array $fields, array $files): array
	{
		$boundary = 'oz-' . \bin2hex(\random_bytes(8));
		$content  = '';

		foreach ($fields as $name => $value) {
			$content .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
		}

		foreach ($files as $name => $file) {
			$content .= "--{$boundary}\r\n"
				. "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$file['name']}\"\r\n"
				. "Content-Type: {$file['type']}\r\n\r\n"
				. $file['content'] . "\r\n";
		}

		$content .= "--{$boundary}--\r\n";

		$headerStr = "Accept: application/json\r\nContent-Type: multipart/form-data; boundary={$boundary}\r\n";

		return $this->send('POST', $path, $headerStr, $content);
	}

	/**
	 * Sends a raw request to the running test server.
	 *
	 * @return array{0: int, 1: string} [status, body]
	 */
	private function send(string $method, string $path, string $headerStr, ?string $content): array
	{
		$url = 'http://' . self::$host . ':' . self::$port . $path;

		$opts = [
			'method'          => $method,
			'timeout'         => 10,
			'ignore_errors'   => true,
			'follow_location' => false,
			'header'          => $headerStr,
		];

		if (null !== $content) {
			$opts['content'] = $content;
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
