<?php

	OZoneSettings::set( 'oz.services.list', array(
		"tnet"     => array(
			"internal_name"   => "OZoneServiceTestNet",
			"is_file_service" => false,
			"can_serve_resp"  => false,
			"cross_site"      => false,
			"req_methods"     => [ "POST" ]
		),
		"getfile"  => array(
			"internal_name"   => "OZoneServiceGetFiles",
			"is_file_service" => true,
			"can_serve_resp"  => true,
			"cross_site"      => false,
			"req_methods"     => [ "GET" ]
		),
		"captcha"  => array(
			"internal_name"   => "OZoneServiceCaptchaCode",
			"is_file_service" => true,
			"can_serve_resp"  => true,
			"cross_site"      => false,
			"require_client"  => false,
			"req_methods"     => [ "GET" ]
		),
		"signin"   => array(
			"internal_name"   => "OZoneServiceSignin",
			"is_file_service" => false,
			"can_serve_resp"  => false,
			"cross_site"      => false,
			"req_methods"     => [ "POST" ]
		),
		"upicedit" => array(
			"internal_name"   => "OZoneServiceUserPicEdit",
			"is_file_service" => false,
			"can_serve_resp"  => false,
			"cross_site"      => false,
			"req_methods"     => [ "POST" ]
		),
		"login"    => array(
			"internal_name"   => "OZoneServiceLogin",
			"is_file_service" => false,
			"can_serve_resp"  => false,
			"cross_site"      => false,
			"req_methods"     => [ "POST" ]
		),
		"logout"   => array(
			"internal_name"   => "OZoneServiceLogout",
			"is_file_service" => false,
			"can_serve_resp"  => false,
			"cross_site"      => false,
			"req_methods"     => [ "POST", "GET" ]
		)
	) );