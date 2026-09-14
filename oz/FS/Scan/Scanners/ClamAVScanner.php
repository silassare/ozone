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

namespace OZONE\Core\FS\Scan\Scanners;

use Closure;
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\Scan\FileScanResult;
use OZONE\Core\FS\Scan\Interfaces\FileScannerInterface;

/**
 * Class ClamAVScanner.
 *
 * Scans through a clamd daemon (ClamAV), with its INSTREAM command: the content is streamed over
 * the socket, so clamd needs no access to the files. clamd rejects a content larger than its
 * `StreamMaxLength` (25 MB by default): keep it above `OZ_UPLOAD_FILE_MAX_SIZE` (`oz.files`).
 */
final class ClamAVScanner implements FileScannerInterface
{
	private const CHUNK_SIZE = 8192;

	/**
	 * ClamAVScanner constructor.
	 *
	 * @param string       $address   the clamd socket: `tcp://host:port` or `unix:///path/to/clamd.sock`
	 * @param int          $timeout   connection and read timeout, in seconds
	 * @param null|Closure $connector opens the connection instead of `stream_socket_client()` (tests)
	 */
	public function __construct(
		private readonly string $address,
		private readonly int $timeout = 30,
		private readonly ?Closure $connector = null
	) {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function fromSettings(): static
	{
		$socket  = Settings::get('oz.files.scan', 'OZ_CLAMAV_SOCKET');
		$address = empty($socket)
			? \sprintf(
				'tcp://%s:%d',
				Settings::get('oz.files.scan', 'OZ_CLAMAV_HOST', '127.0.0.1'),
				(int) Settings::get('oz.files.scan', 'OZ_CLAMAV_PORT', 3310)
			)
			: 'unix://' . $socket;

		return new self($address, (int) Settings::get('oz.files.scan', 'OZ_CLAMAV_TIMEOUT', 30));
	}

	/**
	 * Checks that clamd answers.
	 */
	public function ping(): bool
	{
		$conn = $this->connect();

		try {
			\fwrite($conn, "zPING\0");

			return 'PONG' === $this->readReply($conn);
		} finally {
			\fclose($conn);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function scan(FileStream $content): FileScanResult
	{
		if ($content->isSeekable()) {
			$content->rewind();
		}

		$conn = $this->connect();

		try {
			// clamd stops reading once the content exceeds its limit: its reply then tells why.
			$sending = false !== \fwrite($conn, "zINSTREAM\0");

			while ($sending && !$content->eof()) {
				$chunk = $content->read(self::CHUNK_SIZE);

				if ('' === $chunk) {
					break;
				}

				$sending = false !== \fwrite($conn, \pack('N', \strlen($chunk)) . $chunk);
			}

			if ($sending) {
				\fwrite($conn, \pack('N', 0));
			}

			$reply = $this->readReply($conn);
		} finally {
			\fclose($conn);
		}

		return self::parseReply($reply);
	}

	/**
	 * Parses an INSTREAM reply: `stream: OK`, `stream: <signature> FOUND` or `<message> ERROR`.
	 */
	private static function parseReply(string $reply): FileScanResult
	{
		if ('stream: OK' === $reply) {
			return FileScanResult::clean();
		}

		if (\str_starts_with($reply, 'stream: ') && \str_ends_with($reply, ' FOUND')) {
			return FileScanResult::infected(\substr($reply, 8, -6));
		}

		throw new RuntimeException('ClamAV could not scan the content.', ['_reply' => $reply]);
	}

	/**
	 * Opens the connection to clamd.
	 *
	 * @return resource
	 */
	private function connect()
	{
		if (null !== $this->connector) {
			$conn = ($this->connector)();
		} else {
			$conn = @\stream_socket_client($this->address, $errno, $error, $this->timeout);

			if (false === $conn) {
				throw new RuntimeException('Unable to connect to ClamAV.', [
					'_address' => $this->address,
					'_error'   => $error,
				]);
			}
		}

		\stream_set_timeout($conn, $this->timeout);

		return $conn;
	}

	/**
	 * Reads a NUL-terminated reply (the `z` command prefix).
	 *
	 * @param resource $conn
	 */
	private function readReply($conn): string
	{
		$reply = '';

		while (!\str_contains($reply, "\0")) {
			$data = \fread($conn, 1024);

			if (false === $data || '' === $data) {
				if (\stream_get_meta_data($conn)['timed_out']) {
					throw new RuntimeException('ClamAV did not answer in time.', ['_address' => $this->address]);
				}

				break;
			}

			$reply .= $data;
		}

		return \trim(\explode("\0", $reply, 2)[0]);
	}
}
