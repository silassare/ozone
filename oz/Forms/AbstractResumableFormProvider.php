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

namespace OZONE\Core\Forms;

use DateTimeImmutable;
use Override;
use OZONE\Core\Forms\Interfaces\ResumableFormProviderInterface;
use OZONE\Core\Forms\Services\ResumableFormService;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Router\RouteInfo;

/**
 * Class AbstractResumableFormProvider.
 *
 * Pure-defaults base for resumable form providers. Provider resolution is handled
 * by {@see ResumableFormService} via the
 * `oz.forms.providers` settings registry — there is no static in-process registry.
 *
 * Concrete providers must implement {@see self::getName()} and
 * {@see self::nextStep()}. All other methods have sensible defaults and may be
 * overridden as needed.
 *
 * Usage example:
 *
 * ```php
 * class MyProvider extends AbstractResumableFormProvider
 * {
 *     public static function getName(): string { return 'my-survey'; }
 *
 *     public function nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form
 *     {
 *         // Return next form or null when done
 *     }
 * }
 * ```
 *
 * Register in `app/settings/oz.forms.providers.php`:
 *
 * ```php
 * return [
 *     MyProvider::PROVIDER_NAME => MyProvider::class,
 * ];
 * ```
 */
abstract class AbstractResumableFormProvider implements ResumableFormProviderInterface
{
	/**
	 * The RouteInfo for the current handler invocation.
	 *
	 * Set by the default {@see self::instance()} implementation. Providers
	 * that need access to the context, request, or auth state use `$this->ri`.
	 * Providers that do not need it may ignore this property.
	 */
	protected RouteInfo $ri;

	/**
	 * {@inheritDoc}
	 *
	 * Default implementation: constructs a new instance of the concrete class and
	 * injects `$ri`. Providers may override this to perform additional setup.
	 */
	#[Override]
	public static function instance(RouteInfo $ri): static
	{
		$instance     = new static();
		$instance->ri = $ri;

		return $instance;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function initForm(): ?Form
	{
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function resumeScope(): RequestScope
	{
		return RequestScope::STATE;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function resumeTTL(): int
	{
		return 3600;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function totalSteps(): ?int
	{
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isReversible(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function notBefore(): ?DateTimeImmutable
	{
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function deadline(): ?DateTimeImmutable
	{
		return null;
	}
}
