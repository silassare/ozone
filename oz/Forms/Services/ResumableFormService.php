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
use OZONE\Core\App\Settings;
use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\FormResumeExpiredException;
use OZONE\Core\Exceptions\FormResumeNotYetActiveException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Forms\AbstractResumableFormProvider;
use OZONE\Core\Forms\Enums\FormResumePhase;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormResumeProgress;
use OZONE\Core\Forms\Interfaces\ResumableFormProviderInterface;
use OZONE\Core\Http\Response;
use OZONE\Core\Lang\I18nMessage;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class ResumableFormService.
 *
 * Provides REST endpoints for multi-step, session-resumable form submission.
 *
 * Providers implement {@see ResumableFormProviderInterface} (typically extending
 * {@see AbstractResumableFormProvider}) and register themselves
 * in the `oz.forms.providers` settings file.
 *
 * The `resume_ref` is returned in the body of `POST .../init` and must be sent
 * back on every subsequent request via a dedicated header.
 *
 * Route overview:
 *
 *  POST /form/:provider/init       - start a new session
 *  GET  /form/:provider/state      - get the current step form
 *  POST /form/:provider/next       - submit the current step, advance
 *  POST /form/:provider/back       - go back to the previous step
 *  POST /form/:provider/cancel     - discard the session
 *  POST /form/:provider/evaluate   - evaluate server-only field/rule visibility
 *
 * After the final step `done: true` is returned. The `resume_ref` can be passed to
 * {@see self::requireCompletion()} by downstream code to retrieve the accumulated data.
 */
final class ResumableFormService extends Service
{
	public const SESSION_CACHE_NAMESPACE = 'oz.form.sessions';

	public const ROUTE_INIT     = 'oz:form:init';
	public const ROUTE_STATE    = 'oz:form:state';
	public const ROUTE_NEXT     = 'oz:form:next';
	public const ROUTE_BACK     = 'oz:form:back';
	public const ROUTE_CANCEL   = 'oz:form:cancel';
	public const ROUTE_EVALUATE = 'oz:form:evaluate';

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router->post('/form/:provider/init', static fn (RouteInfo $ri) => (new self($ri))->initSession($ri))
			->name(self::ROUTE_INIT);

