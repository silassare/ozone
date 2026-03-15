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

namespace OZONE\Tests\Http;

use InvalidArgumentException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Http\Stream;
use PHPUnit\Framework\TestCase;

/**
 * Class StreamTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class StreamTest extends TestCase
{
    private function makeStream(string $mode = 'r+'): Stream
    {
        $resource = \fopen('php://temp', $mode);
        self::assertIsResource($resource);

        return new Stream($resource);
    }

    public function testConstructorThrowsForNonResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @phpstan-ignore-next-line */
        new Stream('not-a-resource');
    }

    public function testIsReadableForReadableStream(): void
    {
        $stream = $this->makeStream('r+');
        self::assertTrue($stream->isReadable());
        $stream->close();
    }

    public function testIsReadableForWriteOnlyStream(): void
    {
        $file   = \tempnam(\sys_get_temp_dir(), 'oz_test_');
        $res    = \fopen($file, 'w');
        self::assertIsResource($res);
        $stream = new Stream($res);
        self::assertFalse($stream->isReadable());
        $stream->close();
        \unlink($file);
    }

    public function testIsWritableForWritableStream(): void
    {
        $stream = $this->makeStream('r+');
        self::assertTrue($stream->isWritable());
        $stream->close();
    }

    public function testIsWritableForReadOnlyStream(): void
    {
        $file   = \tempnam(\sys_get_temp_dir(), 'oz_test_');
        \file_put_contents($file, 'data');
        $res    = \fopen($file, 'r');
        self::assertIsResource($res);
        $stream = new Stream($res);
        self::assertFalse($stream->isWritable());
        $stream->close();
        \unlink($file);
    }

    public function testIsSeekableForPhpTempStream(): void
    {
        $stream = $this->makeStream('r+');
        self::assertTrue($stream->isSeekable());
        $stream->close();
    }

    public function testWriteAndRead(): void
    {
        $stream = $this->makeStream('r+');
        $stream->write('hello');
        $stream->seek(0);
        $data = $stream->read(5);
        self::assertSame('hello', $data);
        $stream->close();
    }

    public function testWriteReturnsNumberOfBytesWritten(): void
    {
        $stream  = $this->makeStream('r+');
        $written = $stream->write('test');
        self::assertSame(4, $written);
        $stream->close();
    }

    public function testTellReturnsCurrentPosition(): void
    {
        $stream = $this->makeStream('r+');
        $stream->write('abc');
        self::assertSame(3, $stream->tell());
        $stream->seek(1);
        self::assertSame(1, $stream->tell());
        $stream->close();
    }

    public function testEofReturnsFalseBeforeEnd(): void
    {
        $stream = $this->makeStream('r+');
        $stream->write('abc');
        $stream->seek(0);
        self::assertFalse($stream->eof());
        $stream->close();
    }

    public function testEofReturnsTrueAtEnd(): void
    {
        $stream = $this->makeStream('r+');
        $stream->write('abc');
        // Read past the end.
        $stream->read(100);
        self::assertTrue($stream->eof());
        $stream->close();
    }

    public function testGetSize(): void
    {
        $stream = $this->makeStream('r+');
        $stream->write('hello world');
        $stream->seek(0);
        self::assertSame(11, $stream->getSize());
        $stream->close();
    }

    public function testGetContents(): void
    {
        $stream = $this->makeStream('r+');
        $stream->write('contents');
        $contents = $stream->getContents();
        self::assertSame('contents', $contents);
        $stream->close();
    }

    public function testToStringReturnsFullContent(): void
    {
        $stream = $this->makeStream('r+');
        $stream->write('stringify me');
        self::assertSame('stringify me', (string) $stream);
        $stream->close();
    }

    public function testRewindSetsPositionToZero(): void
    {
        $stream = $this->makeStream('r+');
        $stream->write('rewind');
        $stream->rewind();
        self::assertSame(0, $stream->tell());
        $stream->close();
    }

    public function testDetachReturnsResourceAndNullifies(): void
    {
        $stream = $this->makeStream('r+');
        $res    = $stream->detach();
        self::assertIsResource($res);
        // After detach the stream is no longer attached.
        self::assertSame('', (string) $stream);
        \fclose($res);
    }

    public function testCloseFreesResource(): void
    {
        $resource = \fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);
        $stream->close();
        // The underlying PHP resource should be closed.
        self::assertFalse(\is_resource($resource));
    }

    public function testGetMetadataReturnsArray(): void
    {
        $stream = $this->makeStream('r+');
        $meta   = $stream->getMetadata();
        self::assertIsArray($meta);
        self::assertArrayHasKey('mode', $meta);
        self::assertArrayHasKey('seekable', $meta);
        $stream->close();
    }

    public function testGetMetadataWithKeyReturnsValue(): void
    {
        $stream = $this->makeStream('r+');
        self::assertNotNull($stream->getMetadata('mode'));
        $stream->close();
    }

    public function testGetMetadataWithMissingKeyReturnsNull(): void
    {
        $stream = $this->makeStream('r+');
        self::assertNull($stream->getMetadata('nonexistent_key'));
        $stream->close();
    }

    public function testSeekThrowsForNonSeekableStream(): void
    {
        // stdin-like stream is not seekable, but we can simulate with php://output.
        // Use a temp file opened read-only for non-seekable simulation: actually php://temp is seekable.
        // Instead test that seek throws RuntimeException on failure by mocking or using close first.
        $stream = $this->makeStream('r+');
        $stream->close();
        $this->expectException(RuntimeException::class);
        $stream->seek(0);
    }

    public function testReadThrowsForNonReadableStream(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'oz_test_');
        $res  = \fopen($file, 'w');
        self::assertIsResource($res);
        $stream = new Stream($res);
        $this->expectException(RuntimeException::class);
        $stream->read(1);
        $stream->close();
        \unlink($file);
    }

    public function testWriteThrowsForNonWritableStream(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'oz_test_');
        \file_put_contents($file, 'data');
        $res = \fopen($file, 'r');
        self::assertIsResource($res);
        $stream = new Stream($res);
        $this->expectException(RuntimeException::class);
        $stream->write('fail');
        $stream->close();
        \unlink($file);
    }
}
