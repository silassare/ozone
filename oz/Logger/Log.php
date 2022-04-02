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

namespace OZONE\OZ\Logger;

use PHPUtils\Str;
use Throwable;

class Log
{
	private string $log_file_path;

	public function __construct(string $dir, string $name)
	{
		$this->log_file_path = \realpath($dir) . \DIRECTORY_SEPARATOR . $name . '.log';
	}

	// end __construct()

	public static function error($message, array $context = [])
	{
	}

	public function log($level, $message, array $context = []): void
	{
		// TODO
		$message_parsed = Str::interpolate((string) $message, $context);

		$exception = ($context['exception'] ?? '');

		if ($exception instanceof Throwable) {
			$exception = $exception->getTraceAsString();
		}

		$data = [
			'level'   => $level,
			'message' => $message_parsed,
		];

		if ($exception) {
			$data['exception'] = $exception;
		}

		$log = \json_encode($data, \JSON_PRETTY_PRINT);

		$date = \date('m-d-Y h:m:s');
		$log  = "
======================================================
{$date}
================================
{$log}
";

		\file_put_contents($this->log_file_path, $log, \FILE_APPEND);
	}

	// end log()
}// end class
