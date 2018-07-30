<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	defined('OZ_SELF_SECURITY_CHECK') or die;

	if (!function_exists('oz_logger')) {
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
		 * Write to log file.
		 *
		 * @param mixed $in
		 */
		function oz_logger($in)
		{
			if (is_scalar($in)) {
				$text = (string)$in;
			} elseif ($in instanceof \OZONE\OZ\Exceptions\BaseException) {
				$e        = $in;
				$text     = (string)$e;
				$previous = $e->getPrevious();
				if (!is_null($previous)) {
					$text .= "\n-------previous--------\n" . $previous;
				}
			} elseif ($in instanceof Exception OR $in instanceof Error) {
				$e        = $in;
				$text     = "\tFile    : {$e->getFile()}"
							. "\n\tLine    : {$e->getLine()}"
							. "\n\tCode    : {$e->getCode()}"
							. "\n\tMessage : {$e->getMessage()}"
							. "\n\tTrace   : {$e->getTraceAsString()}";
				$previous = $in->getPrevious();
				if (!is_null($previous)) {
					$text .= "\n-------previous--------\n" . $previous;
				}
			} else {
				$text = var_export($in, true);
			}

			$date = date('Y-m-d H:i:s ');
			$text = str_replace(["\\n", "\\t", "\/"], ["\n", "\t", "/"], $text);
			$log  = <<<LOG
================================================================================
$date
===================
$text\n\n
LOG;
			$f    = fopen(OZ_LOG_DIR . 'debug.log', 'a+');
			fwrite($f, $log);
			fclose($f);
		}

		/**
		 * Called when we should shutdown and only admin
		 * should know what is going wrong.
		 */
		function oz_critical_die_message()
		{
			$mask_e = new \OZONE\OZ\Exceptions\InternalErrorException('Internal Error: Unhandled, if you are an admin, please review the log file and correct it!');
			$mask_e->procedure();

			false OR die;
		}

		/**
		 * Log unhandled exception.
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
		 * Log non fatalist error.
		 *
		 * @param int    $code    the error code
		 * @param string $message the error message
		 * @param string $file    the file where it occurs
		 * @param int    $line    the file line where it occurs
		 */
		function oz_error_handler($code, $message, $file, $line)
		{
			$log = "\n\tFile    : {$file}" . "\n\tLine    : {$line}" . "\n\tCode    : {$code}" . "\n\tMessage : {$message}";

			oz_logger($log);
		}

		/**
		 * Log fatalist error.
		 */
		function oz_error_fatalist()
		{
			$fatalist = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
			$error    = error_get_last();

			if ($error !== null) {
				$code = $error["type"];

				if (in_array($code, $fatalist)) {
					oz_error_handler($code, $error["message"], $error["file"], $error["line"]);
					oz_critical_die_message();
				} else {
					oz_logger("OZone: shutdown error.");
					oz_error_handler($code, $error["message"], $error["file"], $error["line"]);
				}
			}
		}

		set_exception_handler('oz_exception_handler');
		set_error_handler('oz_error_handler');
		register_shutdown_function('oz_error_fatalist');
	}