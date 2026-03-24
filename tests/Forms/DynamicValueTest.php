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

use OZONE\Core\Forms\DynamicValue;
use OZONE\Core\Forms\FormData;
use PHPUnit\Framework\TestCase;

/**
 * Class DynamicValueTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class DynamicValueTest extends TestCase
{
	public function testGetValueCallsFactory(): void
	{
		$fd      = $this->makeFormData(['key' => 'hello']);
		$dynamic = new DynamicValue(static fn (FormData $fd) => $fd->get('key'));

		self::assertSame('hello', $dynamic->getValue($fd));
	}

	public function testGetValueReturnsNullWhenFactoryReturnsNull(): void
	{
		$fd      = $this->makeFormData([]);
		$dynamic = new DynamicValue(static fn () => null);

		self::assertNull($dynamic->getValue($fd));
	}

	public function testToArrayReturnsDynamicMarker(): void
	{
		$dynamic = new DynamicValue(static fn () => 42);

		self::assertSame(['$dynamic' => true, '$preview' => null], $dynamic->toArray());
	}

	public function testToArrayWithNoPreviewFactoryAlwaysReturnsNullPreview(): void
	{
		$dynamic = new DynamicValue(static fn () => 'runtime');

		// Even inside withDiscovery(), no preview factory -> $preview stays null.
		$result = DynamicValue::withDiscovery(static fn () => $dynamic->toArray());

		self::assertSame(['$dynamic' => true, '$preview' => null], $result);
	}

	public function testToArrayWithPreviewFactoryReturnsPreviewDuringDiscovery(): void
	{
		$dynamic = new DynamicValue(
			static fn (FormData $fd) => $fd->get('x'),
			static fn () => ['a', 'b', 'c'],
		);

		// Outside discovery: no preview.
		self::assertSame(['$dynamic' => true, '$preview' => null], $dynamic->toArray());

		// Inside withDiscovery(): preview is embedded.
		$result = DynamicValue::withDiscovery(static fn () => $dynamic->toArray());

		self::assertSame(['$dynamic' => true, '$preview' => ['value' => ['a', 'b', 'c']]], $result);
	}

	public function testIsClientResolvableFalseWithoutPreview(): void
	{
		$dynamic = new DynamicValue(static fn () => 1);

		self::assertFalse($dynamic->isClientResolvable());
	}

	public function testIsClientResolvableTrueWithPreview(): void
	{
		$dynamic = new DynamicValue(static fn () => 1, static fn () => [1, 2, 3]);

		self::assertTrue($dynamic->isClientResolvable());
	}

	private function makeFormData(array $data): FormData
	{
		return new FormData($data);
	}
}
