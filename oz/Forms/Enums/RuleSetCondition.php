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

namespace OZONE\Core\Forms\Enums;

use OZONE\Core\Forms\Rule;
use OZONE\Core\Forms\RuleSet;

/**
 * Enum RuleSetCondition.
 *
 * Controls how children ({@see Rule} or nested {@see RuleSet})
 * are combined in a {@see RuleSet}:
 *
 * - AND: all children must pass for the rule set to pass.
 * - OR:  at least one child must pass for the rule set to pass.
 */
enum RuleSetCondition: string
{
	case AND = 'and';

	case OR = 'or';
}
