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
			'internal_name'   => 'OZONE\OZ\WebRoute\Services\RouteRunner',
			'is_file_service' => false,
			'can_serve_resp'  => true,
			'cross_site'      => true,
			'req_methods'     => ['GET', 'POST', 'PUT', 'DELETE']
		],
		'file'     => [
			'internal_name'   => 'OZONE\OZ\FS\Services\GetFiles',
			'is_file_service' => true,
			'can_serve_resp'  => true,
			'cross_site'      => true,
			'req_methods'     => ['GET']
		],
		'captcha'     => [
			'internal_name'   => 'OZONE\OZ\Authenticator\Services\CaptchaCode',
			'is_file_service' => true,
			'can_serve_resp'  => true,
			'cross_site'      => true,
			'require_client'  => false,
			'req_methods'     => ['GET']
		],
		'qrcode'      => [
			'internal_name'   => 'OZONE\OZ\Authenticator\Services\QrCodeCode',
			'is_file_service' => true,
			'can_serve_resp'  => true,
			'cross_site'      => true,
			'require_client'  => false,
			'req_methods'     => ['GET']
		],
		'tnet'        => [
			'internal_name'   => 'OZONE\OZ\User\Services\TestNet',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => true,
			'req_methods'     => ['POST', 'GET']
		],
		'signup'      => [
			'internal_name'   => 'OZONE\OZ\User\Services\Signup',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => true,
			'req_methods'     => ['POST']
		],
		'upicedit'    => [
			'internal_name'   => 'OZONE\OZ\User\Services\UserPicEdit',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => true,
			'req_methods'     => ['POST']
		],
		'login'       => [
			'internal_name'   => 'OZONE\OZ\User\Services\Login',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => true,
			'req_methods'     => ['POST']
		],
		'logout'      => [
			'internal_name'   => 'OZONE\OZ\User\Services\Logout',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => true,
			'req_methods'     => ['POST', 'GET']
		]
	];