<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	defined('OZ_SELF_SECURITY_CHECK') or die;

	return [
		'files'            => [
			'provider' => 'OZONE\OZ\FS\Services\GetFiles'
		],
		'upload'            => [
			'provider' => 'OZONE\OZ\FS\Services\UploadFiles'
		],
		'captcha'          => [
			'provider' => 'OZONE\OZ\Authenticator\Services\CaptchaCode'
		],
		'qrcode'           => [
			'provider' => 'OZONE\OZ\Authenticator\Services\QRCode'
		],
		'tnet'             => [
			'provider' => 'OZONE\OZ\User\Services\TNet'
		],
		'signup'           => [
			'provider' => 'OZONE\OZ\User\Services\SignUp'
		],
		'login'            => [
			'provider' => 'OZONE\OZ\User\Services\Login'
		],
		'logout'           => [
			'provider' => 'OZONE\OZ\User\Services\Logout'
		],
		'upicedit'         => [
			'provider' => 'OZONE\OZ\User\Services\UserPicEdit'
		],
		'password'         => [
			'provider' => 'OZONE\OZ\User\Services\Password'
		],
		'account-auth'     => [
			'provider' => 'OZONE\OZ\Authenticator\Services\AccountAuth'
		],
		'account-recovery' => [
			'provider' => 'OZONE\OZ\User\Services\AccountRecovery'
		]
	];