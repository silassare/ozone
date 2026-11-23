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

namespace OZONE\Tests\FS\Enums;

use OZONE\Core\FS\Enums\FileKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The kind of a file, from its media type: every kind, and a script or a stylesheet served as
 * `text/...` taken for what it is rather than for a document.
 *
 * @internal
 */
#[CoversClass(FileKind::class)]
final class FileKindTest extends TestCase
{
	#[DataProvider('provideTheKindOfAMediaTypeCases')]
	public function testTheKindOfAMediaType(string $mime, FileKind $kind): void
	{
		self::assertSame($kind, FileKind::fromMime($mime));
	}

	/**
	 * @return iterable<string, array{string, FileKind}>
	 */
	public static function provideTheKindOfAMediaTypeCases(): iterable
	{
		yield 'image/png' => ['image/png', FileKind::IMAGE];

		yield 'image/svg+xml' => ['image/svg+xml', FileKind::IMAGE];

		yield 'audio/mpeg' => ['audio/mpeg', FileKind::AUDIO];

		yield 'video/mp4' => ['video/mp4', FileKind::VIDEO];

		yield 'text/javascript' => ['text/javascript', FileKind::JS];

		yield 'text/javascript with a charset' => ['text/javascript; charset=utf-8', FileKind::JS];

		yield 'application/javascript' => ['application/javascript', FileKind::JS];

		yield 'text/css' => ['text/css', FileKind::CSS];

		yield 'TEXT/CSS' => ['TEXT/CSS', FileKind::CSS];

		yield 'font/woff2' => ['font/woff2', FileKind::FONT];

		yield 'application/font-woff' => ['application/font-woff', FileKind::FONT];

		yield 'text/plain' => ['text/plain', FileKind::DOCUMENT];

		yield 'text/csv' => ['text/csv', FileKind::DOCUMENT];

		yield 'application/pdf' => ['application/pdf', FileKind::DOCUMENT];

		yield 'application/msword' => ['application/msword', FileKind::DOCUMENT];

		yield 'an OOXML document' => [
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			FileKind::DOCUMENT,
		];

		yield 'an OpenDocument spreadsheet' => ['application/vnd.oasis.opendocument.spreadsheet', FileKind::DOCUMENT];

		yield 'application/octet-stream' => ['application/octet-stream', FileKind::OTHER];

		yield 'an unknown type' => ['application/x-oz-unknown', FileKind::OTHER];
	}
}
