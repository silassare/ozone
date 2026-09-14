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

// Writes the row the database route of `make benchmark-http` reads (`/runtime-probe/db`), in the
// sandbox tests/Support/Servers/prepare.php built: the row the Laravel and Symfony applications read.

use OZONE\Core\Db\OZCountry;
use OZONE\Core\OZone;

$app = require __DIR__ . '/../../../tests/Support/Servers/app.php';

OZone::bootstrap($app);

// Writing a primary key is refused by default (TableCRUDListenerTrait); the row is keyed by its code,
// as Session allows for OZSession.
OZCountry::crud()->onBeforePKColumnWrite(static fn () => true);

OZCountry::new()
	->setCc2('BJ')
	->setCallingCode('+229')
	->setName('Benin')
	->setNameReal('Benin')
	->save();
