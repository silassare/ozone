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

use claviska\SimpleImage;
use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\Enums\FileKind;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\Filters\FileFilters;
use OZONE\Core\FS\Filters\ImageFileFilterHandler;
use OZONE\Core\FS\Filters\Interfaces\FileFilterHandlerInterface;
use OZONE\Core\Http\Response;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionObject;

/**
 * Class FileFiltersTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class FileFiltersTest extends TestCase
{
	// -----------------------------------------------------------------------
	// Setup / Teardown
	// -----------------------------------------------------------------------

	/**
	 * Reset static state in FileFilters between tests so registered spy
	 * handlers from one test do not bleed into the next.
	 */
	protected function tearDown(): void
	{
		$rc = new ReflectionClass(FileFilters::class);
		$rc->getProperty('handlers')->setValue(null, []);
		$rc->getProperty('loaded')->setValue(null, false);
	}

	// -----------------------------------------------------------------------
	// ImageFileFilterHandler::canHandle
	// -----------------------------------------------------------------------

	public function testCanHandleImageMime(): void
	{
		$handler = new ImageFileFilterHandler();

		self::assertTrue($handler->canHandle(self::makeFile('image/png'), []));
		self::assertTrue($handler->canHandle(self::makeFile('image/jpeg'), []));
		self::assertTrue($handler->canHandle(self::makeFile('image/gif'), []));
		self::assertTrue($handler->canHandle(self::makeFile('image/webp'), []));
	}

	public function testCannotHandleNonImageMime(): void
	{
		$handler = new ImageFileFilterHandler();

		self::assertFalse($handler->canHandle(self::makeFile('text/plain'), []));
		self::assertFalse($handler->canHandle(self::makeFile('application/pdf'), []));
		self::assertFalse($handler->canHandle(self::makeFile('video/mp4'), []));
		self::assertFalse($handler->canHandle(self::makeFile('audio/mpeg'), []));
	}

	// -----------------------------------------------------------------------
	// Token: no-op (no tokens) - image unchanged
	// -----------------------------------------------------------------------

	public function testNoTokensReturnsOriginalDimensions(): void
	{
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(200, 150);
		$out   = self::applyFilters($file, $bytes, []);

		[$w, $h] = self::pngDimensions($out);
		self::assertSame(200, $w);
		self::assertSame(150, $h);
	}

	// -----------------------------------------------------------------------
	// Token: w{N} - resize to width, proportional height
	// -----------------------------------------------------------------------

	public function testWidthTokenResizesWidth(): void
	{
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(200, 100);
		$out   = self::applyFilters($file, $bytes, ['w100']);

		[$w, $h] = self::pngDimensions($out);
		self::assertSame(100, $w);
		self::assertSame(50, $h); // proportional
	}

	// -----------------------------------------------------------------------
	// Token: h{N} - resize to height, proportional width
	// -----------------------------------------------------------------------

	public function testHeightTokenResizesHeight(): void
	{
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(200, 100);
		$out   = self::applyFilters($file, $bytes, ['h50']);

		[$w, $h] = self::pngDimensions($out);
		self::assertSame(100, $w); // proportional
		self::assertSame(50, $h);
	}

	// -----------------------------------------------------------------------
	// Token: thumb{N} - square thumbnail via crop
	// -----------------------------------------------------------------------

	public function testThumbNTokenProducesSquare(): void
	{
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(200, 150);
		$out   = self::applyFilters($file, $bytes, ['thumb80']);

		[$w, $h] = self::pngDimensions($out);
		self::assertSame(80, $w);
		self::assertSame(80, $h);
	}

	// -----------------------------------------------------------------------
	// Token: thumb - square thumbnail using default setting
	// -----------------------------------------------------------------------

	public function testThumbTokenUsesThumbnailSize(): void
	{
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(700, 700); // larger than default 640
		$out   = self::applyFilters($file, $bytes, ['thumb']);

		[$w, $h] = self::pngDimensions($out);
		self::assertSame(640, $w);
		self::assertSame(640, $h);
	}

	// -----------------------------------------------------------------------
	// Token: nocrop - override thumbnail crop
	// -----------------------------------------------------------------------

	public function testNocropTokenPreservesAspectRatio(): void
	{
		// 200x100 with thumb80+nocrop -> bestFit(80,80) -> 80x40
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(200, 100);
		$out   = self::applyFilters($file, $bytes, ['thumb80', 'nocrop']);

		[$w, $h] = self::pngDimensions($out);
		self::assertSame(80, $w);
		self::assertSame(40, $h);
	}

	// -----------------------------------------------------------------------
	// Token: crop - explicit crop with w + h
	// -----------------------------------------------------------------------

	public function testCropTokenWithWidthAndHeightProducesExactSize(): void
	{
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(200, 150);
		$out   = self::applyFilters($file, $bytes, ['w80', 'h60', 'crop']);

		[$w, $h] = self::pngDimensions($out);
		self::assertSame(80, $w);
		self::assertSame(60, $h);
	}

	// -----------------------------------------------------------------------
	// Token: grayscale - image still valid, response body non-empty
	// -----------------------------------------------------------------------

	public function testGrayscaleTokenProducesValidImage(): void
	{
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(40, 40);
		$out   = self::applyFilters($file, $bytes, ['grayscale']);

		self::assertNotEmpty($out);
		[$w, $h] = self::pngDimensions($out);
		self::assertSame(40, $w);
		self::assertSame(40, $h);
	}

	// -----------------------------------------------------------------------
	// Token: sepia
	// -----------------------------------------------------------------------

	public function testSepiaTokenProducesValidImage(): void
	{
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(40, 40);
		$out   = self::applyFilters($file, $bytes, ['sepia']);

		self::assertNotEmpty($out);
		[$w] = self::pngDimensions($out);
		self::assertSame(40, $w);
	}

	// -----------------------------------------------------------------------
	// Token: blur / blur{N}
	// -----------------------------------------------------------------------

	public function testBlurTokenProducesValidImage(): void
	{
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(40, 40);
		$out   = self::applyFilters($file, $bytes, ['blur']);

		self::assertNotEmpty($out);
		[$w] = self::pngDimensions($out);
		self::assertSame(40, $w);
	}

	public function testBlurNTokenProducesValidImage(): void
	{
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(40, 40);
		$out   = self::applyFilters($file, $bytes, ['blur3']);

		self::assertNotEmpty($out);
		[$w] = self::pngDimensions($out);
		self::assertSame(40, $w);
	}

	// -----------------------------------------------------------------------
	// Token: sharpen
	// -----------------------------------------------------------------------

	public function testSharpenTokenProducesValidImage(): void
	{
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(40, 40);
		$out   = self::applyFilters($file, $bytes, ['sharpen']);

		self::assertNotEmpty($out);
		[$w] = self::pngDimensions($out);
		self::assertSame(40, $w);
	}

	// -----------------------------------------------------------------------
	// Token: q{N} - quality flag parsed without changing dimensions
	// -----------------------------------------------------------------------

	public function testQualityTokenDoesNotChangeDimensions(): void
	{
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(100, 80);
		$out   = self::applyFilters($file, $bytes, ['q50']);

		[$w, $h] = self::pngDimensions($out);
		self::assertSame(100, $w);
		self::assertSame(80, $h);
	}

	// -----------------------------------------------------------------------
	// Unknown tokens are silently ignored
	// -----------------------------------------------------------------------

	public function testUnknownTokensAreIgnored(): void
	{
		$file  = self::makeFile('image/png');
		$bytes = self::makePng(50, 50);
		$out   = self::applyFilters($file, $bytes, ['unknown', 'foobar', 'w30']);

		[$w, $h] = self::pngDimensions($out);
		self::assertSame(30, $w);
		self::assertSame(30, $h);
	}

	// -----------------------------------------------------------------------
	// Bad image bytes -> fallback to raw content (never empty body)
	// -----------------------------------------------------------------------

	public function testBadImageFallsBackToRawContent(): void
	{
		$file      = self::makeFile('image/png');
		$badBytes  = 'this is not an image';
		$handler   = new ImageFileFilterHandler();
		$stream    = self::makeStream($badBytes);
		$response  = new Response();
		$result    = $handler->handle($file, $stream, $response, ['w100']);
		$body      = (string) $result->getBody();

		self::assertSame($badBytes, $body);
	}

	// -----------------------------------------------------------------------
	// Response headers set correctly
	// -----------------------------------------------------------------------

	public function testHandleResponseHasCorrectMimeAndContentLength(): void
	{
		$file    = self::makeFile('image/png');
		$bytes   = self::makePng(50, 50);
		$handler = new ImageFileFilterHandler();
		$stream  = self::makeStream($bytes);
		$result  = $handler->handle($file, $stream, new Response(), ['w30']);
		$body    = (string) $result->getBody();

		self::assertSame('image/png', $result->getHeaderLine('Content-type'));
		self::assertSame((string) \strlen($body), $result->getHeaderLine('Content-Length'));
	}

	// -----------------------------------------------------------------------
	// FileFilters registry - programmatic registration + dispatch
	// -----------------------------------------------------------------------

	public function testRegisterAndApplyUsesFirstMatchingHandler(): void
	{
		// Reset static state between tests by using a fresh anonymous handler
		// that matches everything - registered BEFORE the real ImageHandler.
		$spy     = new class implements FileFilterHandlerInterface {
			public bool $called = false;

			public function canHandle(OZFile $file, array $filterTokens): bool
			{
				return true; // matches anything
			}

			public function handle(OZFile $file, FileStream $stream, Response $response, array $filterTokens): Response
			{
				$this->called = true;

				return $response->withHeader('X-Handled-By', 'spy');
			}
		};

		FileFilters::register($spy);

		$file     = self::makeFile('image/png');
		$bytes    = self::makePng(10, 10);
		$stream   = self::makeStream($bytes);
		$response = FileFilters::apply($file, $stream, new Response(), ['w5']);

		self::assertTrue($spy->called, 'Spy handler should have been called');
		self::assertSame('spy', $response->getHeaderLine('X-Handled-By'));
	}

	public function testApplyFallsBackToRawStreamWhenNoHandlerMatches(): void
	{
		// Register a handler that never matches.
		$null    = new class implements FileFilterHandlerInterface {
			public function canHandle(OZFile $file, array $filterTokens): bool
			{
				return false;
			}

			public function handle(OZFile $file, FileStream $stream, Response $response, array $filterTokens): Response
			{
				return $response; // should never be called
			}
		};

		FileFilters::register($null);

		$content  = 'raw binary data';
		$file     = self::makeFile('application/octet-stream');
		$stream   = self::makeStream($content);
		$response = FileFilters::apply($file, $stream, new Response(), []);
		$body     = (string) $response->getBody();

		self::assertSame($content, $body);
		self::assertSame('application/octet-stream', $response->getHeaderLine('Content-type'));
		self::assertSame((string) \strlen($content), $response->getHeaderLine('Content-Length'));
	}

	// -----------------------------------------------------------------------
	// SimpleImage availability guard
	// -----------------------------------------------------------------------

	public function testSimpleImageIsAvailable(): void
	{
		self::assertTrue(\class_exists(SimpleImage::class), 'claviska/simpleimage must be installed');
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Build a minimal OZFile with the given mime type.
	 *
	 * @param string $mime
	 * @param string $id
	 *
	 * @return OZFile
	 */
	private static function makeFile(string $mime, string $id = '1'): OZFile
	{
		$f = new OZFile();
		$f->setKey('test-key')
			->setRef('test/ref.dat')
			->setStorage('private')
			->setMime($mime)
			->setKind(FileKind::fromMime($mime))
			->setSize(0)
			->setName('test.dat')
			->setRealName('test.dat');

		// Inject the ID using the setter matching the generated base class.
		// ID type is string|null, set via setId / setID depending on generated name.
		// Use reflection to avoid a hard dependency on the exact setter name.
		$ref = new ReflectionObject($f);

		foreach (['setId', 'setID', 'setFileId', 'setFileID'] as $setter) {
			if ($ref->hasMethod($setter)) {
				$f->{$setter}($id);

				break;
			}
		}

		return $f;
	}

	/**
	 * Create a minimal PNG image as a byte string with given dimensions.
	 *
	 * @param int $width
	 * @param int $height
	 *
	 * @return string PNG bytes
	 */
	private static function makePng(int $width = 200, int $height = 150): string
	{
		$img = \imagecreatetruecolor($width, $height);
		\imagecolorallocate($img, 100, 149, 237);
		\ob_start();
		\imagepng($img);

		return (string) \ob_get_clean();
	}

	/**
	 * Build a FileStream from a byte string.
	 *
	 * @param string $bytes
	 *
	 * @return FileStream
	 */
	private static function makeStream(string $bytes): FileStream
	{
		return FileStream::fromString($bytes);
	}

	/**
	 * Decode PNG bytes and return [width, height].
	 *
	 * @param string $bytes
	 *
	 * @return array{int, int}
	 */
	private static function pngDimensions(string $bytes): array
	{
		$img = \imagecreatefromstring($bytes);
		self::assertNotFalse($img, 'Response body must be a valid image');
		$w = \imagesx($img);
		$h = \imagesy($img);

		return [$w, $h];
	}

	/**
	 * Apply filters and return the response body bytes.
	 *
	 * @param OZFile   $file
	 * @param string   $imageBytes
	 * @param string[] $tokens
	 *
	 * @return string
	 */
	private static function applyFilters(OZFile $file, string $imageBytes, array $tokens): string
	{
		$handler  = new ImageFileFilterHandler();
		$stream   = self::makeStream($imageBytes);
		$response = new Response();
		$result   = $handler->handle($file, $stream, $response, $tokens);

		return (string) $result->getBody();
	}
}
