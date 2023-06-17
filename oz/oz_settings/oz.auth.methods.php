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

use OZONE\Core\Auth\AuthMethodType;
use OZONE\Core\Auth\Methods\ApiKeyHeaderAuth;
use OZONE\Core\Auth\Methods\BasicAuth;
use OZONE\Core\Auth\Methods\BearerAuth;
use OZONE\Core\Auth\Methods\DigestAuth;
use OZONE\Core\Auth\Methods\DigestRFC2617Auth;
use OZONE\Core\Auth\Methods\SessionAuth;

return [
	AuthMethodType::BASIC->value           => BasicAuth::class,
	AuthMethodType::BEARER->value          => BearerAuth::class,
	AuthMethodType::DIGEST->value          => DigestAuth::class,
	AuthMethodType::DIGEST_RFC_2617->value => DigestRFC2617Auth::class,
	AuthMethodType::SESSION->value         => SessionAuth::class,
	AuthMethodType::API_KEY_HEADER->value  => ApiKeyHeaderAuth::class,
];
