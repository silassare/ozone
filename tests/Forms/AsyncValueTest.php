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

namespace OZONE\Tests\Forms;

use OZONE\Core\Forms\AsyncValue;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormDataClean;
use OZONE\Core\Forms\FormValidationContext;
use PHPUnit\Framework\TestCase;

/**
 * Class AsyncValueTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Forms\AsyncValue
 */
final class AsyncValueTest extends TestCase
{
	public function testGetValueCallsFactory(): void
	{
		$fd      = $this->makeFormData(['key' => 'hello']);
		$dynamic = new AsyncValue(
			static fn (FormValidationContext $ctx) => $ctx->getCleanFormData()->get('key')
		);

		self::assertSame('hello', $dynamic->getValue($fd));
	}

	public function testGetValueReturnsNullWhenFactoryReturnsNull(): void
	{
		$fd      = $this->makeFormData([]);
		$dynamic = new AsyncValue(static fn () => null);

		self::assertNull($dynamic->getValue($fd));
	}

	public function testToArrayReturnsAsyncMarker(): void
	{
		$dynamic = new AsyncValue(static fn () => 42);

		self::assertSame(['$async' => true, '$preview' => null], $dynamic->toArray());
	}

	public function testToArrayWithNoPreviewFactoryAlwaysReturnsNullPreview(): void
	{
		$dynamic = new AsyncValue(static fn () => 'runtime');

		// Even inside withPreview(), no preview factory -> $preview stays null.
		$result = AsyncValue::withPreview(static fn () => $dynamic->toArray());

		self::assertSame(['$async' => true, '$preview' => null], $result);
	}

	public function testToArrayWithPreviewFactoryReturnsPreviewDuringDiscovery(): void
	{
		$dynamic = new AsyncValue(
			static fn (FormValidationContext $ctx) => $ctx->getCleanFormData()->get('x'),
			static fn () => ['a', 'b', 'c'],
		);

		// Outside discovery: no preview.
		self::assertSame(['$async' => true, '$preview' => null], $dynamic->toArray());

		// Inside withPreview(): preview is embedded.
		$result = AsyncValue::withPreview(static fn () => $dynamic->toArray());

		self::assertSame(['$async' => true, '$preview' => ['value' => ['a', 'b', 'c']]], $result);
	}

	public function testIsClientResolvableFalseWithoutPreview(): void
	{
		$dynamic = new AsyncValue(static fn () => 1);

		self::assertFalse($dynamic->isClientResolvable());
	}

	public function testIsClientResolvableTrueWithPreview(): void
	{
		$dynamic = new AsyncValue(static fn () => 1, static fn () => [1, 2, 3]);

		self::assertTrue($dynamic->isClientResolvable());
	}

	private function makeFormData(array $data): FormValidationContext
	{
		return new FormValidationContext(new FormData($data), new FormDataClean($data));
	}
}
