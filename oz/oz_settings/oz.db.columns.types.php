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

use OZONE\OZ\Columns\Types\TypeCC2;
use OZONE\OZ\Columns\Types\TypeEmail;
use OZONE\OZ\Columns\Types\TypeFile;
use OZONE\OZ\Columns\Types\TypeGender;
use OZONE\OZ\Columns\Types\TypePassword;
use OZONE\OZ\Columns\Types\TypePhone;
use OZONE\OZ\Columns\Types\TypeUrl;
use OZONE\OZ\Columns\Types\TypeUserName;

return [
	TypePhone::NAME     => TypePhone::class,
	TypeEmail::NAME     => TypeEmail::class,
	TypeUrl::NAME       => TypeUrl::class,
	TypeUserName::NAME  => TypeUserName::class,
	TypePassword::NAME  => TypePassword::class,
	TypeCC2::NAME       => TypeCC2::class,
	TypeGender::NAME    => TypeGender::class,
	TypeFile::NAME      => TypeFile::class,
];
