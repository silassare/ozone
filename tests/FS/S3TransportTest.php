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

namespace OZONE\Tests\FS;

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\S3\Interfaces\S3TransportInterface;
use OZONE\Core\FS\S3\S3Request;
use OZONE\Core\FS\S3\Transport\CurlTransport;
use OZONE\Core\FS\S3\Transport\StreamWrapperTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class S3TransportTest.
 *
 * What a transport says when the storage cannot be reached at all. No server is needed, and none
 * is wanted: the address is a port of this machine that nothing listens on, which is the one
 * failure a running MinIO cannot produce.
 *
 * @internal
 */
#[CoversClass(StreamWrapperTransport::class)]
#[CoversClass(CurlTransport::class)]
final class S3TransportTest extends TestCase
{
	#[DataProvider('provideTransports')]
	public function testATransportSaysWhyTheStorageCouldNotBeReached(S3TransportInterface $transport): void
	{
		$url = 'http://127.0.0.1:' . self::closedPort() . '/ozone-test/unreachable.txt';

		try {
			$transport->send(new S3Request('PUT', $url, ['host' => '127.0.0.1'], 'bye'));

			self::fail('An address nothing listens on must raise.');
		} catch (RuntimeException $e) {
			$message = $e->getMessage();
			$prefix  = \sprintf('Unable to reach the object storage at "%s": ', $url);

			self::assertStringStartsWith($prefix, $message, 'both transports report the reason after the URL');

			$reason = \rtrim(\substr($message, \strlen($prefix)), '.');

			// What the URL on its own left a caller guessing at: nothing listening, a name that did
			// not resolve, a peer that hung up and a service that was down read the same.
			self::assertMatchesRegularExpression('~connect|refused~i', $reason);

			// The fallback of each transport, which means the reason was not read back at all.
			self::assertStringNotContainsString('unknown', $reason);

			// The stream wrapper's own message repeats the URL ("fopen(<url>): ..."), and the
			// sentence it is put in says it already.
			self::assertSame(1, \substr_count($message, $url), 'the URL is reported once');
		}
	}

	public static function provideTransports(): iterable
	{
		$transports = ['stream-wrapper' => [new StreamWrapperTransport()]];

		if (CurlTransport::isSupported()) {
			$transports['curl'] = [new CurlTransport()];
		}

		return $transports;
	}

	/**
	 * A port of this machine that nothing listens on: bound to learn which it is, then released.
	 */
	private static function closedPort(): int
	{
		$socket = \stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

		if (false === $socket) {
			self::fail(\sprintf('Unable to reserve a port: %s.', (string) $error));
		}

		$name = (string) \stream_socket_get_name($socket, false);

		\fclose($socket);

		$pos = \strrpos($name, ':');

		self::assertNotFalse($pos, \sprintf('The reserved address "%s" carries no port.', $name));

		return (int) \substr($name, $pos + 1);
	}
}
