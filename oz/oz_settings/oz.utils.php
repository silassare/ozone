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

use OZONE\Core\Services\QRCode\BuiltinQRCodeEncoderDecoder;
use OZONE\Core\Services\QRCode\Interfaces\QRCodeEncoderDecoderInterface;

return [
	/**
	 * QR code encoder class.
	 *
	 * Must implement {@see QRCodeEncoderDecoderInterface}.
	 * Swap this to plug in a third-party encoder (e.g. endroid/qr-code).
	 */
	'OZ_QR_CODE_ENCODER'    => BuiltinQRCodeEncoderDecoder::class,
];
