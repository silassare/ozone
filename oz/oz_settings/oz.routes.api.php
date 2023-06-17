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

use OZONE\Core\Auth\Services\AuthService;
use OZONE\Core\Auth\Services\EmailVerificationAuthService;
use OZONE\Core\Auth\Services\PhoneVerificationAuthService;
use OZONE\Core\FS\Services\UploadFiles;
use OZONE\Core\Services\CaptchaCode;
use OZONE\Core\Services\LinkTo;
use OZONE\Core\Services\QRCode;
use OZONE\Core\Users\Services\AccountRecovery;
use OZONE\Core\Users\Services\Login;
use OZONE\Core\Users\Services\Logout;
use OZONE\Core\Users\Services\Password;
use OZONE\Core\Users\Services\SignUp;
use OZONE\Core\Users\Services\TNet;
use OZONE\Core\Users\Services\UserPicEdit;

return [
	UploadFiles::class                  => true,
	CaptchaCode::class                  => true,
	QRCode::class                       => true,
	LinkTo::class                       => true,
	TNet::class                         => true,
	SignUp::class                       => true,
	Login::class                        => true,
	Logout::class                       => true,
	UserPicEdit::class                  => true,
	Password::class                     => true,
	AccountRecovery::class              => true,
	AuthService::class                  => true,
	PhoneVerificationAuthService::class => true,
	EmailVerificationAuthService::class => true,
];
