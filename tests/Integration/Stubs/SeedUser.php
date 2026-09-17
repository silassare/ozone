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

// Run inside a test project (`php seed_user.php`): creates a country and a user with the ORM, as an
// application would, and prints the user id.

use OZONE\Core\Db\OZCountriesQuery;
use OZONE\Core\Db\OZCountry;
use OZONE\Core\Db\OZUser;
use OZONE\Core\OZone;

require __DIR__ . '/app/boot.php';

OZone::bootstrap(require __DIR__ . '/app/app.php');

// A primary key is not written through an entity (PK_COLUMN_WRITE_REFUSED): the row goes through the
// query builder, as seeding does.
(new OZCountriesQuery())->insertMulti([[
	OZCountry::COL_CC2          => 'BJ',
	OZCountry::COL_NAME         => 'Benin',
	OZCountry::COL_NAME_REAL    => 'Benin',
	OZCountry::COL_CALLING_CODE => '229',
]])->execute();

$user = new OZUser();

$user->setCivility('Mx')
	->setDisplayName('Ada Admin')
	->setFirstName('Ada')
	->setLastName('Admin')
	->setEmail('__PLH_EMAIL__')
	->setGender('None')
	->setBirthDate('1990-01-01')
	->setPass('a-strong-password')
	->setCc2('BJ')
	->save();

echo $user->getID();
