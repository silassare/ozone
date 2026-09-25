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

namespace OZONE\Core\Forms\Resume;

use DateTimeImmutable;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use OZONE\Core\App\Keys;
use OZONE\Core\App\Settings;
use OZONE\Core\Columns\Types\TypeFile;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\FormResumeExpiredException;
use OZONE\Core\Exceptions\FormResumeNotYetActiveException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Field;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormDataClean;
use OZONE\Core\Forms\FormValidationContext;
use OZONE\Core\Forms\Resume\Enums\FormResumePhase;
use OZONE\Core\Forms\Resume\Interfaces\ResumableFormProviderInterface;
use OZONE\Core\Forms\RuleSet;
use OZONE\Core\Forms\TypesSwitcher;
use OZONE\Core\Lang\I18nMessage;
use OZONE\Core\Router\Route;
use OZONE\Core\Router\RouteInfo;

/**
 * Class FormSessionManager.
 *
 * The resumable form session state machine, independent of how it is exposed.
 * {@see Services\ResumableFormService} drives it from both the standalone
 * endpoints and the route interceptor, and turns its results into responses.
 *
 * Phases: INIT (optional pre-flight form) -> STEPS -> DONE.
 */
final class FormSessionManager
{
	private readonly ResumableFormProviderInterface $provider;

	/**
	 * {@see Route::key()} of the serving route for route-bound sessions,
	 * null for standalone ones.
	 */
	private readonly ?string $route_key;

	/**
	 * FormSessionManager constructor.
	 *
	 * @param class-string<ResumableFormProviderInterface> $provider_class the provider driving the session
	 * @param RouteInfo                                    $ri             the current route info
	 * @param null|string                                  $provider_name  the registry name when reached through the
	 *                                                                     standalone endpoints; the session must then
	 *                                                                     have been opened under that same name. Null
	 *                                                                     when reached through a route, which binds
	 *                                                                     the session to that route.
	 */
	public function __construct(
		private readonly string $provider_class,
		private readonly RouteInfo $ri,
		private readonly ?string $provider_name = null,
	) {
		$this->provider  = $provider_class::instance($ri);
		$this->route_key = null === $provider_name ? $ri->route()->key() : null;
	}

	/**
	 * Creates a new session.
	 *
	 * When `initForm()` is non-null the session starts in INIT phase and the init
	 * form is returned; its data is submitted via a later {@see self::next()} call.
	 * Otherwise `nextStep()` runs immediately and the session starts in STEPS, or
	 * DONE when the provider has no steps at all.
	 *
	 * Every call creates a new session: it never picks up one already under way. A client that comes
	 * back to a filling keeps its `resume_ref` and reads {@see self::state()} with it.
	 *
	 * @throws FormResumeNotYetActiveException when `notBefore()` is in the future
	 */
	public function init(): FormSessionStep
	{
		$provider   = $this->provider;
		$not_before = $provider->notBefore();

		if (null !== $not_before && new DateTimeImmutable() < $not_before) {
			throw new FormResumeNotYetActiveException();
		}

		$deadline   = $provider->deadline();
		$expires_at = null !== $deadline
			? \min(\time() + $provider->resumeTTL(), $deadline->getTimestamp())
			: null;

		$progress = new FormResumeProgress();
		$progress->setStepIndex(0);

		$cleaned_fd = new FormDataClean();
		$form       = $provider::initForm();

		if (null !== $form) {
			// INIT phase: the init data is submitted separately via next().
			$phase = FormResumePhase::INIT;
		} else {
			$form  = $provider->nextStep($cleaned_fd, $progress);
			$phase = null === $form ? FormResumePhase::DONE : FormResumePhase::STEPS;
		}

		$progress->setPhase($phase);

		$session = new FormSession(
			Keys::id32(),
			$this->provider_class,
			$this->provider_name,
			$this->route_key,
			$provider->resumeScope()->value,
			$provider->resumeScope()->resolveId($this->ri->getContext()),
			\time(),
			$expires_at,
			$phase,
			$cleaned_fd,
			$progress,
		);

		FormSessionStore::save($session, $provider->resumeTTL());

		return $this->step($session, $form);
	}

