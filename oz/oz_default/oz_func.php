<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use OZONE\OZ\Core\Context;
use OZONE\OZ\Exceptions\BaseException;

if (!\function_exists('oz_logger')) {
	/**
	 * Build file url.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param int                    $file_id
	 * @param string                 $file_key
	 * @param int                    $file_quality
	 * @param string                 $def
	 *
	 * @throws \Exception
	 *
	 * @return int|mixed|string
	 */
	function oz_file_url(Context $context, $file_id, $file_key = '', $file_quality = 0, $def = '')
	{
		$args  = \func_get_args();
		$parts = \explode('_', $args[1]);

		if (\count($parts) === 2) { // oz_file_url($context, file_id + '_' + file_key,file_quality,def)
			$def          = $file_quality;
			$file_quality = $file_key;
			$file_id      = $parts[0];
			$file_key     = $parts[1];
		}

		$file_quality = \in_array($file_quality, [0, 1, 2, 3]) ? $file_quality : 0;

		if ($def && ($file_id === '0' || $file_key === '0')) {
			return $def;
		}

		return $context->buildRouteUri('oz:files-static', [
			'oz_file_id'      => $file_id,
			'oz_file_key'     => $file_key,
			'oz_file_quality' => $file_quality,
		]);
	}

	/**
	 * Write to log file.
	 *
	 * @param mixed $in
	 */
	function oz_logger($in)
	{
		$prev_sep = "\n========previous========\n";
		$date     = \date('Y-m-d H:i:s');
		$log_file = OZ_LOG_DIR . 'debug.log';

		if (\is_scalar($in)) {
			$log = (string) $in;
		} elseif (\is_array($in)) {
			$log = \var_export($in, true);
		} elseif ($in instanceof Exception || $in instanceof Error) {
			$e   = $in;
			$log = BaseException::throwableToString($e);

			while ($e = $e->getPrevious()) {
				$log .= $prev_sep . BaseException::throwableToString($e);
			}
		} elseif ($in instanceof JsonSerializable) {
			$log = \json_encode($in, \JSON_PRETTY_PRINT);
		} else {
			$log = \gettype($in);
			// $log = var_export(debug_backtrace(),true);
		}

		$log = \str_replace(['\\n', '\\t', "\/"], ["\n", "\t", '/'], $log);
		$log = "================================================================================\n"
			   . $date . "\n"
			   . "========================\n"
			   . $log . "\n\n";

		$mode = (\file_exists($log_file) && \filesize($log_file) <= 254000) ? 'a' : 'w';
		$fp   = \fopen($log_file, $mode);
		\fwrite($fp, $log);
		\fclose($fp);
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

		if (OZ_OZONE_IS_CLI) {
			die(\PHP_EOL . $e->getMessage() . \PHP_EOL);
		}

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
			$fatalist = [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR];

			if (\in_array($code, $fatalist)) {
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
		$error = \error_get_last();

		if (null !== $error) {
			oz_logger(
				"::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::\n"
				. 'OZone shutdown error'
				. "\n::::::::::::::::::::::::::::::"
			);
			oz_error_logger($error['type'], $error['message'], $error['file'], $error['line'], true);
		}
	}

	/**
	 * Try get callable file code source start and end line numbers.
	 *
	 * @param callable $c
	 *
	 * @return array|bool
	 */
	function oz_callable_info(callable $c)
	{
		$r = null;

		try {
			if ($c instanceof Closure) {
				$r = new ReflectionFunction($c);
			} elseif (\is_callable($c)) {
				$r = new ReflectionMethod($c);
			}

			if ($r) {
				return [
					'file'  => $r->getFileName(),
					'start' => $r->getStartLine() - 1,
					'end'   => $r->getEndLine(),
				];
			}
		} catch (ReflectionException $e) {
		}

		return false;
	}

	\set_exception_handler('oz_exception_handler');
	\set_error_handler('oz_error_handler');
	\register_shutdown_function('oz_error_shutdown_function');
}
