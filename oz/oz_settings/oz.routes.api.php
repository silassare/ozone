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

use OZONE\OZ\Auth\Services\AuthEmailService;
use OZONE\OZ\Auth\Services\AuthPhoneService;
use OZONE\OZ\Auth\Services\AuthService;
use OZONE\OZ\FS\Services\UploadFiles;
use OZONE\OZ\Services\CaptchaCode;
use OZONE\OZ\Services\LinkTo;
use OZONE\OZ\Services\QRCode;
use OZONE\OZ\Users\Services\AccountRecovery;
use OZONE\OZ\Users\Services\Login;
use OZONE\OZ\Users\Services\Logout;
use OZONE\OZ\Users\Services\Password;
use OZONE\OZ\Users\Services\SignUp;
use OZONE\OZ\Users\Services\TNet;
use OZONE\OZ\Users\Services\UserPicEdit;

return [
	UploadFiles::class      => true,
	CaptchaCode::class      => true,
	QRCode::class           => true,
	LinkTo::class           => true,
	TNet::class             => true,
	SignUp::class           => true,
	Login::class            => true,
	Logout::class           => true,
	UserPicEdit::class      => true,
	Password::class         => true,
	AccountRecovery::class  => true,
	AuthService::class      => true,
	AuthPhoneService::class => true,
	AuthEmailService::class => true,
];
