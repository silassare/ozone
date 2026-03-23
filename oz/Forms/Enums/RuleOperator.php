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

/**
 * Enum RuleOperator.
 *
 * Supported comparison operators for {@see Rule} evaluation.
 */
enum RuleOperator: string
{
	case EQ = 'eq';

	case NEQ = 'neq';

	case GT = 'gt';

	case GTE = 'gte';

	case LT = 'lt';

	case LTE = 'lte';

	case IN = 'in';

	case NOT_IN = 'not_in';

	case IS_NULL = 'is_null';

	case IS_NOT_NULL = 'is_not_null';
}
