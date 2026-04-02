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
 * Interface QRCodeEncoderDecoderInterface.
 *
 * Implement this interface to provide a custom QR code encoder/decoder.
 * Register the implementation class via the {@see Settings} key
 * `OZ_QR_CODE_ENCODER` in the `oz.utils` settings group.
 */
interface QRCodeEncoderDecoderInterface
{
	/**
	 * Gets the QR code encoder/decoder instance.
	 *
	 * @return static the QR code encoder/decoder instance
	 */
	public static function get(): static;

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

	/**
	 * Decodes a PNG or JPEG QR code image and returns the embedded string.
	 *
	 * The image must have been produced by a conforming QR encoder (ISO/IEC 18004).
	 * Only byte-mode segments are supported by the built-in decoder.
	 *
	 * @param string $image raw PNG or JPEG binary
	 *
	 * @return string the decoded data
	 */
	public function decode(string $image): string;
}
