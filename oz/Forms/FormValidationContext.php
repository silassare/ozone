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

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Enums\RuleSetDataType;
use PHPUtils\Store\Store;

/**
 * Class FormValidationContext.
 *
 * Carries both sides of a validation pass: the raw request payload and the
 * cleaned data accumulated so far.
 *
 * Holding both together is what makes the {@see RuleSetDataType} tag meaningful:
 * a {@see RuleSet} declares which side it reads and {@see self::dataFor()}
 * hands it that side, so a CLEANED rule set can never be evaluated against raw
 * input by accident.
 *
 * The cleaned store is the live accumulator mutated by
 * {@see AbstractFieldContainer::checkFields()} as each field is validated, so a
 * rule reading it mid-pass only sees fields declared before the current one. The
 * context also tracks which fields of the pass are still pending, so a condition
 * reading one of them fails loudly instead of silently seeing null
 * ({@see self::assertReadable()}).
 */
final class FormValidationContext
{
	/**
	 * Refs declared in this pass and not processed yet.
	 *
	 * @var array<string, true>
	 */
	private array $pending = [];

	/**
	 * FormValidationContext constructor.
	 *
	 * @param FormData      $unsafe_fd  raw, unvalidated payload from the request
	 * @param FormDataClean $cleaned_fd live accumulator of validated values
	 */
	public function __construct(
		private readonly FormData $unsafe_fd,
		private readonly FormDataClean $cleaned_fd,
	) {}

	/**
	 * Gets the raw, unvalidated form data from the request.
	 */
	public function getUnsafeFormData(): FormData
	{
		return $this->unsafe_fd;
	}

	/**
	 * Gets the cleaned form data accumulated so far.
	 *
	 * This is the live accumulator: values appear as their fields are validated.
	 */
	public function getCleanFormData(): FormDataClean
	{
		return $this->cleaned_fd;
	}

	/**
	 * Selects the data store matching a given rule set data type.
	 *
	 * @internal used by {@see RuleSet::check()} to resolve its own operand source
	 */
	public function dataFor(RuleSetDataType $data_type): Store
	{
		return match ($data_type) {
			RuleSetDataType::UNSAFE  => $this->unsafe_fd,
			RuleSetDataType::CLEANED => $this->cleaned_fd,
		};
	}

	/**
	 * Marks refs as declared in this pass but not processed yet.
	 *
	 * @param list<string> $refs
	 *
	 * @internal used by {@see Form::validate()}
	 */
	public function markPending(array $refs): void
	{
		foreach ($refs as $ref) {
			$this->pending[$ref] = true;
		}
	}

	/**
	 * Marks refs as processed: validated, left absent, or skipped with their fieldset.
	 *
	 * @internal used by {@see Form::validate()} and {@see AbstractFieldContainer::checkFields()}
	 */
	public function markProcessed(string ...$refs): void
	{
		foreach ($refs as $ref) {
			unset($this->pending[$ref]);
		}
	}

	/**
	 * Throws when a condition reads a field this pass has not processed yet.
	 *
	 * Conditions read the cleaned store, which fills in validation order, so such a
	 * field is still empty when the condition runs: the condition would always see
	 * null. Refs outside the pass (e.g. accumulated by earlier wizard steps) are fine.
	 *
	 * @param RuleSet $condition the condition about to be evaluated
	 * @param string  $owner_ref ref of the field or fieldset the condition belongs to
	 *
	 * @throws RuntimeException
	 *
	 * @internal
	 */
	public function assertReadable(RuleSet $condition, string $owner_ref): void
	{
		foreach ($condition->getFieldRefs() as $ref) {
			if (isset($this->pending[$ref])) {
				throw new RuntimeException(\sprintf(
					'"%s" has a condition on "%s", which is validated after it, so the condition '
						. 'would always see null. Declare "%s" before "%s".',
					$owner_ref,
					$ref,
					$ref,
					$owner_ref
				));
			}
		}
	}
}
