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

use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\RuleSet;

/**
 * Enum RuleSetContext.
 *
 * Indicates what kind of {@see FormData} a {@see RuleSet}
 * is evaluated against:
 *
 * - UNSAFE: raw input data before any field validation (set by {@see Form::expect()}).
 * - CLEANED: validated and transformed data after field validation (set by {@see Form::ensure()}).
 *
 * This is used for dev-time tracing to flag likely mismatches between rules and evaluation context.
 *
 * @internal Set only by the framework via {@see RuleSet::create()}.
 *           Developer code should use {@code new RuleSet()} which defaults to AND + UNSAFE.
 */
enum RuleSetContext: string
{
	case UNSAFE = 'unsafe';

	case CLEANED = 'cleaned';
}
