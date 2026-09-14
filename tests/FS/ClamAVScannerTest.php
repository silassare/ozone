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
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\Scan\Scanners\ClamAVScanner;
use PHPUnit\Framework\TestCase;

/**
 * Class ClamAVScannerTest.
 *
 * The clamd protocol, against the other end of a socket pair holding clamd's reply.
 *
 * @internal
 *
 * @covers \OZONE\Core\FS\Scan\FileScanResult
 * @covers \OZONE\Core\FS\Scan\Scanners\ClamAVScanner
 */
final class ClamAVScannerTest extends TestCase
{
	public function testStreamsTheContentWithInstream(): void
	{
		[$scanner, $clamd] = self::scanner("stream: OK\0");

		$result = $scanner->scan(FileStream::fromString('hello'));

		self::assertFalse($result->isInfected());
		self::assertNull($result->signature);
		self::assertSame("zINSTREAM\0" . \pack('N', 5) . 'hello' . \pack('N', 0), \stream_get_contents($clamd));
	}

	public function testSplitsTheContentInChunks(): void
	{
		[$scanner, $clamd] = self::scanner("stream: OK\0");
		$content           = \str_repeat('a', 8192) . 'b';

		$scanner->scan(FileStream::fromString($content));

		self::assertSame(
			"zINSTREAM\0" . \pack('N', 8192) . \str_repeat('a', 8192) . \pack('N', 1) . 'b' . \pack('N', 0),
			\stream_get_contents($clamd)
		);
	}

	public function testReportsTheThreatFound(): void
	{
		[$scanner] = self::scanner("stream: Eicar-Test-Signature FOUND\0");

		$result = $scanner->scan(FileStream::fromString('x'));

		self::assertTrue($result->isInfected());
		self::assertSame('Eicar-Test-Signature', $result->signature);
	}

	public function testFailsOnAnError(): void
	{
		[$scanner] = self::scanner("INSTREAM size limit exceeded. ERROR\0");

		$this->expectException(RuntimeException::class);

		$scanner->scan(FileStream::fromString('x'));
	}

	public function testPings(): void
	{
		[$scanner, $clamd] = self::scanner("PONG\0");

		self::assertTrue($scanner->ping());
		self::assertSame("zPING\0", \stream_get_contents($clamd));
	}

	/**
	 * A scanner connected to one end of a socket pair; clamd's reply waits on the other end.
	 *
	 * @return array{0: ClamAVScanner, 1: resource}
	 */
	private static function scanner(string $reply): array
	{
		[$client, $clamd] = \stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

		\fwrite($clamd, $reply);

		return [new ClamAVScanner('unix:///unused', 5, static fn () => $client), $clamd];
	}
}
