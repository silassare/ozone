<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\App;

interface AppInterface
{
	/**
	 * AppInterface constructor.
	 */
	public function __construct();

	/**
	 * ==============================================================
	 * HOOKS
	 * ==============================================================
	 */

	/**
	 * Init hook. Is called before the current request is executed.
	 */
	public function onInit();

	/**
	 * Unhandled exception hook. Is called when an unhandled exception occurs.
	 *
	 * @param \Exception $e the exception
	 */
	public function onUnhandledException(\Exception $e);

	/**
	 * Unhandled error hook. Is called when an unhandled error occurs.
	 *
	 * @param int    $code    the error code
	 * @param string $message the error message
	 * @param string $file    the file where it occurs
	 * @param int    $line    the file line where it occurs
	 */
	public function onUnhandledError($code, $message, $file, $line);
}
