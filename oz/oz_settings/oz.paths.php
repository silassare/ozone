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

return [
	/**
	 * Route path pattern for the captcha image service.
	 * The `{oz_captcha_key}` placeholder is replaced by the captcha key parameter.
	 */
	'OZ_CAPTCHA_ROUTE_PATH' => '/captcha/oz-captcha-{oz_captcha_key}.png',

	/**
	 * Route path pattern for the QR code image service.
	 * The `{oz_qr_code_key}` placeholder is replaced by the QR code key parameter.
	 */
	'OZ_QR_CODE_ROUTE_PATH' => '/qrcode/oz-qr-code-{oz_qr_code_key}.png',

	/**
	 * Route path pattern for the link-to redirect service.
	 * The `{oz_link_to_key}` placeholder is replaced by the link key parameter.
	 */
	'OZ_LINK_TO_ROUTE_PATH' => '/link-to/oz-{oz_link_to_key}',

	/**
	 * Resumable form service route group path.
	 */
	'OZ_RESUMABLE_FORM_SERVICE_ROUTE_GROUP_PATH' => '/form',
];
