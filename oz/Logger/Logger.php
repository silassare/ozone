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

use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Logger\Interfaces\LogWriterInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

/**
 * Class Logger.
 */
class Logger implements LoggerInterface
{
	/**
	 * {@inheritDoc}
	 *
	 * @param string|Stringable|Throwable $message
	 */
	#[Override]
	public function log($level, string|Stringable|Throwable $message, array $context = []): void
	{
		self::writer()->write((string) $level, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function emergency(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::EMERGENCY, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function alert(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::ALERT, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function critical(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::CRITICAL, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function error(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::ERROR, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function warning(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::WARNING, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function notice(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::NOTICE, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function info(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::INFO, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function debug(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::DEBUG, $message, $context);
	}

	/**
	 * Gets the configured log writer instance.
	 */
	private static function writer(): LogWriterInterface
	{
		/** @var null|LogWriterInterface $writer */
		static $writer;

		if (null === $writer) {
			$cls = Settings::get('oz.logs', 'OZ_LOG_WRITER');

			if (LogWriter::class !== $cls) {
				if (!\is_subclass_of($cls, LogWriterInterface::class)) {
					throw (new RuntimeException(\sprintf(
						'Log writer "%s" must implement "%s".',
						$cls,
						LogWriterInterface::class
					)))->suspectConfig('oz.logs', 'OZ_LOG_WRITER');
				}

				$writer = $cls::get();
			} else {
				$writer = LogWriter::get();
			}
		}

		return $writer;
	}
}
