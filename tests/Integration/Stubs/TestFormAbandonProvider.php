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

use Override;
use OZONE\Core\Forms\AbstractResumableFormProvider;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormResumeProgress;
use OZONE\Core\Http\Enums\RequestScope;

/**
 * Minimal provider used by abandon/expiry integration tests.
 *
 * - requiresRealContext() = false  (usable from standalone /form/* endpoints)
 * - resumeScope()         = HOST   (no cookie management needed in tests)
 * - resumeTTL()           = 1      (1 second -- expires almost immediately)
 * - nextStep()            = null   (zero-step flow -- DONE on init)
 * - onAbandon()           writes a flag file so tests can verify it was called
 */
final class TestFormAbandonProvider extends AbstractResumableFormProvider
{
	public const PROVIDER_NAME = 'test-abandon';

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
	public function resumeTTL(): int
	{
		return 1;
	}

	#[Override]
	public function nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form
	{
		// Zero-step provider: done immediately.
		return null;
	}

	#[Override]
	public static function onAbandon(array $session): void
	{
		\file_put_contents('__PLH_FLAG_FILE__', '1');
	}
}
