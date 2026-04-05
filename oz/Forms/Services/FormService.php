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

namespace OZONE\Core\Forms\Services;

use DateTimeImmutable;
use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Keys;
use OZONE\Core\App\Service;
use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\FormResumeExpiredException;
use OZONE\Core\Exceptions\FormResumeNotYetActiveException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Forms\AbstractResumableFormProvider;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\Interfaces\ResumableFormProviderInterface;
use OZONE\Core\Http\Response;
use OZONE\Core\Lang\I18nMessage;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class FormService.
 *
 * Provides REST endpoints for multi-step, session-resumable form submission.
 *
 * Providers implement {@see ResumableFormProviderInterface} (extend
 * {@see AbstractResumableFormProvider}) and register themselves before the
 * framework boots via {@see AbstractResumableFormProvider::register()}.
 *
 * Route overview:
 *
 *  POST /form/:provider/init            - start a new session
 *  GET  /form/:provider/:ref/state      - get the current step form
 *  POST /form/:provider/:ref/next       - submit the current step, advance
 *  POST /form/:provider/:ref/back       - go back to the previous step
 *  POST /form/:provider/:ref/cancel     - discard the session
 *
 * After the final step `done: true` is returned. The `resume_ref` can be
 * passed to {@see self::requireCompletion()} by downstream code to retrieve
 * the accumulated data.
 */
final class FormService extends Service
{
	public const SESSION_CACHE_NAMESPACE = 'oz.form.sessions';

	public const ROUTE_INIT     = 'oz:form:init';
	public const ROUTE_STATE    = 'oz:form:state';
	public const ROUTE_NEXT     = 'oz:form:next';
	public const ROUTE_BACK     = 'oz:form:back';
	public const ROUTE_CANCEL   = 'oz:form:cancel';
	public const ROUTE_EVALUATE = 'oz:form:evaluate';

	/**
	 * Key injected into session progress by the service itself to track how many
	 * steps have been completed. Providers may read this value in `nextStep()` to
	 * implement step-index-based branching that does not rely on user-submitted data.
	 *
	 * - 0  : first call to `nextStep()` (from `initSession`)
	 * - 1+ : n-th call from `nextStep()` (counter starts at 1 after the first step)
	 */
	public const STEP_INDEX_KEY = '_oz_form_step_index';

	/** Phase constants stored in the session cache. */
	private const PHASE_STEPS = 'steps';
	private const PHASE_DONE  = 'done';

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router->post('/form/:provider/init', static fn (RouteInfo $ri) => (new self($ri))->initSession($ri))
			->name(self::ROUTE_INIT);

