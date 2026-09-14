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

namespace OZONE\Core\Exceptions;

/**
 * Class FileScanRejectedException.
 *
 * A new file refused by the virus scan (sync mode of `oz.files.scan`): `OZ_FILE_INFECTED`, or
 * `OZ_FILE_SCAN_FAILED` when the content could not be scanned.
 */
class FileScanRejectedException extends BadRequestException {}
