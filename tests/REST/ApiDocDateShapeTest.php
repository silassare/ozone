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

namespace OZONE\Tests\REST;

use Gobl\DBAL\Types\TypeDate;
use OpenApi\Annotations\Schema;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\REST\ApiDoc\GoblSchemaMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * What a date column promises a client, and that the promise is the truth.
 *
 * A date is stored as a big integer, or as a decimal when it keeps microseconds, and both are
 * answered as strings so that no digit is lost on the way through JSON. The documentation used to
 * describe the same field as `int64`, so a generator emitted a number for a value that arrives
 * quoted: `Date.parse("1763452800")` is `NaN` and `new Date(1763452800)` is January 1970, and
 * neither raises. A field whose shape is not declared changes under its readers without anything
 * failing to build, which is the whole reason to pin it here.
 *
 * @internal
 */
#[CoversClass(GoblSchemaMapper::class)]
final class ApiDocDateShapeTest extends TestCase
{
	/**
	 * Formats that name a number. `format` is a free string in OpenAPI, so nothing refuses these on
	 * a string schema; a code generator reads them and emits a numeric field.
	 */
	private const array NUMERIC_FORMATS = ['int32', 'int64', 'float', 'double', 'number', 'integer'];

	public function testADateIsDocumentedAsAStringAndNotAsANumber(): void
	{
		$schema = $this->schemaOf(new TypeDate());

		self::assertSame('string', $schema->type, 'a date arrives quoted, so it is documented as a string');
		self::assertNotContains(
			$schema->format,
			self::NUMERIC_FORMATS,
			'and is never given a format that names a number: a generator believes it'
		);
	}

	public function testADateKeepingMicrosecondsIsAStringToo(): void
	{
		$schema = $this->schemaOf(new TypeDate()->microseconds());

		self::assertSame('string', $schema->type);
		self::assertNotContains($schema->format, self::NUMERIC_FORMATS);
	}

	public function testTheDocumentedShapeIsWhatTheColumnAnswers(): void
	{
		$type   = new TypeDate();
		$schema = $this->schemaOf($type);

		// The value a read answers, for the same column the schema above describes.
		$answered = $type->dbToPhp(1_763_452_800, db());

		self::assertSame('string', $schema->type);
		self::assertIsString($answered, 'what the column answers is the shape the schema promises');
		self::assertTrue(\ctype_digit($answered), 'a count of seconds, in digits: ' . $answered);
	}

	public function testADateAskedToBeFormattedIsStillDocumentedAsAString(): void
	{
		$schema = $this->schemaOf(new TypeDate()->format(\DATE_ATOM));

		self::assertSame('string', $schema->type);
		self::assertSame('date-time', $schema->format, 'and says which string it is');
	}

	private function schemaOf(TypeDate $type): Schema
	{
		return ApiDoc::get()
			->gobl()
			->typeSchema($type);
	}
}
