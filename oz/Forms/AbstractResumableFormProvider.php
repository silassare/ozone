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
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Interfaces\ResumableFormProviderInterface;
use OZONE\Core\Http\Enums\RequestScope;

/**
 * Class AbstractResumableFormProvider.
 *
 * Base class for resumable form providers. Provides a static registry so that
 * providers can be resolved from the URL's `:provider` segment without
 * exposing fully-qualified class names in requests.
 *
 * Usage:
 *
 * ```php
 * class MyProvider extends AbstractResumableFormProvider
 * {
 *     public static function providerRef(): string { return 'my-survey'; }
 *
 *     public function nextStep(FormData $progress): ?Form
 *     {
 *         // Return next form or null when done
 *     }
 * }
 *
 * // Register once, e.g. in a BootHookReceiverInterface::boot() method:
 * AbstractResumableFormProvider::register(MyProvider::class);
 * ```
 */
abstract class AbstractResumableFormProvider implements ResumableFormProviderInterface
{
	/**
	 * Registry: providerRef -> class FQN.
	 *
	 * @var array<string, class-string<ResumableFormProviderInterface>>
	 */
	private static array $registry = [];

	/**
	 * Registers a provider class in the global registry.
	 *
	 * The class must implement {@see ResumableFormProviderInterface}.
	 * Registering a different class under the same ref as an already-registered
	 * one throws a {@see RuntimeException}.
	 *
	 * @param class-string<ResumableFormProviderInterface> $class FQN of the provider class
	 *
	 * @throws RuntimeException when the same ref is already registered by a different class
	 */
	public static function register(string $class): void
	{
		$ref = $class::providerRef();

		if (isset(self::$registry[$ref]) && self::$registry[$ref] !== $class) {
			throw new RuntimeException(\sprintf(
				'Resumable form provider ref "%s" is already registered by "%s". Cannot re-register with "%s".',
				$ref,
				self::$registry[$ref],
				$class
			));
		}

		self::$registry[$ref] = $class;
	}

	/**
	 * Resolves a provider ref to a fresh provider instance.
	 *
	 * @param string $ref The value of the `:provider` URL segment
	 *
	 * @return ResumableFormProviderInterface
	 *
	 * @throws NotFoundException when no provider is registered for `$ref`
	 */
	public static function resolve(string $ref): ResumableFormProviderInterface
	{
		if (!isset(self::$registry[$ref])) {
			throw new NotFoundException('OZ_FORM_PROVIDER_NOT_FOUND', ['ref' => $ref]);
		}

		$class = self::$registry[$ref];

		return new $class();
	}

	/**
	 * Clears the registry.
	 *
	 * Intended for test isolation only - do not call in production code.
	 *
	 * @internal
	 */
	public static function clearRegistry(): void
	{
		self::$registry = [];
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
	public function initForm(): ?Form
	{
		return null;
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
