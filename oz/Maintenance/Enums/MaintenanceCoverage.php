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

namespace OZONE\Core\Maintenance\Enums;

/**
 * Enum MaintenanceCoverage.
 *
 * Which requests a maintenance holds: those of every scope, of the API scopes, of the web scopes, or
 * of the scopes it names.
 */
enum MaintenanceCoverage: string
{
	case ALL    = 'all';
	case API    = 'api';
	case WEB    = 'web';
	case SCOPES = 'scopes';
}
