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
use OZONE\Core\Forms\AbstractResumableFormProvider;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Http\Enums\RequestScope;

/**
 * Interface ResumableFormProviderInterface.
 *
 * A resumable form provider drives a multi-step, session-backed form sequence.
 * Each step's form is derived from the accumulated validated data of all previous
 * steps, making QCM flows, adaptive surveys, and multi-page wizards possible.
 *
 * Implementors MUST register themselves via
 * {@see AbstractResumableFormProvider::register()} so they can
 * be resolved from the URL's `:provider` segment.
 *
 * Contract for {@see self::nextStep()}: it must be deterministic - given the same
 * `$progress`, it must always describe the same form structure. Side effects and
 * random variation are not allowed.
 */
interface ResumableFormProviderInterface
{
	/**
	 * Returns the unique slug that identifies this provider type.
	 *
	 * This value is used as the `:provider` URL segment, so it must be URL-safe
	 * (letters, digits, hyphens, and colons are all accepted by the router).
	 *
	 * Example: `'quiz:geography'`, `'onboarding:wizard'`
	 */
	public static function providerRef(): string;

	/**
	 * Returns an optional pre-flight form shown before the main step sequence.
	 *
	 * Common uses: accepting terms of service, solving a captcha, providing
	 * context (e.g. choosing an exam subject before the actual questions start).
	 *
	 * Return `null` to skip straight to the first {@see self::nextStep()} call.
	 *
	 * When non-null the client is expected to include the init form data in the
	 * body of `POST /form/:provider/init`. The validated init fields are merged
	 * into the accumulated progress and passed as the starting `$progress` to
	 * the first {@see self::nextStep()} call.
	 */
	public function initForm(): ?Form;

	/**
	 * Returns the next form to display given the accumulated validated progress.
	 *
	 * Called at least once per step submission. The returned form describes
	 * exactly the fields the client must fill for the next step.
	 *
	 * Return `null` to signal that the sequence is complete - no more steps.
	 *
	 * @param FormData $progress accumulated validated data from all previous steps
	 *                           (including init form data when applicable)
	 */
	public function nextStep(FormData $progress): ?Form;

	/**
	 * Returns the total number of steps in the sequence, or null when unknown.
	 *
	 * When non-null, the service includes a `progress` block in every response
	 * so the client can show a progress indicator.
	 *
	 * Return null for open-ended or adaptive sequences where the total is not known
	 * in advance.
	 */
	public function totalSteps(): ?int;

	/**
	 * Returns true when the client is allowed to go back to a previous step.
	 *
	 * When true, the session snapshots progress before each submission so
	 * `POST /form/:provider/:ref/back` can restore the previous state.
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
	 * When non-null, `expires_at` is derived from `min(time() + resumeTTL(), deadline)`
	 * and stored in the session. Any handler that loads the session after the deadline
	 * throws {@see FormResumeExpiredException} (HTTP 410).
	 *
	 * Return null for sessions that expire only by inactivity (via {@see self::resumeTTL()}).
	 */
	public function deadline(): ?DateTimeImmutable;

	/**
	 * Returns the scope strategy used to tie session cache entries to a specific
	 * principal. The resolved scope ID is stored in the session and verified on
	 * every subsequent request so another caller cannot hijack an in-progress session.
	 */
	public function resumeScope(): RequestScope;

	/**
	 * Returns the session cache TTL in seconds.
	 *
	 * After this duration of inactivity the session cache entry expires and
	 * the client must start a new session.
	 */
	public function resumeTTL(): int;
}
