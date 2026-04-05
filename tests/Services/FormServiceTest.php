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

namespace OZONE\Tests\Services;

use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Forms\AbstractResumableFormProvider;
use OZONE\Core\Forms\DynamicValue;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\Services\FormService;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Router\Route;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * Class FormServiceTest.
 *
 * Tests for {@see FormService} route handlers and the {@see FormService::requireCompletion()} helper.
 *
 * Providers registered here all use {@see RequestScope::HOST} so that tests do not
 * require a real stateful auth session — the HOST scope resolves to the request
 * host:port, which is always available in the test context.
 *
 * @internal
 *
 * @coversNothing
 */
final class FormServiceTest extends TestCase
{
	/** Dummy route used to build RouteInfo objects. */
	private static Route $dummyRoute;

	public static function setUpBeforeClass(): void
	{
		$router            = new Router();
		$router->get('/dummy', static fn () => null)->name('dummy');
		self::$dummyRoute  = $router->getRoute('dummy');
	}

	protected function setUp(): void
	{
		CacheManager::persistent(FormService::SESSION_CACHE_NAMESPACE)->clear();
		AbstractResumableFormProvider::clearRegistry();

		AbstractResumableFormProvider::register(TwoStepProvider::class);
		AbstractResumableFormProvider::register(ImmediateDoneProvider::class);
		AbstractResumableFormProvider::register(WithInitProvider::class);
		AbstractResumableFormProvider::register(DynamicFieldProvider::class);
	}

	protected function tearDown(): void
	{
		CacheManager::persistent(FormService::SESSION_CACHE_NAMESPACE)->clear();
		AbstractResumableFormProvider::clearRegistry();
	}

	// -----------------------------------------------------------------------
	// initSession
	// -----------------------------------------------------------------------

