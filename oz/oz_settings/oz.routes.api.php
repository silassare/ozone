<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

\defined('OZ_SELF_SECURITY_CHECK') || die;

return [
	'OZONE\OZ\FS\Services\GetFiles'               => true,
	'OZONE\OZ\FS\Services\UploadFiles'            => true,
	'OZONE\OZ\Authenticator\Services\CaptchaCode' => true,
	'OZONE\OZ\Authenticator\Services\QRCode'      => true,
	'OZONE\OZ\User\Services\TNet'                 => true,
	'OZONE\OZ\User\Services\SignUp'               => true,
	'OZONE\OZ\User\Services\Login'                => true,
	'OZONE\OZ\User\Services\Logout'               => true,
	'OZONE\OZ\User\Services\UserPicEdit'          => true,
	'OZONE\OZ\User\Services\Password'             => true,
	'OZONE\OZ\User\Services\SessionShare'         => true,
	'OZONE\OZ\User\Services\AccountRecovery'      => true,
];
