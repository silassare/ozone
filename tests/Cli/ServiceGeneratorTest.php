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

namespace OZONE\Tests\Cli;

use OZONE\Core\Cli\Utils\ServiceGenerator;
use OZONE\Core\REST\RESTFulService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class ServiceGeneratorTest.
 *
 * @internal
 */
#[CoversClass(ServiceGenerator::class)]
final class ServiceGeneratorTest extends TestCase
{
	#[DataProvider('provideNamesAServiceAfterItsBasePathCases')]
	public function testNamesAServiceAfterItsBasePath(string $path, string $name): void
	{
		self::assertSame($name, ServiceGenerator::defaultName($path));
		self::assertMatchesRegularExpression(RESTFulService::SERVICE_NAME_REG, $name);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function provideNamesAServiceAfterItsBasePathCases(): iterable
	{
		return [
			'one segment'   => ['/countries', 'countries'],
			'nested'        => ['/shop/orders', 'shop.orders'],
			'with a dash'   => ['/order-items', 'order_items'],
			'in capitals'   => ['/Shop/Order-Items/', 'shop.order_items'],
		];
	}
}
