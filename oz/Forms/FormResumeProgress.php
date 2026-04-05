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

use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Enums\FormResumePhase;
use PHPUtils\Store\Store;

/**
 * Class FormResumeProgress.
 *
 * Typed wrapper around the private provider state stored in a resumable form session.
 *
 * The framework owns all keys prefixed with `_` (e.g. `_step_index`, `_phase`).
 * Providers may read and write any other key for their own bookkeeping.
 *
 * @extends Store<array>
 */
final class FormResumeProgress extends Store
{
	private const STEP_INDEX_KEY = '_step_index';
	private const PHASE_KEY      = '_phase';

	/**
	 * Creates a FormResumeProgress from a raw state array.
	 *
	 * @param array $state the backing array (from session cache `progress_state`)
	 */
	public function __construct(array $state = [])
	{
		parent::__construct($state);
	}

	/**
	 * Returns the current phase of the session.
	 */
	public function getPhase(): FormResumePhase
	{
		$raw = $this->get(self::PHASE_KEY);

		return FormResumePhase::from((string) ($raw ?? FormResumePhase::STEPS->value));
	}

	/**
	 * Sets the current phase.
	 *
	 * @internal used exclusively by ResumableFormService
	 */
	public function setPhase(FormResumePhase $phase): void
	{
		$this->set(self::PHASE_KEY, $phase->value);
	}

	/**
	 * Returns the 0-based step index.
	 *
	 * - 0: first call to nextStep() (from initSession)
	 * - N: n-th call (counter starts at 1 after the first nextStep() advances it)
	 */
	public function getStepIndex(): int
	{
		return (int) $this->get(self::STEP_INDEX_KEY, 0);
	}

	/**
	 * Sets the step index.
	 *
	 * @internal used exclusively by ResumableFormService
	 */
	public function setStepIndex(int $index): void
	{
		$this->set(self::STEP_INDEX_KEY, $index);
	}

	/**
	 * Sets a provider-owned value by key.
	 *
	 * Keys starting with `_` are reserved for the framework and will throw.
	 * Use {@see self::setPhase()} and {@see self::setStepIndex()} for framework keys.
	 *
	 * @param string $key   must not start with `_`
	 * @param mixed  $value
	 *
	 * @return static
	 */
	#[Override]
	public function set(string $key, mixed $value): static
	{
		if (\str_starts_with($key, '_') && self::STEP_INDEX_KEY !== $key && self::PHASE_KEY !== $key) {
			throw new RuntimeException(\sprintf(
				'FormResumeProgress key "%s" is reserved (keys starting with "_" are for framework use only).',
				$key
			));
		}

		return parent::set($key, $value);
	}
}
