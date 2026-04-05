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

/**
 * Enum FormResumePhase.
 *
 * Tracks the current phase of a resumable form session.
 */
enum FormResumePhase: string
{
	case INIT  = 'init';
	case STEPS = 'steps';
	case DONE  = 'done';
}
