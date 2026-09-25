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

use LogicException;
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
	public function testGetValueCallsTheFactoryWithTheContext(): void
	{
		$ctx    = $this->context(['key' => 'hello']);
		$secret = AsyncValue::secret(
			static fn (FormValidationContext $ctx) => $ctx->getCleanFormData()->get('key')
		);
		$public = AsyncValue::public(
			static fn (FormValidationContext $ctx) => $ctx->getCleanFormData()->get('key'),
			static fn () => 'preview'
		);

		// The server compares against the value, never the preview.
		self::assertSame('hello', $secret->getValue($ctx));
		self::assertSame('hello', $public->getValue($ctx));
	}

	public function testASecretValueIsNeverSerialized(): void
	{
		$secret = AsyncValue::secret(static fn () => 'hidden');

		self::assertTrue($secret->isSecret());

		$this->expectException(LogicException::class);

		AsyncValue::withPreview(static fn () => $secret->toArray());
	}

	public function testAPublicValueSendsItsPreviewWhenSentToAClient(): void
	{
		$public = AsyncValue::public(static fn () => 'runtime', static fn () => ['a', 'b']);

		self::assertFalse($public->isSecret());
		self::assertSame(
			['$preview' => ['value' => ['a', 'b']]],
			AsyncValue::withPreview(static fn () => $public->toArray())
		);
		// Outside (a form's version fingerprint), the preview is not computed.
		self::assertSame(['$preview' => null], $public->toArray());
	}

	public function testAPreviewOfNullIsToldApartFromNone(): void
	{
		$public = AsyncValue::public(static fn () => null, static fn () => null);

		self::assertSame(
			['$preview' => ['value' => null]],
			AsyncValue::withPreview(static fn () => $public->toArray())
		);
	}

	private function context(array $data): FormValidationContext
	{
		return new FormValidationContext(new FormData($data), new FormDataClean($data));
	}
}
