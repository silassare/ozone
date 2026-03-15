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

namespace OZONE\Tests\Lang;

use OZONE\Core\Lang\I18nMessage;
use PHPUnit\Framework\TestCase;

/**
 * Class I18nMessageTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class I18nMessageTest extends TestCase
{
	public function testGetTextReturnsConstructorText(): void
	{
		$msg = new I18nMessage('MY_LANG_KEY');
		self::assertSame('MY_LANG_KEY', $msg->getText());
	}

	public function testGetDataReturnsConstructorData(): void
	{
		$data = ['name' => 'Alice', 'age' => 30];
		$msg  = new I18nMessage('MY_LANG_KEY', $data);
		self::assertSame($data, $msg->getData());
	}

	public function testGetDataDefaultsToEmptyArray(): void
	{
		$msg = new I18nMessage('KEY');
		self::assertSame([], $msg->getData());
	}

	public function testToArrayContainsTextAndData(): void
	{
		$msg = new I18nMessage('GREETING', ['name' => 'Bob']);
		self::assertSame(['text' => 'GREETING', 'data' => ['name' => 'Bob']], $msg->toArray());
	}

	public function testToArrayWithNoDataHasEmptyDataKey(): void
	{
		$msg = new I18nMessage('BARE_KEY');
		self::assertSame(['text' => 'BARE_KEY', 'data' => []], $msg->toArray());
	}
}
