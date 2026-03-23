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

use OZONE\Core\Lang\I18nMessage;

/**
 * Class RuleViolation.
 *
 * Represents a failure in a {@see RuleSet} check.
 * When a {@see RuleSet} fails, it records the failing node
 * (a {@see Rule} or a nested {@see RuleSet}) and, for nested
 * failures, the cause ({@see self}) that originated the failure.
 *
 * Message resolution walks from the leaf node upward, returning
 * the first non-null message found.
 */
final class RuleViolation
{
	/**
	 * RuleViolation constructor.
	 *
	 * @param Rule|RuleSet       $node  the node that failed
	 * @param null|RuleViolation $cause the inner cause for nested rule-set failures
	 */
	public function __construct(
		private readonly Rule|RuleSet $node,
		private readonly ?self $cause = null,
	) {}

	/**
	 * Gets the failure message.
	 *
	 * For a {@see Rule} node, returns the rule's own message.
	 * For a {@see RuleSet} node, delegates to the inner {@see $cause}.
	 *
	 * @return null|I18nMessage|string
	 */
	public function getMessage(): I18nMessage|string|null
	{
		if ($this->node instanceof Rule) {
			return $this->node->message;
		}

		return $this->cause?->getMessage();
	}

	/**
	 * Gets the node that failed.
	 *
	 * @return Rule|RuleSet
	 */
	public function getNode(): Rule|RuleSet
	{
		return $this->node;
	}

	/**
	 * Gets the inner cause of this violation, if any.
	 *
	 * @return null|RuleViolation
	 */
	public function getCause(): ?self
	{
		return $this->cause;
	}
}