		$router->group('/form/:provider', static function (Router $router): void {
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
		});
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		// TODO: document the endpoints and their request/response schemas
	}

	// -------------------------------------------------------------------------
	// Route handlers
	// -------------------------------------------------------------------------

	/**
	 * POST /form/:provider/init.
	 *
	 * Creates a new form session for the given provider. When the provider returns
	 * a non-null `initForm()`, its data must be present in the request body.
	 *
	 * Response keys:
	 *  - `resume_ref` (string)  opaque 32-char session reference
	 *  - `form`       (?array)  the form to fill next; null when immediately done
	 *  - `done`       (bool)    true when the provider has no steps at all
	 *  - `expires_at` (?int)    Unix timestamp when the session expires, or null
	 *  - `progress`   (?array)  step progress block when `totalSteps()` is not null
	 *
	 * @throws NotFoundException               when the provider name is not registered
	 * @throws FormResumeNotYetActiveException when `notBefore()` is in the future
	 * @throws InvalidFormException            when init form validation fails
	 */
	public function initSession(RouteInfo $ri): Response
	{
		$provider_name      = $ri->param('provider');
		[$class, $provider] = $this->resolveProvider($provider_name, $ri);
		$context            = $ri->getContext();

		$not_before = $provider->notBefore();
		if (null !== $not_before && new DateTimeImmutable() < $not_before) {
			throw new FormResumeNotYetActiveException();
		}

		$scope_id     = $provider->resumeScope()->resolveId($context);
		$cleaned_form = new FormData();
		$init_form    = $class::initForm();

		if (null !== $init_form) {
			$unsafe_fd    = $context->getRequest()->getUnsafeFormData();
			$cleaned_form = $init_form->validate($unsafe_fd);
		}

		$progress = new FormResumeProgress();
		$progress->setStepIndex(0);

		$next_form = $provider->nextStep($cleaned_form, $progress);
		$phase     = null === $next_form ? FormResumePhase::DONE : FormResumePhase::STEPS;
		$progress->setPhase($phase);

		$deadline   = $provider->deadline();
		$expires_at = null !== $deadline
			? \min(\time() + $provider->resumeTTL(), $deadline->getTimestamp())
			: null;

		$resume_ref = Keys::id32('form.session');

		self::writeSession($resume_ref, [
			'provider_name'  => $provider_name,
			'phase'          => $phase->value,
			'cleaned_form'   => $cleaned_form->toArray(),
			'progress_state' => $progress->toArray(),
			'scope_id'       => $scope_id,
			'created_at'     => \time(),
			'expires_at'     => $expires_at,
			'history'        => [],
		], $provider->resumeTTL());

		return $this->respondWith($resume_ref, $next_form, FormResumePhase::DONE === $phase, $provider, $progress, $expires_at);
	}

	/**
	 * GET /form/:provider/state.
	 *
	 * Returns the current form to fill without advancing the session.
	 *
	 * @throws NotFoundException          when the session is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public function getState(RouteInfo $ri): Response
	{
		$provider_name = $ri->param('provider');
		$resume_ref    = $this->readResumeRef($ri);
		$context       = $ri->getContext();

		[$session, $provider, $cleaned_form, $progress] = $this->loadSession($ri, $resume_ref, $provider_name, $context);

		$current_form = $this->deriveCurrentForm($session['phase'], $provider, $cleaned_form, $progress);
		$expires_at   = $session['expires_at'] ?? null;

		return $this->respondWith($resume_ref, $current_form, FormResumePhase::DONE->value === $session['phase'], $provider, $progress, $expires_at);
	}

	/**
	 * POST /form/:provider/next.
	 *
	 * Validates the submitted step data, merges it into the accumulated
	 * cleaned form, and advances the session to the next step.
	 *
	 * @throws BadRequestException        when the session is already complete
	 * @throws NotFoundException          when the session is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 * @throws InvalidFormException       when step validation fails
	 */
	public function nextStep(RouteInfo $ri): Response
	{
		$provider_name = $ri->param('provider');
		$resume_ref    = $this->readResumeRef($ri);
		$context       = $ri->getContext();

		[$session, $provider, $cleaned_form, $progress] = $this->loadSession($ri, $resume_ref, $provider_name, $context);

		if (FormResumePhase::DONE->value === $session['phase']) {
			throw new BadRequestException('OZ_FORM_SESSION_ALREADY_DONE');
		}

		$current_form = $this->deriveCurrentForm($session['phase'], $provider, $cleaned_form, $progress);
		$unsafe_fd    = $context->getRequest()->getUnsafeFormData();
		$validated    = $current_form->validate($unsafe_fd, $cleaned_form);

		$history    = $session['history'] ?? [];
		$expires_at = $session['expires_at'] ?? null;

		if ($provider->isReversible()) {
			// Snapshot the state BEFORE merging validated data and advancing.
			// This allows backStep() to restore to the exact state which produced the
			// current form - so the user sees the same form again after going back.
			$history[] = [
				'cleaned_form'   => $cleaned_form->toArray(),
				'progress_state' => $progress->toArray(),
			];
		}

		// Merge the validated step data into the accumulated cleaned form.
		foreach ($validated->toArray() as $key => $value) {
			$cleaned_form->set((string) $key, $value);
		}

		// Advance the step index so the provider knows which step it is answering for.
		$progress->setStepIndex($progress->getStepIndex() + 1);

		$next_form = $provider->nextStep($cleaned_form, $progress);
		$phase     = null === $next_form ? FormResumePhase::DONE : FormResumePhase::STEPS;
		$progress->setPhase($phase);

		self::writeSession($resume_ref, \array_merge($session, [
			'phase'          => $phase->value,
			'cleaned_form'   => $cleaned_form->toArray(),
			'progress_state' => $progress->toArray(),
			'history'        => $history,
		]), $provider->resumeTTL());

		return $this->respondWith($resume_ref, $next_form, FormResumePhase::DONE === $phase, $provider, $progress, $expires_at);
	}

	/**
	 * POST /form/:provider/back.
	 *
	 * Reverts the session to the previous step by restoring the last history
	 * snapshot. Only available on providers where `isReversible()` returns true.
	 *
	 * @throws BadRequestException        when the provider is not reversible or there is no history
	 * @throws NotFoundException          when the session is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public function backStep(RouteInfo $ri): Response
	{
		$provider_name = $ri->param('provider');
		$resume_ref    = $this->readResumeRef($ri);
		$context       = $ri->getContext();

		[$session, $provider] = $this->loadSession($ri, $resume_ref, $provider_name, $context);

		if (!$provider->isReversible()) {
			throw new BadRequestException('OZ_FORM_SESSION_NOT_REVERSIBLE');
		}

		$history    = $session['history'] ?? [];
		$expires_at = $session['expires_at'] ?? null;

		if (empty($history)) {
			throw new BadRequestException('OZ_FORM_SESSION_NO_HISTORY');
		}

		$prev_snapshot       = \array_pop($history);
		$prev_cleaned_form   = new FormData($prev_snapshot['cleaned_form'] ?? []);
		$prev_progress       = new FormResumeProgress($prev_snapshot['progress_state'] ?? []);

		// Re-derive the form from the restored state (always STEPS — back is never from DONE).
		$current_form = $provider->nextStep($prev_cleaned_form, $prev_progress);

		self::writeSession($resume_ref, \array_merge($session, [
			'phase'          => FormResumePhase::STEPS->value,
			'cleaned_form'   => $prev_cleaned_form->toArray(),
			'progress_state' => $prev_progress->toArray(),
			'history'        => $history,
		]), $provider->resumeTTL());

		return $this->respondWith($resume_ref, $current_form, false, $provider, $prev_progress, $expires_at);
	}

	/**
	 * POST /form/:provider/evaluate.
	 *
	 * Server-side evaluation of all conditions the client cannot resolve locally.
	 * Merges the current raw client input with the accumulated session data and
	 * evaluates server-only field visibility and expect rules.
	 *
	 * @throws NotFoundException          when the session is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws BadRequestException        when the session is complete
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public function evaluateCurrent(RouteInfo $ri): Response
	{
		$provider_name = $ri->param('provider');
		$resume_ref    = $this->readResumeRef($ri);
		$context       = $ri->getContext();

		[$session, $provider, $cleaned_form, $progress] = $this->loadSession($ri, $resume_ref, $provider_name, $context);

		if (FormResumePhase::DONE->value === $session['phase']) {
			throw new BadRequestException('OZ_FORM_SESSION_ALREADY_DONE');
		}

		$current_form = $this->deriveCurrentForm($session['phase'], $provider, $cleaned_form, $progress);

		// Merge: accumulated cleaned values (validated) + current raw client input.
		$eval_data = new FormData(
			\array_merge(
				$cleaned_form->toArray(),
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
	 * POST /form/:provider/cancel.
	 *
	 * Discards the session. The resume_ref becomes invalid immediately.
	 *
	 * @throws NotFoundException          when the session is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public function cancelSession(RouteInfo $ri): Response
	{
		$provider_name = $ri->param('provider');
		$resume_ref    = $this->readResumeRef($ri);
		$context       = $ri->getContext();

		$this->loadSession($ri, $resume_ref, $provider_name, $context);

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
	 * provider and caller, and that the sequence has been fully completed.
	 *
	 * Intended for downstream route handlers that require the multi-step form to have
	 * been fully filled before executing a real action.
	 *
	 * The session is NOT deleted by this method — TTL handles cleanup.
	 *
	 * @param string  $provider_name The expected provider name (key from oz.forms.providers)
	 * @param string  $resume_ref    The session reference returned by `POST /init`
	 * @param Context $context       Current request context (used for ownership check)
	 *
	 * @return FormData Accumulated validated data from all completed steps
	 *
	 * @throws NotFoundException          when the session does not exist or has expired
	 * @throws ForbiddenException         when not done, wrong provider, or scope mismatch
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public static function requireCompletion(
		string $provider_name,
		string $resume_ref,
		Context $context
	): FormData {
		$session = self::loadRawSession($resume_ref);

		if (null === $session) {
			throw new NotFoundException('OZ_FORM_SESSION_NOT_FOUND', ['ref' => $resume_ref]);
		}

		if ($session['provider_name'] !== $provider_name) {
			throw new ForbiddenException('OZ_FORM_SESSION_PROVIDER_MISMATCH');
		}

		// Derive scope_id from provider settings for ownership check.
		$class = Settings::get('oz.forms.providers', $provider_name);

		if (!$class || !\is_a($class, ResumableFormProviderInterface::class, true)) {
			throw new NotFoundException('OZ_FORM_PROVIDER_NOT_FOUND', ['provider' => $provider_name]);
		}

		// We need a provider instance for resumeScope() — use a dummy RouteInfo-less instance.
		// requireCompletion() is called outside a provider handler, so we build a temp instance
		// from the class alone (relies on AbstractResumableFormProvider::instance() having
		// a fallback when $ri is null).
		/** @var ResumableFormProviderInterface $class */
		$provider = new $class();
		$scope_id = $provider->resumeScope()->resolveId($context);

		if ($session['scope_id'] !== $scope_id) {
			throw new ForbiddenException('OZ_FORM_SESSION_ACCESS_DENIED');
		}

		$expires_at = $session['expires_at'] ?? null;
		if (null !== $expires_at && \time() > $expires_at) {
			throw new FormResumeExpiredException();
		}

		if (FormResumePhase::DONE->value !== $session['phase']) {
			throw new ForbiddenException('OZ_FORM_SESSION_NOT_DONE', ['ref' => $resume_ref]);
		}

		return new FormData($session['cleaned_form'] ?? []);
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Reads the resume_ref from request header.
	 *
	 * @throws BadRequestException when the header is absent or empty
	 */
	private function readResumeRef(RouteInfo $ri): string
	{
		$header_name = Settings::get('oz.request', 'OZ_FORM_RESUME_REF_HEADER_NAME');
		$value       = $ri->getContext()->getRequest()->getHeaderLine($header_name);

		if ('' === $value) {
			throw new BadRequestException('OZ_FORM_SESSION_REF_MISSING');
		}

		return $value;
	}

	/**
	 * Resolves a provider name to its class and a fresh instance.
	 *
	 * @param string    $provider_name the `:provider` URL segment value
	 * @param RouteInfo $ri            the current route info, passed to instance()
	 *
	 * @return array{0: class-string<ResumableFormProviderInterface>, 1: ResumableFormProviderInterface}
	 *
	 * @throws NotFoundException when the provider name is not registered
	 */
	private function resolveProvider(string $provider_name, RouteInfo $ri): array
	{
		$class = Settings::get('oz.forms.providers', $provider_name);

		if (!$class || !\is_a($class, ResumableFormProviderInterface::class, true)) {
			throw new NotFoundException('OZ_FORM_PROVIDER_NOT_FOUND', ['provider' => $provider_name]);
		}

		/** @var class-string<ResumableFormProviderInterface> $class */
		return [$class, $class::instance($ri)];
	}

	/**
	 * Loads and validates a session, verifying ownership, provider match, and expiry.
	 *
	 * @return array{0: array, 1: ResumableFormProviderInterface, 2: FormData, 3: FormResumeProgress}
	 *
	 * @throws NotFoundException          when the session is not found
	 * @throws ForbiddenException         when ownership or provider check fails
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	private function loadSession(
		RouteInfo $ri,
		string $resume_ref,
		string $provider_name,
		Context $context
	): array {
		$session = self::loadRawSession($resume_ref);

		if (null === $session) {
			throw new NotFoundException('OZ_FORM_SESSION_NOT_FOUND', ['ref' => $resume_ref]);
		}

		if ($session['provider_name'] !== $provider_name) {
			throw new ForbiddenException('OZ_FORM_SESSION_PROVIDER_MISMATCH');
		}

		[, $provider] = $this->resolveProvider($provider_name, $ri);

		$scope_id = $provider->resumeScope()->resolveId($context);

		if ($session['scope_id'] !== $scope_id) {
			throw new ForbiddenException('OZ_FORM_SESSION_ACCESS_DENIED');
		}

		$expires_at = $session['expires_at'] ?? null;
		if (null !== $expires_at && \time() > $expires_at) {
			throw new FormResumeExpiredException();
		}

		$cleaned_form = new FormData($session['cleaned_form'] ?? []);
		$progress     = new FormResumeProgress($session['progress_state'] ?? []);

		return [$session, $provider, $cleaned_form, $progress];
	}

	/**
	 * Re-derives the current form from session phase, cleaned form, and progress state.
	 *
	 * The form is never stored in the session — it is always re-derived to keep the
	 * cache lean and to guarantee determinism.
	 */
	private function deriveCurrentForm(
		string $phase,
		ResumableFormProviderInterface $provider,
		FormData $cleaned_form,
		FormResumeProgress $progress
	): ?Form {
		return match ($phase) {
			FormResumePhase::INIT->value  => $provider::initForm(),
			FormResumePhase::STEPS->value => $provider->nextStep($cleaned_form, $progress),
			default                       => null, // DONE
		};
	}

	/**
	 * Builds the common response data array.
	 *
	 * When `totalSteps()` is not null a `progress` block is included.
	 */
	private function respondWith(
		string $resume_ref,
		?Form $form,
		bool $done,
		ResumableFormProviderInterface $provider,
		FormResumeProgress $progress,
		?int $expires_at
	): Response {
		$data = [
			'resume_ref' => $resume_ref,
			'done'       => $done,
			'expires_at' => $expires_at,
		];

		$total_steps = $provider->totalSteps();
		if (null !== $total_steps) {
			$data['progress'] = [
				'step'        => $progress->getStepIndex(),
				'total_steps' => $total_steps,
			];
		}

		$this->json()
			->setDone()->setData($data)->setForm($form);

		return $this->respond();
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
