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

namespace OZONE\Core\Services\QRCode\Interfaces;

use OZONE\Core\App\Settings;

/**
 * Interface QRCodeEncoderInterface.
 *
 * Implement this interface to provide a custom QR code encoder, and name the implementation in the
 * `OZ_QR_CODE_ENCODER` key of the `oz.utils` settings group. That is the hook for a project whose
 * codes carry a logo, rounded modules, a colour or a gradient: those belong to a project's visual
 * identity, so OZone encodes and a project that wants more supplies its own encoder.
 *
 * Reading a QR code is not here, and OZone ships nothing that does it. Decoding a code that OZone
 * did not draw means reading a photograph: locating the finder patterns in it, correcting the
 * perspective, sampling the grid and repairing the damage through the error-correction codewords.
 * Where a camera is involved, the client that holds it decodes and sends the text, which is what a
 * browser or a mobile application does; a server asked for an uploaded image instead takes a
 * library built for it.
 *
 * @see Settings
 */
interface QRCodeEncoderInterface
{
	/**
	 * Creates an instance of the QR code encoder.
	 *
	 * @return static the QR code encoder instance
	 */
	public static function instance(): static;

	/**
	 * Encodes the given data as a PNG QR code image and returns the raw PNG binary.
	 *
	 * @param string $data   the data to encode
	 * @param int    $size   the module size in pixels (each QR module = $size x $size pixels)
	 * @param int    $margin the quiet-zone width in modules around the QR symbol
	 *
	 * @return string raw PNG binary
	 */
	public function encode(string $data, int $size = 6, int $margin = 2): string;
}
