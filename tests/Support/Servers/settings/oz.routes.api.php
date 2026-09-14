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

use OZONE\Tests\Support\Servers\RuntimeProbe;

// The probe routes the worker servers of tests/Runtime/Servers/ answer.
return [
	RuntimeProbe::class => true,
];