	/**
	 * Returns the current step form without advancing.
	 *
	 * @throws NotFoundException          when the session is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public function state(): FormSessionStep
	{
		$session = $this->load();

		return $this->step($session, $this->currentForm($session));
	}

	/**
	 * Validates the current step (or init form) and advances.
	 *
	 * In INIT phase the init form is validated and the session moves to STEPS (or
	 * DONE) without incrementing the step index, so `nextStep()` still receives 0.
	 * In STEPS phase the current step is validated, the index incremented, and
	 * `nextStep()` called for the next form.
	 *
	 * @throws BadRequestException        when the session is already complete
	 * @throws NotFoundException          when the session is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 * @throws InvalidFormException       when form validation fails
	 * @throws RuntimeException           when the provider yields no form for the current step
	 */
	public function next(): FormSessionStep
	{
		$session = $this->loadUnfinished();
		$form    = $this->requireCurrentForm($session);
		$unsafe  = $this->ri->getContext()->getRequest()->getUnsafeFormData();

		if (FormResumePhase::INIT === $session->phase) {
			$session->cleaned_fd->merge($form->validate($unsafe, $session->cleaned_fd));
		} else {
			$validated = $form->validate($unsafe, $session->cleaned_fd);

			if ($this->provider->isReversible()) {
				// Snapshot BEFORE merging and advancing, so back() restores the exact
				// state that produced the current form.
				$session->history[] = [
					'cleaned_fd'     => $session->cleaned_fd->toArray(),
					'progress_state' => $session->progress->toArray(),
				];
			}

			$session->cleaned_fd->merge($validated);

			// Tell the provider which step it is now answering for.
			$session->progress->setStepIndex($session->progress->getStepIndex() + 1);
		}

		$next_form      = $this->provider->nextStep($session->cleaned_fd, $session->progress);
		$session->phase = null === $next_form ? FormResumePhase::DONE : FormResumePhase::STEPS;
		$session->progress->setPhase($session->phase);

		FormSessionStore::save($session, $this->provider->resumeTTL());

		return $this->step($session, $next_form);
	}

