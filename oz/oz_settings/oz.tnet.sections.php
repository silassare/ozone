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

use OZONE\Core\Auth\Interfaces\TNetSectionInterface;
use OZONE\Core\Auth\TNet\ReaderSection;

/**
 * The named sections `tnet` answers beside its own fields: a section name mapped to a class
 * implementing {@see TNetSectionInterface}.
 *
 * A plugin or a project adds its own here, and replaces or removes one by its name (`null`
 * removes it). `GET /tnet?sections=a,b` answers those only.
 */
return [
	ReaderSection::NAME => ReaderSection::class,
];
