<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	defined('OZ_SELF_SECURITY_CHECK') or die;

	if (!function_exists('oz_logger')) {
		/**
		 * read file content.
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
		 * write to log.
		 *
		 * @param mixed $in
		 */
		function oz_logger($in)
		{
			$log = '';

			if ($in instanceof \OZONE\OZ\Exceptions\OZoneBaseException) {
				$log .= $in;
			} elseif ($in instanceof Exception OR $in instanceof Error) {
				$e   = $in;
				$log = "\n\tFile    : {$e->getFile()}"
					   . "\n\tLine    : {$e->getLine()}"
					   . "\n\tCode    : {$e->getCode()}"
					   . "\n\tMessage : {$e->getMessage()}"
					   . "\n\tTrace   : {$e->getTraceAsString()}";
			} else {
				$log = $in;
			}

			$text = trim(json_encode($log), '"');
			$text = str_replace(["\\n", "\\t", "\\\\"], ["\n", "\t", "\\"], $text);
			$text = date('Y-m-d H:i:s ')
					. $text
					. "\n================================================================================\n";

			$f = fopen(OZ_APP_DIR . 'debug.log', 'a+');
			fwrite($f, $text);
			fclose($f);
		}

		/**
		 * when we should shutdown and only admin should know what is going wrong
		 */
		function oz_critical_die_message()
		{
			$mask_e = new \OZONE\OZ\Exceptions\OZoneInternalError('Internal error: unhandled, if you are an admin please look in log file and fix it!');
			$mask_e->procedure();

			false OR die;
		}

		/**
		 * log unhandled exception.
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
		 * log non fatalist error.
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
		 * log fatalist error.
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
					// tmp : this error probably would have been handled
					oz_logger("OZone: shutdown IGNORE code: $code");
				}
			}
		}

		set_exception_handler('oz_exception_handler');
		set_error_handler('oz_error_handler');
		register_shutdown_function('oz_error_fatalist');
	}