	/**
	 * Reverts the session to the previous step.
	 *
	 * History is pushed by {@see self::next()} only outside the INIT phase, so submitting the init form
	 * leaves nothing to go back to: a `back` right after it throws `OZ_FORM_SESSION_NO_HISTORY`.
	 *
	 * @throws BadRequestException        when the provider is not reversible or there is no history
	 * @throws NotFoundException          when the session is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public function back(): FormSessionStep
	{
		$session = $this->load();

		if (!$this->provider->isReversible()) {
			throw new BadRequestException('OZ_FORM_SESSION_NOT_REVERSIBLE');
		}

		if (empty($session->history)) {
			throw new BadRequestException('OZ_FORM_SESSION_NO_HISTORY');
		}

		$snapshot = \array_pop($session->history);

		$session->cleaned_fd = new FormDataClean($snapshot['cleaned_fd'] ?? []);
		$session->progress   = new FormResumeProgress($snapshot['progress_state'] ?? []);
		// Back is never from DONE, so the restored state is always a step.
		$session->phase = FormResumePhase::STEPS;

		FormSessionStore::save($session, $this->provider->resumeTTL());

		return $this->step($session, $this->provider->nextStep($session->cleaned_fd, $session->progress));
	}

	/**
	 * Discards the session.
	 *
	 * @throws NotFoundException          when the session is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public function cancel(): void
	{
		FormSessionStore::drop($this->load()->ref);
	}

	/**
	 * Evaluates the server-only conditions of the current step.
	 *
	 * The step has not been submitted yet, so its input is cleaned by type, field
	 * by field, on top of the accumulated data: conditions then compare against the
	 * same typed values validation would produce. Input that is missing or fails its
	 * type is left out, since a step being filled may be incomplete. Custom
	 * validators are not run, and file fields are never cleaned here (that would
	 * store the upload). Rules reading the unsafe side still see the raw payload.
	 *
	 * Fields, fieldsets and rule sets are all reported by ref; a shown fieldset's own
	 * `expect()` and `ensure()` sets come after the form's. A switcher's branches holding a
	 * secret are answered in `switchers`, by their ref (`<field>@switch[<n>]`), so a client
	 * knows which type such a field takes.
	 *
	 * @return array{
	 *  visibility: array<string, bool>,
	 *  fieldsets: array<string, bool>,
	 *  expect: list<array{ref: string, passes: bool, message: mixed}>,
	 *  ensure: list<array{ref: string, passes: bool, message: mixed}>,
	 *  switchers: list<array{ref: string, passes: bool, message: mixed}>
	 * }
	 *
	 * @throws BadRequestException        when the session is complete
	 * @throws NotFoundException          when the session is not found
	 * @throws ForbiddenException         when the caller does not own the session
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 * @throws RuntimeException           when the provider yields no form for the current step
	 */
	public function evaluate(): array
	{
		$session = $this->loadUnfinished();
		$form    = $this->requireCurrentForm($session);
		$ctx     = new FormValidationContext(
			$this->ri->getContext()->getRequest()->getUnsafeFormData(),
			new FormDataClean($session->cleaned_fd->toArray())
		);

		self::cleanPartially($form->getFields(), $ctx);

		$visibility = [];
		$fieldsets  = [];
		$expect     = self::evaluateServerOnly($form->getPreValidationRules(), $ctx);
		$ensure     = self::evaluateServerOnly($form->getPostValidationRules(), $ctx);
		$switchers  = self::evaluateSwitchers($form->getFields(), $ctx);

		foreach ($form->getFields() as $field) {
			if ($field->getIf()?->isServerOnly()) {
				$visibility[$field->getRef()] = $field->isEnabled($ctx);
			}
		}

		foreach ($form->getFieldsets() as $fieldset) {
			if ($fieldset->getIf()?->isServerOnly()) {
				$fieldsets[$fieldset->getSelfRef()] = $fieldset->isEnabled($ctx);
			}

			// Null when the fieldset condition fails: its fields are skipped too.
			$built = $fieldset->build($ctx);

			if (null === $built) {
				continue;
			}

			// A fieldset's own rules are sent with it, so they are answered here too, after the form's
			// (each result names its ref): its expect() before its fields are cleaned, its ensure() after.
			\array_push($expect, ...self::evaluateServerOnly($built->getPreValidationRules(), $ctx));

			self::cleanPartially($built->getFields(), $ctx);

			foreach ($built->getFields() as $field) {
				if ($field->getIf()?->isServerOnly()) {
					$visibility[$field->getRef()] = $field->isEnabled($ctx);
				}
			}

			\array_push($ensure, ...self::evaluateServerOnly($built->getPostValidationRules(), $ctx));
			\array_push($switchers, ...self::evaluateSwitchers($built->getFields(), $ctx));
		}

		return [
			'visibility' => $visibility,
			'fieldsets'  => $fieldsets,
			'expect'     => $expect,
			'ensure'     => $ensure,
			'switchers'  => $switchers,
		];
	}

	/**
	 * The server-only branches of the switchers among the given fields.
	 *
	 * @param array<string, Field> $fields
	 *
	 * @return list<array{ref: string, passes: bool, message: mixed}>
	 */
	private static function evaluateSwitchers(array $fields, FormValidationContext $ctx): array
	{
		$results = [];

		foreach ($fields as $field) {
			$type = $field->getType();

			if ($type instanceof TypesSwitcher) {
				\array_push($results, ...self::evaluateServerOnly($type->getConditions(), $ctx));
			}
		}

		return $results;
	}

	/**
	 * @param array<RuleSet> $rule_sets
	 *
	 * @return list<array{ref: string, passes: bool, message: mixed}>
	 */
	private static function evaluateServerOnly(array $rule_sets, FormValidationContext $ctx): array
	{
		$results = [];

		foreach ($rule_sets as $rule_set) {
			if (!$rule_set->isServerOnly()) {
				continue;
			}

			$passes = $rule_set->check($ctx);
			$msg    = $passes ? null : $rule_set->getViolationMessage();

			$results[] = [
				'ref'     => $rule_set->getRef(),
				'passes'  => $passes,
				'message' => $msg instanceof I18nMessage ? $msg->toArray() : $msg,
			];
		}

		return $results;
	}

