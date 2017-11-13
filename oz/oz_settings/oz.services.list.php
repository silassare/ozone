<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	return [
		'oz_web_route' => [
			'service_class'   => 'OZONE\OZ\WebRoute\Services\RouteRunner',
			'is_file_service' => false,
			'can_serve_resp'  => true,
			'cross_site'      => true,
			'request_methods'     => ['GET', 'POST', 'PUT', 'DELETE']
		],
		'file'     => [
			'service_class'   => 'OZONE\OZ\FS\Services\GetFiles',
			'is_file_service' => true,
			'can_serve_resp'  => true,
			'cross_site'      => true,
			'request_methods'     => ['GET']
		],
		'captcha'     => [
			'service_class'   => 'OZONE\OZ\Authenticator\Services\CaptchaCode',
			'is_file_service' => true,
			'can_serve_resp'  => true,
			'cross_site'      => true,
			'require_client'  => false,
			'request_methods'     => ['GET']
		],
		'qrcode'      => [
			'service_class'   => 'OZONE\OZ\Authenticator\Services\QrCodeCode',
			'is_file_service' => true,
			'can_serve_resp'  => true,
			'cross_site'      => true,
			'require_client'  => false,
			'request_methods'     => ['GET']
		],
		'tnet'        => [
			'service_class'   => 'OZONE\OZ\User\Services\TestNet',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => true,
			'request_methods'     => ['POST', 'GET']
		],
		'signup'      => [
			'service_class'   => 'OZONE\OZ\User\Services\Signup',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => true,
			'request_methods'     => ['POST']
		],
		'upicedit'    => [
			'service_class'   => 'OZONE\OZ\User\Services\UserPicEdit',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => true,
			'request_methods'     => ['POST']
		],
		'login'       => [
			'service_class'   => 'OZONE\OZ\User\Services\Login',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => true,
			'request_methods'     => ['POST']
		],
		'logout'      => [
			'service_class'   => 'OZONE\OZ\User\Services\Logout',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => true,
			'request_methods'     => ['POST', 'GET']
		]
	];