		$router->group('/form/:provider/:ref', static function (Router $router) {
			$router->get('/state', static fn (RouteInfo $ri) => (new self($ri))->getState($ri))
				->name(self::ROUTE_STATE);

			$router->post('/next', static fn (RouteInfo $ri) => (new self($ri))->nextStep($ri))
				->name(self::ROUTE_NEXT);

			$router->post('/back', static fn (RouteInfo $ri) => (new self($ri))->backStep($ri))
				->name(self::ROUTE_BACK);

			$router->post('/cancel', static fn (RouteInfo $ri) => (new self($ri))->cancelSession($ri))
				->name(self::ROUTE_CANCEL);

			$router->post('/evaluate', static fn (RouteInfo $ri) => (new self($ri))->evaluateCurrent($ri))
				->name(self::ROUTE_EVALUATE);
		})->param('ref', '[0-9a-f]{32}');
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		// API doc is intentionally minimal - form structures are dynamic.
	}

	// -------------------------------------------------------------------------
	// Route handlers
	// -------------------------------------------------------------------------

	/**
	 * POST /form/:provider/init.
	 *
	 * Creates a new form session for the given provider.
	 * When the provider has an init form its data must be present in the request body.
	 *
	 * Response keys:
	 *  - `resume_ref` (string)   opaque 32-char session reference
	 *  - `form`       (?array)   the form to fill next, or null when immediately done
	 *  - `done`       (bool)     true when the provider has no steps at all
	 *  - `expires_at` (?int)     Unix timestamp when the session expires, or null
	 *  - `progress`   (?array)   step progress block when `totalSteps()` is not null
	 *
	 * @throws NotFoundException               when the provider ref is not registered
	 * @throws FormResumeNotYetActiveException when the provider has a `notBefore()` date in the future
	 * @throws InvalidFormException            when init form validation fails
	 */
	public function initSession(RouteInfo $ri): Response
	{
		$provider_ref = $ri->param('provider');
		$provider     = AbstractResumableFormProvider::resolve($provider_ref);
		$context      = $ri->getContext();

		$not_before = $provider->notBefore();
		if (null !== $not_before && new DateTimeImmutable() < $not_before) {
			throw new FormResumeNotYetActiveException();
		}

		$scope_id  = $provider->resumeScope()->resolveId($context);
		$progress  = new FormData();
		$init_form = $provider->initForm();

		if (null !== $init_form) {
			$unsafe_fd = $context->getRequest()->getUnsafeFormData();
			$progress  = $init_form->validate($unsafe_fd);
		}

		// Seed the step index so providers can use it for step-index-based branching.
		$progress->set(self::STEP_INDEX_KEY, 0);

		$next_form  = $provider->nextStep($progress);
		$phase      = null === $next_form ? self::PHASE_DONE : self::PHASE_STEPS;
		$resume_ref = Keys::id32('form.session');
		$expires_at = $provider->deadline()?->getTimestamp();

		self::writeSession($resume_ref, [
			'provider_ref' => $provider_ref,
			'provider_cls' => $provider::class,
			'phase'        => $phase,
			'progress'     => $progress->toArray(),
			'scope_id'     => $scope_id,
			'created_at'   => \time(),
			'expires_at'   => $expires_at,
			'history'      => [],
		], $provider->resumeTTL());

		$this->json()
			->setDone()
			->setData($this->buildResponseData($resume_ref, $next_form, self::PHASE_DONE === $phase, $provider, $progress, $expires_at));

		return $this->respond();
	}

	/**
	 * GET /form/:provider/:ref/state.
	 *
	 * Returns the current form to fill without advancing the session.
	 *
	 * Response keys:
	 *  - `form`       (?array)   the current form, or null when done
	 *  - `done`       (bool)     true when session is complete
	 *  - `resume_ref` (string)
	 *  - `expires_at` (?int)     Unix timestamp when the session expires, or null
	 *  - `progress`   (?array)   step progress block when `totalSteps()` is not null
	 *
	 * @throws NotFoundException          when the session ref is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public function getState(RouteInfo $ri): Response
	{
		$resume_ref   = $ri->param('ref');
		$provider_ref = $ri->param('provider');
		$context      = $ri->getContext();

		[$session, $provider, $progress] = $this->loadSession($resume_ref, $provider_ref, $context);

		$current_form = $this->deriveCurrentForm($session['phase'], $provider, $progress);
		$expires_at   = $session['expires_at'] ?? null;

		$this->json()
			->setDone()
			->setData($this->buildResponseData($resume_ref, $current_form, self::PHASE_DONE === $session['phase'], $provider, $progress, $expires_at));

		return $this->respond();
	}

	/**
	 * POST /form/:provider/:ref/next.
	 *
	 * Validates the submitted step data, merges it into the accumulated
	 * progress, and advances the session to the next step.
	 *
	 * Response keys:
	 *  - `resume_ref` (string)
	 *  - `form`       (?array)   the next form to fill, or null when done
	 *  - `done`       (bool)
	 *  - `expires_at` (?int)
	 *  - `progress`   (?array)   step progress block when `totalSteps()` is not null
	 *
	 * @throws BadRequestException        when the session is already complete
	 * @throws NotFoundException          when the session ref is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 * @throws InvalidFormException       when step validation fails
	 */
	public function nextStep(RouteInfo $ri): Response
	{
		$resume_ref   = $ri->param('ref');
		$provider_ref = $ri->param('provider');
		$context      = $ri->getContext();

		[$session, $provider, $progress] = $this->loadSession($resume_ref, $provider_ref, $context);

		if (self::PHASE_DONE === $session['phase']) {
			throw new BadRequestException('OZ_FORM_SESSION_ALREADY_DONE');
		}

		$current_form = $this->deriveCurrentForm($session['phase'], $provider, $progress);

		$unsafe_fd = $context->getRequest()->getUnsafeFormData();
		$validated = $current_form->validate($unsafe_fd, $progress);

		// Increment the step index so the provider knows which step just completed.
		$validated->set(self::STEP_INDEX_KEY, (int) $progress->get(self::STEP_INDEX_KEY, 0) + 1);

		$history    = $session['history'] ?? [];
		$expires_at = $session['expires_at'] ?? null;

		if ($provider->isReversible()) {
			// Save the current progress state before advancing so `backStep()` can restore it.
			$history[] = $session['progress'];
		}

		$next_form = $provider->nextStep($validated);
		$phase     = null === $next_form ? self::PHASE_DONE : self::PHASE_STEPS;

		self::writeSession($resume_ref, \array_merge($session, [
			'phase'    => $phase,
			'progress' => $validated->toArray(),
			'history'  => $history,
		]), $provider->resumeTTL());

		$this->json()
			->setDone()
			->setData($this->buildResponseData($resume_ref, $next_form, self::PHASE_DONE === $phase, $provider, $validated, $expires_at));

		return $this->respond();
	}

	/**
	 * POST /form/:provider/:ref/back.
	 *
	 * Reverts the session to the previous step by restoring the last history
	 * snapshot. Only available on providers where `isReversible()` returns true.
	 *
	 * Response keys:
	 *  - `resume_ref` (string)
	 *  - `form`       (?array)   the restored form to fill
	 *  - `done`       (bool)     always false
	 *  - `expires_at` (?int)
	 *  - `progress`   (?array)   step progress block when `totalSteps()` is not null
	 *
	 * @throws ForbiddenException         when the provider is not reversible
	 * @throws BadRequestException        when there is no history to go back to
	 * @throws NotFoundException          when the session ref is not found
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public function backStep(RouteInfo $ri): Response
	{
		$resume_ref   = $ri->param('ref');
		$provider_ref = $ri->param('provider');
		$context      = $ri->getContext();

		[$session, $provider] = $this->loadSession($resume_ref, $provider_ref, $context);

		if (!$provider->isReversible()) {
			throw new ForbiddenException('OZ_FORM_SESSION_NOT_REVERSIBLE');
		}

		$history    = $session['history'] ?? [];
		$expires_at = $session['expires_at'] ?? null;

		if (empty($history)) {
			throw new BadRequestException('OZ_FORM_SESSION_NO_HISTORY');
		}

		$prev_progress_array = \array_pop($history);
		$prev_progress       = new FormData($prev_progress_array);
		$current_form        = $provider->nextStep($prev_progress);

		self::writeSession($resume_ref, \array_merge($session, [
			'phase'    => self::PHASE_STEPS,
			'progress' => $prev_progress_array,
			'history'  => $history,
		]), $provider->resumeTTL());

		$this->json()
			->setDone()
			->setData($this->buildResponseData($resume_ref, $current_form, false, $provider, $prev_progress, $expires_at));

		return $this->respond();
	}

	/**
	 * POST /form/:provider/:ref/evaluate.
	 *
	 * Server-side evaluation of all conditions the client cannot resolve locally.
	 *
	 * The client sends its current (partial) raw field values. The server merges
	 * them with the accumulated session progress and evaluates:
	 *
	 *  1. Field visibility - for every field whose `if()` condition `isServerOnly()`,
	 *     the server runs the condition and returns the result in `visibility`.
	 *
	 *  2. Expect rules - for every `expect()` pre-validation rule that `isServerOnly()`,
	 *     the server runs the rule and returns pass/fail + message in `expect`.
	 *
	 * The client uses this to hide/show dynamic fields and display real-time
	 * validation feedback before the user hits submit.
	 *
	 * Response keys:
	 *  - `visibility` (array<field_ref, bool>)                              visible state for server-only conditional fields
	 *  - `expect`     (list<{index:int, passes:bool, message:?string}>)     server-only expect results
	 *
	 * @throws NotFoundException          when the session ref is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws BadRequestException        when the session is complete (no current form to evaluate)
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public function evaluateCurrent(RouteInfo $ri): Response
	{
		$resume_ref   = $ri->param('ref');
		$provider_ref = $ri->param('provider');
		$context      = $ri->getContext();

		[$session, $provider, $progress] = $this->loadSession($resume_ref, $provider_ref, $context);

		if (self::PHASE_DONE === $session['phase']) {
			throw new BadRequestException('OZ_FORM_SESSION_ALREADY_DONE');
		}

		$current_form = $this->deriveCurrentForm($session['phase'], $provider, $progress);

		// Merge: accumulated progress (validated) + current raw client input.
		// Field conditions and expect rules receiving this data operate on the best
		// available picture: persisted clean values + whatever the user is typing now.
		$eval_data = new FormData(
			\array_merge(
				$progress->toArray(),
				$context->getRequest()->getUnsafeFormData()->toArray()
			)
		);

		// -- Field visibility -------------------------------------------------
		$visibility = [];

		foreach ($current_form->getFields() as $field) {
			$cond = $field->getIf();

			if (null !== $cond && $cond->isServerOnly()) {
				$visibility[$field->getRef()] = $field->isEnabled($eval_data);
			}
		}

		// -- Expect rules (pre-validation, server-only) -----------------------
		$expect_results = [];

		foreach ($current_form->getPreValidationRules() as $index => $rule) {
			if ($rule->isServerOnly()) {
				$passes = $rule->check($eval_data);
				$msg    = $passes ? null : $rule->getViolationMessage();

				$expect_results[] = [
					'index'   => $index,
					'passes'  => $passes,
					'message' => $msg instanceof I18nMessage ? $msg->toArray() : $msg,
				];
			}
		}

		$this->json()
			->setDone()
			->setData([
				'visibility' => $visibility,
				'expect'     => $expect_results,
			]);

		return $this->respond();
	}

	/**
	 * POST /form/:provider/:ref/cancel.
	 *
	 * Discards the session. The resume_ref becomes invalid immediately.
	 *
	 * @throws NotFoundException          when the session ref is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public function cancelSession(RouteInfo $ri): Response
	{
		$resume_ref   = $ri->param('ref');
		$provider_ref = $ri->param('provider');
		$context      = $ri->getContext();

		$this->loadSession($resume_ref, $provider_ref, $context);

		CacheManager::persistent(self::SESSION_CACHE_NAMESPACE)->delete($resume_ref);

		$this->json()
			->setDone()
			->setData(['done' => true]);

		return $this->respond();
	}

	// -------------------------------------------------------------------------
	// Static helper for downstream consumption
	// -------------------------------------------------------------------------

	/**
	 * Validates that a session identified by `$resume_ref` belongs to the correct
	 * provider and caller, and that the sequence has been completed.
	 *
	 * Intended for downstream route handlers that require the multi-step form
	 * to have been fully filled before executing a real action.
	 *
	 * The session is NOT deleted by this method - let the TTL handle cleanup,
	 * or call `CacheManager::persistent(FormService::SESSION_CACHE_NAMESPACE)->delete($resume_ref)`
	 * once the action has consumed the data.
	 *
	 * @param string  $provider_ref The expected provider ref
	 * @param string  $resume_ref   The session reference returned by `POST /init`
	 * @param Context $context      Current request context (used for ownership check)
	 *
	 * @return FormData Accumulated validated data from all completed steps
	 *
	 * @throws NotFoundException          when the session does not exist or has expired
	 * @throws ForbiddenException         when the session is not yet done, belongs to a different
	 *                                    provider, or does not match the caller's scope
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public static function requireCompletion(
		string $provider_ref,
		string $resume_ref,
		Context $context
	): FormData {
		$session = self::loadRawSession($resume_ref);

		if (null === $session) {
			throw new NotFoundException('OZ_FORM_SESSION_NOT_FOUND', ['ref' => $resume_ref]);
		}

		if ($session['provider_ref'] !== $provider_ref) {
			throw new ForbiddenException('OZ_FORM_SESSION_PROVIDER_MISMATCH');
		}

		$provider = AbstractResumableFormProvider::resolve($session['provider_ref']);
		$scope_id = $provider->resumeScope()->resolveId($context);

		if ($session['scope_id'] !== $scope_id) {
			throw new ForbiddenException('OZ_FORM_SESSION_ACCESS_DENIED');
		}

		$expires_at = $session['expires_at'] ?? null;
		if (null !== $expires_at && \time() > $expires_at) {
			throw new FormResumeExpiredException();
		}

		if (self::PHASE_DONE !== $session['phase']) {
			throw new ForbiddenException('OZ_FORM_SESSION_NOT_DONE', ['ref' => $resume_ref]);
		}

		return new FormData($session['progress']);
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Loads and validates a session, returning the session array, provider instance,
	 * and current progress FormData.
	 *
	 * @return array{0: array, 1: ResumableFormProviderInterface, 2: FormData}
	 *
	 * @throws NotFoundException          when the session is not found
	 * @throws ForbiddenException         when ownership or provider check fails
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	private function loadSession(
		string $resume_ref,
		string $provider_ref,
		Context $context
	): array {
		$session = self::loadRawSession($resume_ref);

		if (null === $session) {
			throw new NotFoundException('OZ_FORM_SESSION_NOT_FOUND', ['ref' => $resume_ref]);
		}

		if ($session['provider_ref'] !== $provider_ref) {
			throw new ForbiddenException('OZ_FORM_SESSION_PROVIDER_MISMATCH');
		}

		$provider = AbstractResumableFormProvider::resolve($session['provider_ref']);
		$scope_id = $provider->resumeScope()->resolveId($context);

		if ($session['scope_id'] !== $scope_id) {
			throw new ForbiddenException('OZ_FORM_SESSION_ACCESS_DENIED');
		}

		$expires_at = $session['expires_at'] ?? null;
		if (null !== $expires_at && \time() > $expires_at) {
			throw new FormResumeExpiredException();
		}

		$progress = new FormData($session['progress']);

		return [$session, $provider, $progress];
	}

	/**
	 * Derives the current form from the session phase.
	 *
	 * In `PHASE_STEPS` the next form is re-derived from `$progress`.
	 * In `PHASE_DONE` returns null.
	 */
	private function deriveCurrentForm(
		string $phase,
		ResumableFormProviderInterface $provider,
		FormData $progress
	): ?Form {
		return match ($phase) {
			self::PHASE_STEPS => $provider->nextStep($progress),
			default           => null,
		};
	}

	/**
	 * Builds the common response data array for state-bearing responses.
	 *
	 * When `totalSteps()` is not null, a `progress` block is included in the data.
	 */
	private function buildResponseData(
		string $resume_ref,
		?Form $form,
		bool $done,
		ResumableFormProviderInterface $provider,
		FormData $progress,
		?int $expires_at
	): array {
		$data = [
			'resume_ref' => $resume_ref,
			'form'       => $form,
			'done'       => $done,
			'expires_at' => $expires_at,
		];

		$total_steps = $provider->totalSteps();
		if (null !== $total_steps) {
			$data['progress'] = [
				'current_step' => (int) $progress->get(self::STEP_INDEX_KEY, 0),
				'total_steps'  => $total_steps,
			];
		}

		return $data;
	}

	/**
	 * Loads a raw session array from the cache, or null when not found.
	 */
	private static function loadRawSession(string $resume_ref): ?array
	{
		$cached = CacheManager::persistent(self::SESSION_CACHE_NAMESPACE)->get($resume_ref);

		if (!\is_array($cached)) {
			return null;
		}

		return $cached;
	}

	/**
	 * Writes (or overwrites) a session record in the cache.
	 */
	private static function writeSession(string $resume_ref, array $session, int $ttl): void
	{
		CacheManager::persistent(self::SESSION_CACHE_NAMESPACE)->set($resume_ref, $session, $ttl);
	}
}
