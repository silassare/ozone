<?php
	/**
	 * This file is part of the Ozone package.
	 *
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	\OZONE\OZ\Core\OZoneSettings::set( 'oz.services.list', array(

		'getfile'  => array(
			'internal_name'   => 'OZONE\OZ\FS\Services\GetFiles',
			'is_file_service' => true,
			'can_serve_resp'  => true,
			'cross_site'      => false,
			'req_methods'     => [ 'GET' ]
		),
		'captcha'  => array(
			'internal_name'   => 'OZONE\OZ\FS\Services\CaptchaCode',
			'is_file_service' => true,
			'can_serve_resp'  => true,
			'cross_site'      => false,
			'require_client'  => false,
			'req_methods'     => [ 'GET' ]
		),

		'tnet'     => array(
			'internal_name'   => 'OZONE\OZ\User\Services\TestNet',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => false,
			'req_methods'     => [ 'POST', 'GET' ]
		),
		'signin'   => array(
			'internal_name'   => 'OZONE\OZ\User\Services\Signin',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => false,
			'req_methods'     => [ 'POST' ]
		),
		'upicedit' => array(
			'internal_name'   => 'OZONE\OZ\User\Services\UserPicEdit',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => false,
			'req_methods'     => [ 'POST' ]
		),
		'login'    => array(
			'internal_name'   => 'OZONE\OZ\User\Services\Login',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => false,
			'req_methods'     => [ 'POST' ]
		),
		'logout'   => array(
			'internal_name'   => 'OZONE\OZ\User\Services\Logout',
			'is_file_service' => false,
			'can_serve_resp'  => false,
			'cross_site'      => false,
			'req_methods'     => [ 'POST', 'GET' ]
		)
	) );