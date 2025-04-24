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

namespace OZONE\Core\Logger;

use JsonSerializable;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Logger\Interfaces\LogWriterInterface;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use Throwable;

/**
 * Class LogWriter.
 */
class LogWriter implements LogWriterInterface
{
	protected readonly string $log_file;
	protected readonly int $max_size;

	/**
	 * LogWriter constructor.
	 */
	public function __construct()
	{
		if (OZ_SCOPE_NAME !== ScopeInterface::ROOT_SCOPE) {
			$dir  = scope()->getLogsDir();
			$file = $dir->resolve('ozone.' . OZ_SCOPE_NAME . '.log');
		}

		if (!isset($file)) {
			$file = \getcwd() . DS . 'ozone.log';
		}

		$this->log_file = $file;
		$this->max_size = Settings::get('oz.logs', 'OZ_LOG_MAX_FILE_SIZE');
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get(): static
	{
		/** @var null|LogWriterInterface $writer */
		static $writer;

		if (null === $writer) {
			$writer = new self();
		}

		return $writer;
	}

	/**
	 * {@inheritDoc}
	 */
	public function write($level, mixed $message, array $context = []): void
	{
		$message = self::describe($message);

		$date  = \date('Y-m-d H:i:s');
		$level = \strtoupper($level);

		$message = <<<LOG
================================================================================
[{$level}] {$date}
================================================================================
{$message}


LOG;

		$mode = (\file_exists($this->log_file) && \filesize($this->log_file) <= $this->max_size) ? 'a' : 'w';

		if ($fp = \fopen($this->log_file, $mode)) {
			\fwrite($fp, $message);
			\fclose($fp);

			if ('w' === $mode) {
				\chmod($this->log_file, 0660);
			}
		}
	}

	/**
	 * Returns a string representation of a value to be logged.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	protected static function describe(mixed $value): string
	{
		$prev_sep = "\n========previous========\n";

		if (\is_scalar($value)) {
			$log = (string) $value;
		} elseif (\is_array($value)) {
			$log = \var_export($value, true);
		} elseif ($value instanceof Throwable) {
			$e   = $value;
			$log = BaseException::throwableToString($e);

			while ($e = $e->getPrevious()) {
				$log .= $prev_sep . BaseException::throwableToString($e);
			}
		} elseif ($value instanceof JsonSerializable) {
			/** @noinspection JsonEncodingApiUsageInspection */
			$log = \json_encode($value, \JSON_PRETTY_PRINT);
		} else {
			$log = \get_debug_type($value);
		}

		return \str_replace(['\n', '\t', '\/'], ["\n", "\t", '/'], $log);
	}
}