	/**
	 * Loads the session named by the request header and checks that the caller may
	 * drive it: same standalone provider name (when any), same scope owner, same
	 * provider and route binding (see {@see FormSession::assertUsableBy()}), and not
	 * past its deadline.
	 *
	 * @throws BadRequestException        when the header is absent or empty
	 * @throws NotFoundException          when the session is not found
	 * @throws ForbiddenException         when the provider name, scope owner, provider or route differs
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	private function load(): FormSession
	{
		$header_name = Settings::get('oz.request', 'OZ_FORM_RESUME_REF_HEADER_NAME');
		$resume_ref  = $this->ri->getContext()->getRequest()->getHeaderLine($header_name);

		if ('' === $resume_ref) {
			throw new BadRequestException('OZ_FORM_SESSION_REF_MISSING');
		}

		$session = FormSessionStore::load($resume_ref);

		if (null === $session) {
			throw new NotFoundException('OZ_FORM_SESSION_NOT_FOUND', ['ref' => $resume_ref]);
		}

		if (null !== $this->provider_name && $session->provider_name !== $this->provider_name) {
			throw new ForbiddenException('OZ_FORM_SESSION_PROVIDER_MISMATCH');
		}

		if ($session->scope_id !== $this->provider->resumeScope()->resolveId($this->ri->getContext())) {
			throw new ForbiddenException('OZ_FORM_SESSION_ACCESS_DENIED');
		}

		$session->assertUsableBy($this->provider_class, $this->route_key);

		if ($session->isExpired()) {
			throw new FormResumeExpiredException();
		}

		return $session;
	}

	/**
	 * Like {@see self::load()}, but rejects a session that is already DONE.
	 *
	 * @throws BadRequestException when the session is already complete
	 */
	private function loadUnfinished(): FormSession
	{
		$session = $this->load();

		if ($session->isDone()) {
			throw new BadRequestException('OZ_FORM_SESSION_ALREADY_DONE');
		}

		return $session;
	}

	/**
	 * Re-derives the current form from the session.
	 *
	 * The form is never stored; it is always re-derived to keep the cache lean and
	 * to guarantee determinism.
	 */
	private function currentForm(FormSession $session): ?Form
	{
		return match ($session->phase) {
			FormResumePhase::INIT  => $this->provider::initForm(),
			FormResumePhase::STEPS => $this->provider->nextStep($session->cleaned_fd, $session->progress),
			FormResumePhase::DONE  => null,
		};
	}

	/**
	 * The form of an unfinished session's current step.
	 *
	 * @throws RuntimeException when the provider yields none, which breaks the
	 *                          determinism contract of `nextStep()` / `initForm()`
	 */
	private function requireCurrentForm(FormSession $session): Form
	{
		return $this->currentForm($session) ?? throw new RuntimeException(\sprintf(
			'Provider "%s" returned no form for an unfinished session (phase "%s", step %d). '
				. 'nextStep() must be deterministic and return null only once every step is done; '
				. 'initForm() must not change while sessions are open.',
			$this->provider_class,
			$session->phase->value,
			$session->progress->getStepIndex()
		));
	}

	/**
	 * Cleans the submitted values of the given fields by type, in order, into the
	 * context's cleaned store, leaving out anything missing, disabled or invalid.
	 *
	 * @param array<string, Field> $fields
	 */
	private static function cleanPartially(array $fields, FormValidationContext $ctx): void
	{
		$unsafe  = $ctx->getUnsafeFormData();
		$cleaned = $ctx->getCleanFormData();

		foreach ($fields as $field) {
			$ref = $field->getRef();

			// Cleaning a file field would store the upload.
			if (!$unsafe->has($ref) || $field->getType() instanceof TypeFile || !$field->isEnabled($ctx)) {
				continue;
			}

			try {
				$cleaned->set($ref, $field->validateType($unsafe->get($ref), $ctx));
			} catch (RuntimeException | TypesInvalidValueException) {
				// Left out: a step being filled may be incomplete or wrong. RuntimeException
				// covers a TypesSwitcher whose discriminating field is not usable yet.
			}
		}
	}

	private function step(FormSession $session, ?Form $form): FormSessionStep
	{
		$total_steps = $this->provider->totalSteps();

		return new FormSessionStep(
			$session->ref,
			$form,
			$session->isDone(),
			$session->expires_at,
			null === $total_steps ? null : [
				'step'        => $session->progress->getStepIndex(),
				'total_steps' => $total_steps,
			],
		);
	}
}
