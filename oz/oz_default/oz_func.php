<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	use OZONE\OZ\Core\Context;
	use OZONE\OZ\Exceptions\BaseException;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	if (!function_exists('oz_file_read')) {
		/**
		 * Safely read file content.
		 *
		 * @param string $src the file path
		 *
		 * @return bool|string
		 * @throws \Exception
		 */
		function oz_file_read($src)
		{
			if (empty($src) || !file_exists($src) || !is_file($src) || !is_readable($src)) {
				throw new Exception(sprintf('Unable to access file at : "%s".', $src));
			}

			return file_get_contents($src);
		}

		/**
		 * Build file url.
		 *
		 * @param \OZONE\OZ\Core\Context $context
		 * @param integer                $file_id
		 * @param string                 $file_key
		 * @param int                    $file_quality
		 * @param string                 $def
		 *
		 * @return int|mixed|string
		 * @throws \Exception
		 */
		function oz_file_url(Context $context, $file_id, $file_key = '', $file_quality = 0, $def = '')
		{
			$args  = func_get_args();
			$parts = explode('_', $args[1]);

			if (count($parts) === 2) { // oz_file_url($context, file_id + '_' + file_key,file_quality,def)
				$def          = $file_quality;
				$file_quality = $file_key;
				$file_id      = $parts[0];
				$file_key     = $parts[1];
			}

			$file_quality = in_array($file_quality, [0, 1, 2, 3]) ? $file_quality : 0;

			if ($def && ($file_id === '0' || $file_key === '0')) {
				return $def;
			}

			return $context->buildRouteUri('oz:files-static', [
				'oz_file_id'      => $file_id,
				'oz_file_key'     => $file_key,
				'oz_file_quality' => $file_quality
			]);
		}

		/**
		 * Write to log file.
		 *
		 * @param mixed $in
		 */
		function oz_logger($in)
		{
			$prev_sep          = "\n========previous========\n";
			$date              = date('Y-m-d H:i:s');
			$log_file          = OZ_LOG_DIR . 'debug.log';
			$max_log_file_size = 254 * 1000;// Kb

			if (file_exists($log_file) AND filesize($log_file) > $max_log_file_size) {
				unlink($log_file);
			}

			if (is_scalar($in)) {
				$log = (string)$in;
			} elseif (is_array($in)) {
				$log = var_export($in, true);
			} elseif ($in instanceof Exception OR $in instanceof Error) {
				$e   = $in;
				$log = BaseException::throwableToString($e);

				while ($e = $e->getPrevious()) {
					$log .= $prev_sep . BaseException::throwableToString($e);
				}
			} else {
				$log = gettype($in);
				// $log = var_export(debug_backtrace(),true);
			}

			$log = str_replace(["\\n", "\\t", "\/"], ["\n", "\t", "/"], $log);
			$log = "================================================================================\n"
				   . $date . "\n"
				   . "========================\n"
				   . $log . "\n\n";

			file_put_contents($log_file, $log, FILE_APPEND);
		}

		/**
		 * Called when we should shutdown and only admin
		 * should know what is going wrong.
		 */
		function oz_critical_die_message()
		{
			BaseException::criticalDie(BaseException::MESSAGE_ERROR_UNHANDLED);
		}

		/**
		 * Handle unhandled exception.
		 *
		 * @param \Exception $e
		 */
		function oz_exception_handler($e)
		{
			oz_logger($e);

			if (OZ_OZONE_IS_CLI) die(PHP_EOL . $e->getMessage() . PHP_EOL);

			oz_critical_die_message();
		}

		/**
		 * Log error.
		 *
		 * @param int    $code         the error code
		 * @param string $message      the error message
		 * @param string $file         the file where it occurs
		 * @param int    $line         the file line where it occurs
		 * @param bool   $die_on_fatal when true we will interrupt on fatal error
		 */
		function oz_error_logger($code, $message, $file, $line, $die_on_fatal = false)
		{
			oz_logger("\n\tFile    : {$file}"
					  . "\n\tLine    : {$line}"
					  . "\n\tCode    : {$code}"
					  . "\n\tMessage : {$message}");

			if ($die_on_fatal) {
				$fatalist = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

				if (in_array($code, $fatalist)) {
					oz_critical_die_message();
				}
			}
		}

		/**
		 * Handle unhandled error.
		 *
		 * @param int    $code    the error code
		 * @param string $message the error message
		 * @param string $file    the file where it occurs
		 * @param int    $line    the file line where it occurs
		 */
		function oz_error_handler($code, $message, $file, $line)
		{
			oz_error_logger($code, $message, $file, $line, true);
		}

		/**
		 * Try to log error after shutdown.
		 */
		function oz_error_shutdown_function()
		{
			$error = error_get_last();

			if (!is_null($error)) {
				oz_logger(
					"::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::\n"
					. "O'Zone shutdown error"
					. "\n::::::::::::::::::::::::::::::");
				oz_error_logger($error["type"], $error["message"], $error["file"], $error["line"], true);
			}
		}

		set_exception_handler('oz_exception_handler');
		set_error_handler('oz_error_handler');
		register_shutdown_function('oz_error_shutdown_function');
	}