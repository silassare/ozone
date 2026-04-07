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

namespace OZONE\Core\Forms\Interfaces;

use DateTimeImmutable;
use OZONE\Core\Exceptions\FormResumeExpiredException;
use OZONE\Core\Exceptions\FormResumeNotYetActiveException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormResumeProgress;
use OZONE\Core\Forms\Services\ResumableFormService;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Router\RouteFormResumeInterceptor;
use OZONE\Core\Router\RouteInfo;

/**
 * Interface ResumableFormProviderInterface.
 *
 * A resumable form provider drives a multi-step, session-backed form sequence.
 * Each step form is derived from the accumulated validated data of all previous
 * steps, making QCM flows, adaptive surveys, and multi-page wizards possible.
 *
 * Providers are resolved by name via the `oz.forms.providers` settings registry.
 *
 * Contract for {@see self::nextStep()}: it must be deterministic — given the same
 * inputs it must always return the same form structure. Side effects are not allowed.
 */
interface ResumableFormProviderInterface
{
	/**
	 * Returns the unique slug that identifies this provider type.
	 *
	 * This value is used as the `:provider` URL segment and must match the key
	 * registered in the `oz.forms.providers` settings file.
	 *
	 * Example: `'quiz:geography'`, `'route'`, `'onboarding:wizard'`
	 */
	public static function getName(): string;

	/**
	 * Returns true when the provider requires the route-interceptor context
	 * {@see RouteFormResumeInterceptor} and cannot be used via the standalone
	 * {@see ResumableFormService} endpoints.
	 *
	 * When true, {@see ResumableFormService} will reject any direct request to those
	 * endpoints with a {@see BadRequestException}. Default: true.
	 */
	public static function requiresRealContext(): bool;

	/**
	 * Returns an optional pre-flight form shown before the main step sequence.
	 *
	 * When non-null the client must include the init form data in the body of
	 * `POST /form/:provider/init`. The validated init fields become the starting
	 * `$cleaned_form` for the first {@see self::nextStep()} call.
	 *
	 * Return `null` to skip straight to the first {@see self::nextStep()} call.
	 *
	 * STATIC so that API doc generation and route discovery can call it without
	 * constructing a full provider instance.
	 */
	public static function initForm(): ?Form;

	/**
	 * Factory: creates a provider instance bound to the current route context.
	 *
	 * The returned instance stores `$ri` so it can access the context, request,
	 * auth state, etc. Called once per ResumableFormService handler invocation.
	 *
	 * @param RouteInfo $ri the current route info
	 *
	 * @return static
	 */
	public static function instance(RouteInfo $ri): static;

	/**
	 * Returns the next form to display given the accumulated validated data and
	 * the provider's private progress state.
	 *
	 * - `$cleaned_form`: accumulated validated fields from all previous steps
	 *   (shareable with the client if needed). Read values but do NOT mutate.
	 * - `$progress`: private provider state, phase, and step index (never sent to
	 *   the client). The provider may call `$progress->set(key, value)` to store
	 *   bookkeeping data for use in subsequent calls.
	 *
	 * Return `null` to signal that the sequence is complete (no more steps).
	 *
	 * @param FormData           $cleaned_form accumulated validated fields from all previous steps
	 * @param FormResumeProgress $progress     private provider state
	 */
	public function nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form;

	/**
	 * Returns the total number of steps, or null when unknown.
	 *
	 * When non-null, every response includes a `progress` block so the client can
	 * show a progress indicator. Return null for open-ended or adaptive sequences.
	 */
	public function totalSteps(): ?int;

	/**
	 * Returns true when the client is allowed to go back to a previous step.
	 *
	 * When true, the service snapshots progress before each submission so
	 * `POST /form/:provider/back` can restore the previous state.
	 */
	public function isReversible(): bool;

	/**
	 * Returns the earliest instant at which this flow may be started.
	 *
	 * When non-null and in the future, `POST /form/:provider/init` throws
	 * {@see FormResumeNotYetActiveException} (HTTP 403).
	 *
	 * Return null to allow the flow to start at any time.
	 */
	public function notBefore(): ?DateTimeImmutable;

	/**
	 * Returns the hard deadline after which in-progress sessions are rejected.
	 *
	 * When non-null, `expires_at` is derived from
	 * `min(time() + resumeTTL(), deadline->getTimestamp())` and stored in the session.
	 * Any handler that loads the session after the deadline throws
	 * {@see FormResumeExpiredException} (HTTP 410).
	 *
	 * Return null for sessions that expire only by inactivity (via {@see self::resumeTTL()}).
	 */
	public function deadline(): ?DateTimeImmutable;

	/**
	 * Returns the scope strategy used to tie session cache entries to a specific
	 * principal. The resolved scope ID is stored in the session and verified on
	 * every subsequent request so another caller cannot hijack a session.
	 */
	public function resumeScope(): RequestScope;

	/**
	 * Returns the session cache TTL in seconds.
	 *
	 * After this duration of inactivity the session cache entry expires and the
	 * client must start a new session.
	 */
	public function resumeTTL(): int;
}