	public function testInitSessionCreatesSessionAndReturnsFirstStepForm(): void
	{
		$svc      = $this->makeService(['provider' => 'svc:two-step']);
		$response = $svc->initSession($this->makeRi(['provider' => 'svc:two-step']));

		$body = \json_decode((string) $response->getBody(), true);

		self::assertFalse($body['data']['done']);
		self::assertArrayHasKey('resume_ref', $body['data']);
		self::assertNotNull($body['data']['form']);
		self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $body['data']['resume_ref']);
	}

	public function testInitSessionWithImmediatelyDoneProviderSetsDoneTrue(): void
	{
		$ri       = $this->makeRi(['provider' => 'svc:done']);
		$svc      = new FormService($ri);
		$response = $svc->initSession($ri);
		$body     = \json_decode((string) $response->getBody(), true);

		self::assertTrue($body['data']['done']);
		self::assertNull($body['data']['form']);
	}

	public function testInitSessionWithInitFormMergesInitDataIntoProgress(): void
	{
		$ri       = $this->makeRi(['provider' => 'svc:with-init']);
		$svc      = new FormService($ri);
		$response = $svc->initSession($ri);
		$body     = \json_decode((string) $response->getBody(), true);

		// svc:with-init provider: initForm has optional field, nextForm returns null -> done immediately
		self::assertTrue($body['data']['done']);
	}

	public function testInitSessionUnknownProviderThrowsNotFoundException(): void
	{
		$ri  = $this->makeRi(['provider' => 'svc:no-such-provider']);
		$svc = new FormService($ri);

		$this->expectException(NotFoundException::class);

		$svc->initSession($ri);
	}

	// -----------------------------------------------------------------------
	// getState
	// -----------------------------------------------------------------------

	public function testGetStateReturnsCurrentFormForActiveSession(): void
	{
		// Create session via initSession
		$initRi   = $this->makeRi(['provider' => 'svc:two-step']);
		$svc      = new FormService($initRi);
		$initResp = $svc->initSession($initRi);
		$initBody = \json_decode((string) $initResp->getBody(), true);
		$ref      = $initBody['data']['resume_ref'];

		// Now call getState
		$stateRi   = $this->makeRi(['provider' => 'svc:two-step', 'ref' => $ref]);
		$stateSvc  = new FormService($stateRi);
		$stateResp = $stateSvc->getState($stateRi);
		$stateBody = \json_decode((string) $stateResp->getBody(), true);

		self::assertFalse($stateBody['data']['done']);
		self::assertNotNull($stateBody['data']['form']);
		self::assertSame($ref, $stateBody['data']['resume_ref']);
	}

	public function testGetStateUnknownRefThrowsNotFoundException(): void
	{
		$ri  = $this->makeRi(['provider' => 'svc:two-step', 'ref' => \str_repeat('a', 32)]);
		$svc = new FormService($ri);

		$this->expectException(NotFoundException::class);

		$svc->getState($ri);
	}

	public function testGetStateWrongProviderThrowsForbiddenException(): void
	{
		// Create session with two-step provider
		$initRi  = $this->makeRi(['provider' => 'svc:two-step']);
		$initSvc = new FormService($initRi);
		$body    = \json_decode((string) $initSvc->initSession($initRi)->getBody(), true);
		$ref     = $body['data']['resume_ref'];

		// Request state with wrong provider
		$ri  = $this->makeRi(['provider' => 'svc:done', 'ref' => $ref]);
		$svc = new FormService($ri);

		$this->expectException(ForbiddenException::class);

		$svc->getState($ri);
	}

	// -----------------------------------------------------------------------
	// nextStep
	// -----------------------------------------------------------------------

	public function testNextStepAdvancesSessionAndReturnsNextForm(): void
	{
		// Init
		$initRi  = $this->makeRi(['provider' => 'svc:two-step']);
		$initSvc = new FormService($initRi);
		$ref     = \json_decode((string) $initSvc->initSession($initRi)->getBody(), true)['data']['resume_ref'];

		// Step 1 (no required fields — empty POST body validates)
		$nextRi   = $this->makeRi(['provider' => 'svc:two-step', 'ref' => $ref]);
		$nextSvc  = new FormService($nextRi);
		$nextResp = $nextSvc->nextStep($nextRi);
		$nextBody = \json_decode((string) $nextResp->getBody(), true);

		// TwoStepProvider has step1 and step2; after step1 we expect step2 form
		self::assertFalse($nextBody['data']['done']);
		self::assertNotNull($nextBody['data']['form']);
	}

	public function testNextStepFinalStepSetsDoneTrue(): void
	{
		$initRi  = $this->makeRi(['provider' => 'svc:two-step']);
		$initSvc = new FormService($initRi);
		$ref     = \json_decode((string) $initSvc->initSession($initRi)->getBody(), true)['data']['resume_ref'];

		// Advance past step 1
		$step1Ri  = $this->makeRi(['provider' => 'svc:two-step', 'ref' => $ref]);
		(new FormService($step1Ri))->nextStep($step1Ri);

		// Step 2 is the final step
		$step2Ri   = $this->makeRi(['provider' => 'svc:two-step', 'ref' => $ref]);
		$step2Svc  = new FormService($step2Ri);
		$step2Resp = $step2Svc->nextStep($step2Ri);
		$step2Body = \json_decode((string) $step2Resp->getBody(), true);

		self::assertTrue($step2Body['data']['done']);
		self::assertNull($step2Body['data']['form']);
	}

	public function testNextStepOnDoneSessionThrowsBadRequestException(): void
	{
		$initRi  = $this->makeRi(['provider' => 'svc:done']);
		$initSvc = new FormService($initRi);
		$ref     = \json_decode((string) $initSvc->initSession($initRi)->getBody(), true)['data']['resume_ref'];

		$ri  = $this->makeRi(['provider' => 'svc:done', 'ref' => $ref]);
		$svc = new FormService($ri);

		$this->expectException(BadRequestException::class);

		$svc->nextStep($ri);
	}

	public function testNextStepUnknownRefThrowsNotFoundException(): void
	{
		$ri  = $this->makeRi(['provider' => 'svc:two-step', 'ref' => \str_repeat('b', 32)]);
		$svc = new FormService($ri);

		$this->expectException(NotFoundException::class);

		$svc->nextStep($ri);
	}

	// -----------------------------------------------------------------------
	// cancelSession
	// -----------------------------------------------------------------------

	public function testCancelSessionDeletesSessionEntry(): void
	{
		$initRi  = $this->makeRi(['provider' => 'svc:two-step']);
		$initSvc = new FormService($initRi);
		$ref     = \json_decode((string) $initSvc->initSession($initRi)->getBody(), true)['data']['resume_ref'];

		$cancelRi   = $this->makeRi(['provider' => 'svc:two-step', 'ref' => $ref]);
		$cancelSvc  = new FormService($cancelRi);
		$cancelResp = $cancelSvc->cancelSession($cancelRi);
		$cancelBody = \json_decode((string) $cancelResp->getBody(), true);

		self::assertTrue($cancelBody['data']['done']);

		// Subsequent getState must throw NotFoundException
		$afterRi  = $this->makeRi(['provider' => 'svc:two-step', 'ref' => $ref]);
		$afterSvc = new FormService($afterRi);

		$this->expectException(NotFoundException::class);

		$afterSvc->getState($afterRi);
	}

	// -----------------------------------------------------------------------
	// evaluateCurrent
	// -----------------------------------------------------------------------

	public function testEvaluateCurrentReturnsServerOnlyFieldVisibility(): void
	{
		$initRi  = $this->makeRi(['provider' => 'svc:dynamic']);
		$initSvc = new FormService($initRi);
		$ref     = \json_decode((string) $initSvc->initSession($initRi)->getBody(), true)['data']['resume_ref'];

		$evalRi   = $this->makeRi(['provider' => 'svc:dynamic', 'ref' => $ref]);
		$evalSvc  = new FormService($evalRi);
		$evalResp = $evalSvc->evaluateCurrent($evalRi);
		$evalBody = \json_decode((string) $evalResp->getBody(), true);

		// DynamicFieldProvider has one field with a server-only if() condition
		self::assertArrayHasKey('visibility', $evalBody['data']);
		self::assertArrayHasKey('expect', $evalBody['data']);
		self::assertArrayHasKey('dynamic_answer', $evalBody['data']['visibility']);
	}

	public function testEvaluateCurrentOnDoneSessionThrowsBadRequestException(): void
	{
		$initRi  = $this->makeRi(['provider' => 'svc:done']);
		$initSvc = new FormService($initRi);
		$ref     = \json_decode((string) $initSvc->initSession($initRi)->getBody(), true)['data']['resume_ref'];

		$ri  = $this->makeRi(['provider' => 'svc:done', 'ref' => $ref]);
		$svc = new FormService($ri);

		$this->expectException(BadRequestException::class);

		$svc->evaluateCurrent($ri);
	}

	// -----------------------------------------------------------------------
	// requireCompletion (static helper)
	// -----------------------------------------------------------------------

	public function testRequireCompletionReturnsFinalProgress(): void
	{
		$initRi  = $this->makeRi(['provider' => 'svc:done']);
		$initSvc = new FormService($initRi);
		$ref     = \json_decode((string) $initSvc->initSession($initRi)->getBody(), true)['data']['resume_ref'];

		$progress = FormService::requireCompletion('svc:done', $ref, context());

		self::assertInstanceOf(FormData::class, $progress);
	}

	public function testRequireCompletionOnIncompleteSessionThrowsForbiddenException(): void
	{
		$initRi  = $this->makeRi(['provider' => 'svc:two-step']);
		$initSvc = new FormService($initRi);
		$ref     = \json_decode((string) $initSvc->initSession($initRi)->getBody(), true)['data']['resume_ref'];

		$this->expectException(ForbiddenException::class);

		FormService::requireCompletion('svc:two-step', $ref, context());
	}

	public function testRequireCompletionUnknownRefThrowsNotFoundException(): void
	{
		$this->expectException(NotFoundException::class);

		FormService::requireCompletion('svc:done', \str_repeat('c', 32), context());
	}

	public function testRequireCompletionWrongProviderThrowsForbiddenException(): void
	{
		$initRi  = $this->makeRi(['provider' => 'svc:done']);
		$initSvc = new FormService($initRi);
		$ref     = \json_decode((string) $initSvc->initSession($initRi)->getBody(), true)['data']['resume_ref'];

		$this->expectException(ForbiddenException::class);

		FormService::requireCompletion('svc:two-step', $ref, context());
	}

	// -----------------------------------------------------------------------
	// Helper
	// -----------------------------------------------------------------------

	private function makeRi(array $params = []): RouteInfo
	{
		return new RouteInfo(context(), self::$dummyRoute, $params);
	}

	private function makeService(array $params): FormService
	{
		return new FormService($this->makeRi($params));
	}
}

