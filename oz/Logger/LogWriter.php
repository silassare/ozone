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
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Logger\Interfaces\LogWriterInterface;
use Throwable;

/**
 * Class LogWriter.
 */
class LogWriter implements LogWriterInterface
{
	protected readonly string $log_file;
	protected readonly int $max_size;
	protected readonly int $max_files;

	/**
	 * Whether no app was running, so the log file is a temporary fallback.
	 */
	protected readonly bool $fallback;

	/**
	 * LogWriter constructor.
	 *
	 * By default logs go to `ozone.{scope}.log` in the scope's logs dir (`.ozone/logs/`),
	 * rotated per `OZ_LOG_MAX_FILE_SIZE` and `OZ_LOG_MAX_FILES`.
	 *
	 * @param null|string $file      the log file
	 * @param null|int    $max_size  size, in bytes, beyond which the file is rotated
	 * @param null|int    $max_files how many rotated files are kept
	 */
	public function __construct(?string $file = null, ?int $max_size = null, ?int $max_files = null)
	{
		$fallback = false;

		if (null === $file) {
			try {
				$file = scope()->getLogsDir()->resolve('ozone.' . OZ_SCOPE_NAME . '.log');
			} catch (Throwable) {
				// No app yet (early boot, or the CLI outside a project). Never the working
				// directory: it can be a public one.
				$file     = \sys_get_temp_dir() . DS . 'ozone.log';
				$fallback = true;
			}
		}

		$this->log_file  = $file;
		$this->fallback  = $fallback;
		$this->max_size  = $max_size ?? (int) Settings::get('oz.logs', 'OZ_LOG_MAX_FILE_SIZE');
		$this->max_files = $max_files ?? (int) Settings::get('oz.logs', 'OZ_LOG_MAX_FILES', 5);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function instance(): static
	{
		/** @var null|self $writer */
		static $writer;

		// A fallback writer is replaced once an app is running.
		if (null === $writer || $writer->fallback) {
			$writer = new self();
		}

		return $writer;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function write($level, mixed $message, array $context = []): void
	{
		$message = self::describe($message);
		$context = !empty($context) ? self::describe($context) : '';

		$date  = \date('Y-m-d H:i:s');
		$level = \strtoupper($level);

		$message = <<<LOG
================================================================================
[{$level}] {$date}
================================================================================
{$message}

LOG;
		if (!empty($context)) {
			$message .= <<<LOG
===
{$context}

LOG;
		}

		\clearstatcache(true, $this->log_file);

		$is_new = !\file_exists($this->log_file);

		if (!$is_new && \filesize($this->log_file) > $this->max_size) {
			try {
				$this->rotate();
				$is_new = true;
			} catch (Throwable) {
				// Another process may be rotating the same file: keep appending.
			}
		}

		if ($fp = \fopen($this->log_file, 'ab')) {
			\fwrite($fp, $message);
			\fclose($fp);

			if ($is_new) {
				\chmod($this->log_file, 0o660);
			}
		}
	}

	/**
	 * Moves the current file to `name.1.log`, shifting older ones (`name.1.log` to
	 * `name.2.log`, ...) and dropping the one beyond `max_files`.
	 */
	protected function rotate(): void
	{
		// Oldest first, so no file is overwritten before it has moved on.
		for ($i = $this->max_files; $i > 1; --$i) {
			$older = $this->rotatedFile($i - 1);

			if (\file_exists($older)) {
				\rename($older, $this->rotatedFile($i));
			}
		}

		if ($this->max_files > 0) {
			\rename($this->log_file, $this->rotatedFile(1));
		} else {
			\unlink($this->log_file);
		}
	}

	/**
	 * The path of the rotated file at the given index: `ozone.root.log` -> `ozone.root.1.log`,
	 * keeping the `.log` extension for ignore rules and log viewers.
	 */
	protected function rotatedFile(int $index): string
	{
		return (string) \preg_replace('~(\.log)?$~', '.' . $index . '$1', $this->log_file, 1);
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
