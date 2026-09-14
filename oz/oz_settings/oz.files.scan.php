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

use OZONE\Core\FS\Scan\FileScan;
use OZONE\Core\FS\Scan\Interfaces\FileScannerInterface;
use OZONE\Core\FS\Scan\Scanners\ClamAVScanner;
use OZONE\Core\Queue\Queue;

/**
 * Virus scan of new files ({@see FileScan}), off by default. The scan state of each file is kept in
 * `oz_files.file_scan_state`.
 */
return [
	'OZ_FILE_SCAN_ENABLED'          => false,

	/**
	 * The scanner, a {@see FileScannerInterface}.
	 */
	'OZ_FILE_SCANNER'               => ClamAVScanner::class,

	/**
	 * `sync`: scan before the file is saved, rejecting infected uploads (`OZ_FILE_INFECTED`) and,
	 * when the scanner fails, every upload (`OZ_FILE_SCAN_FAILED`).
	 * `async`: save the file `pending` and scan it in a queue job (needs `oz jobs work`).
	 */
	'OZ_FILE_SCAN_MODE'             => FileScan::MODE_SYNC,

	/**
	 * The queue of async scans.
	 */
	'OZ_FILE_SCAN_QUEUE'            => Queue::DEFAULT,

	/**
	 * Whether files still pending, or whose scan failed, may be served. Infected files never are.
	 * Direct links to public files (OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED) bypass this check.
	 */
	'OZ_FILE_SCAN_SERVE_UNVERIFIED' => false,

	/**
	 * clamd ({@see ClamAVScanner}): a unix socket path, or else a TCP host and port.
	 */
	'OZ_CLAMAV_SOCKET'              => env('OZ_CLAMAV_SOCKET'),
	'OZ_CLAMAV_HOST'                => env('OZ_CLAMAV_HOST', '127.0.0.1'),
	'OZ_CLAMAV_PORT'                => env('OZ_CLAMAV_PORT', 3310),

	/**
	 * Connection and read timeout, in seconds.
	 */
	'OZ_CLAMAV_TIMEOUT'             => 30,
];