// ---------------------------------------------------------------------------
// Inline test-only provider fixtures (all use HOST scope for test convenience)
// ---------------------------------------------------------------------------

/**
 * Two-step provider. Step 1 tracks 'visited_step1'; step 2 runs when that is set.
 *
 * Step forms have no required fields so they validate successfully with an empty request body.
 *
 * @internal
 */
final class TwoStepProvider extends AbstractResumableFormProvider
{
	public static function providerRef(): string
	{
		return 'svc:two-step';
	}

	public function resumeScope(): RequestScope
	{
		return RequestScope::HOST;
	}

	public function nextStep(FormData $progress): ?Form
	{
		// Use the service-managed step index so no user-submitted data is required
		// to drive step progression — critical for empty-body test submissions.
		$step = (int) $progress->get(FormService::STEP_INDEX_KEY, 0);

		if ($step >= 2) {
			return null; // done after 2 nextStep calls
		}

		$form = new Form();
		$form->field('step_' . $step . '_data');

		return $form;
	}
}

/**
 * Provider where nextStep() returns null immediately — session is done on init.
 *
 * @internal
 */
final class ImmediateDoneProvider extends AbstractResumableFormProvider
{
	public static function providerRef(): string
	{
		return 'svc:done';
	}

	public function resumeScope(): RequestScope
	{
		return RequestScope::HOST;
	}

	public function nextStep(FormData $progress): ?Form
	{
		return null;
	}
}

/**
 * Provider with an optional init form.
 *
 * @internal
 */
final class WithInitProvider extends AbstractResumableFormProvider
{
	public static function providerRef(): string
	{
		return 'svc:with-init';
	}

	public function resumeScope(): RequestScope
	{
		return RequestScope::HOST;
	}

	public function initForm(): ?Form
	{
		$form = new Form();
		$form->field('consent'); // optional — validates with empty body

		return $form;
	}

	public function nextStep(FormData $progress): ?Form
	{
		return null;
	}
}

/**
 * Provider with a field that has a server-only `if()` condition (DynamicValue).
 *
 * @internal
 */
final class DynamicFieldProvider extends AbstractResumableFormProvider
{
	public static function providerRef(): string
	{
		return 'svc:dynamic';
	}

	public function resumeScope(): RequestScope
	{
		return RequestScope::HOST;
	}

	public function nextStep(FormData $progress): ?Form
	{
		$form  = new Form();
		$field = $form->field('dynamic_answer');

		// Server-only visibility condition: visible only when type == 'special'
		$field->if()->eq('type', new DynamicValue(static fn (FormData $fd) => 'special'));

		return $form;
	}
}
