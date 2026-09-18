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

namespace OZONE\Tests\Crypt;

use ArrayObject;
use OZONE\Core\Crypt\SignedSerializer;
use PHPUnit\Framework\TestCase;

/**
 * Class SignedSerializerTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Crypt\SignedSerializer
 */
final class SignedSerializerTest extends TestCase
{
	public function testRoundTripKeepsObjects(): void
	{
		$value = ['list' => new ArrayObject([1, 2, 3])];

		[$signed, $out] = SignedSerializer::unserialize(SignedSerializer::serialize($value));

		self::assertTrue($signed);
		// unserialize() builds new objects, never the same instances: the content is compared (an
		// assertEquals() here is turned into assertSame() by the code style fixer)
		self::assertSame(['list'], \array_keys($out));
		self::assertInstanceOf(ArrayObject::class, $out['list']);
		self::assertSame([1, 2, 3], $out['list']->getArrayCopy());
	}

	public function testTamperedPayloadIsRejected(): void
	{
		$raw = SignedSerializer::serialize(['admin' => false]);

		self::assertSame([false, null], SignedSerializer::unserialize(\str_replace('b:0', 'b:1', $raw)));
	}

	public function testUnsignedPayloadIsRejected(): void
	{
		self::assertSame([false, null], SignedSerializer::unserialize(\serialize(['x' => 1])));
		self::assertSame([false, null], SignedSerializer::unserialize(''));
	}

	public function testForeignObjectIsNeverInstantiated(): void
	{
		SignedSerializerProbe::$woken = false;

		SignedSerializer::unserialize('forged-signature:' . \serialize(new SignedSerializerProbe()));

		self::assertFalse(SignedSerializerProbe::$woken);
	}
}

/**
 * Records whether it was ever unserialized.
 *
 * @internal
 */
final class SignedSerializerProbe
{
	public static bool $woken = false;

	public function __unserialize(array $data): void
	{
		self::$woken = true;
	}
}
