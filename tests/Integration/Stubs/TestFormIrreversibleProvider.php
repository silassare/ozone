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

namespace __PLH_NAMESPACE__;

use DateTimeImmutable;
use Override;
use OZONE\Core\Forms\AbstractResumableFormProvider;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormResumeProgress;
use OZONE\Core\Http\Enums\RequestScope;

/**
 * Single-step irreversible provider used by integration tests.
 *
 * - requiresRealContext() = false  (usable from standalone /form/* endpoints)
 * - resumeScope()         = HOST   (no cookie management needed in tests)
 * - isReversible()        = false  (POST /back must return error=1)
 * - notBefore()           = reads a UNIX timestamp from NOT_BEFORE_FILE; null when file absent
 * - deadline()            = reads a UNIX timestamp from DEADLINE_FILE;   null when file absent
 * - nextStep()            = returns a one-field form at step 0, null (done) thereafter
 */
final class TestFormIrreversibleProvider extends AbstractResumableFormProvider
{
	public const PROVIDER_NAME = 'test-irreversible';

	/**
	 * Absolute path to the file that activates notBefore().
	 * Write a UNIX timestamp string into this file before POST /init
	 * to make the endpoint reject the request with FormResumeNotYetActiveException.
	 * Delete the file (or leave it absent) to disable the constraint.
	 */
	private const NOT_BEFORE_FILE = '__PLH_NOT_BEFORE_FILE__';

	/**
	 * Absolute path to the file that activates deadline().
	 * Write a UNIX timestamp string into this file before POST /init
	 * to store a past/future deadline in the session.
	 * Delete the file (or leave it absent) to disable the constraint.
	 */
	private const DEADLINE_FILE = '__PLH_DEADLINE_FILE__';

	#[Override]
	public static function getName(): string
	{
		return self::PROVIDER_NAME;
	}

	#[Override]
	public static function requiresRealContext(): bool
	{
		return false;
	}

	#[Override]
	public function resumeScope(): RequestScope
	{
		return RequestScope::HOST;
	}

	#[Override]
	public function isReversible(): bool
	{
		return false;
	}

	#[Override]
	public function notBefore(): ?DateTimeImmutable
	{
		$path = self::NOT_BEFORE_FILE;

		if ('' === $path || !\file_exists($path)) {
			return null;
		}

		$ts = (int) \trim((string) \file_get_contents($path));

		return $ts > 0 ? new DateTimeImmutable('@' . $ts) : null;
	}

	#[Override]
	public function deadline(): ?DateTimeImmutable
	{
		$path = self::DEADLINE_FILE;

		if ('' === $path || !\file_exists($path)) {
			return null;
		}

		$ts = (int) \trim((string) \file_get_contents($path));

		return $ts > 0 ? new DateTimeImmutable('@' . $ts) : null;
	}

	#[Override]
	public function nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form
	{
		if ($progress->getStepIndex() > 0) {
			// Single-step provider: done after the first step.
			return null;
		}

		$form = new Form();
		$form->string('action', true);

		return $form;
	}
}
