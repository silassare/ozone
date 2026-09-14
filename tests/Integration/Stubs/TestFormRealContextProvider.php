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
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormDataClean;
use OZONE\Core\Forms\Resume\AbstractResumableFormProvider;
use OZONE\Core\Forms\Resume\FormResumeProgress;

/**
 * Minimal resumable form provider that requires real context.
 *
 * requiresRealContext() inherits true from AbstractResumableFormProvider.
 * Used by integration tests to verify that the standalone /form/* endpoints
 * reject providers that require a real matched-route context.
 */
final class TestFormRealContextProvider extends AbstractResumableFormProvider
{
	public const PROVIDER_NAME = 'test-real-ctx';

	#[Override]
	public static function getName(): string
	{
		return self::PROVIDER_NAME;
	}

	// requiresRealContext() intentionally not overridden: inherits true.

	#[Override]
	public function nextStep(FormDataClean $cleaned_fd, FormResumeProgress $progress): ?Form
	{
		return null;
	}
}
