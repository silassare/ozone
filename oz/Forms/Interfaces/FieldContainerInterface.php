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

use OZONE\Core\Forms\Enums\RuleSetCondition;
use OZONE\Core\Forms\Field;
use OZONE\Core\Forms\Fieldset;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\RuleSet;

/**
 * Interface FieldContainerInterface.
 *
 * Shared contract implemented by both {@see Form} and
 * {@see Fieldset}. Allows {@see Field} to register
 * double-check companions and validation rules without knowing
 * whether it lives in a top-level form or in a named fieldset.
 */
interface FieldContainerInterface
{
	/**
	 * Sets the container name.
	 *
	 * The name is used as a prefix for all field references within this container
	 * and must be a valid dot-path segment.
	 *
	 * @param string $name
	 *
	 * @return static
	 */
	public function name(string $name): static;

	/**
	 * Returns the container name, or null if unnamed.
	 *
	 * @return null|string
	 */
	public function getName(): ?string;

	/**
	 * Creates or retrieves a field by name within this container.
	 *
	 * @param string $name
	 *
	 * @return Field
	 */
	public function field(string $name): Field;

	/**
	 * Retrieves a field by name, or null if not found.
	 *
	 * @param string $name
	 *
	 * @return null|Field
	 */
	public function getField(string $name): ?Field;

	/**
	 * Returns all fields registered in this container, keyed by name.
	 *
	 * @return array<string, Field>
	 */
	public function getFields(): array;

	/**
	 * Creates and registers a new pre-validation condition on this container.
	 *
	 * Pre-validation conditions run on raw (unsafe) data before any field is
	 * validated. Non-server-only rule sets may be forwarded to the client.
	 *
	 * @param RuleSetCondition $condition AND (default) or OR
	 *
	 * @return RuleSet
	 */
	public function expect(RuleSetCondition $condition = RuleSetCondition::AND): RuleSet;

	/**
	 * Creates and registers a new post-validation assertion on this container.
	 *
	 * Post-validation assertions run on cleaned data after all fields have been
	 * validated. They are server-side only and never sent to the client.
	 *
	 * @param RuleSetCondition $condition AND (default) or OR
	 *
	 * @return RuleSet
	 */
	public function ensure(RuleSetCondition $condition = RuleSetCondition::AND): RuleSet;

	/**
	 * Returns all registered pre-validation rule sets.
	 *
	 * @return list<RuleSet>
	 */
	public function getPreValidationRules(): array;

	/**
	 * Returns all registered post-validation rule sets.
	 *
	 * @return list<RuleSet>
	 */
	public function getPostValidationRules(): array;

	/**
	 * Returns the fully-qualified field reference for the given name,
	 * prepending the container's own name prefix when applicable.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public function getRef(string $name): string;
}
