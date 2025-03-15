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

use OZONE\Core\Auth\Services\AccountRecovery;
use OZONE\Core\Auth\Services\AuthService;
use OZONE\Core\Auth\Services\EmailVerificationService;
use OZONE\Core\Auth\Services\Login;
use OZONE\Core\Auth\Services\Logout;
use OZONE\Core\Auth\Services\Password;
use OZONE\Core\Auth\Services\PhoneVerificationService;
use OZONE\Core\Auth\Services\TNet;
use OZONE\Core\FS\Services\UploadFiles;
use OZONE\Core\Services\CaptchaCode;
use OZONE\Core\Services\LinkTo;
use OZONE\Core\Services\QRCode;
use OZONE\Core\Users\Services\SignUp;

return [
	UploadFiles::class                  => true,
	CaptchaCode::class                  => true,
	QRCode::class                       => true,
	LinkTo::class                       => true,
	TNet::class                         => true,
	SignUp::class                       => true,
	Login::class                        => true,
	Logout::class                       => true,
	Password::class                     => true,
	AccountRecovery::class              => true,
	AuthService::class                  => true,
	PhoneVerificationService::class     => true,
	EmailVerificationService::class     => true,
];
