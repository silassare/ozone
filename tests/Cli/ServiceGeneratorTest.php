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
use PHPUnit\Framework\TestCase;

/**
 * Class ServiceGeneratorTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Cli\Utils\ServiceGenerator
 */
final class ServiceGeneratorTest extends TestCase
{
	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function paths(): array
	{
		return [
			'one segment'   => ['/countries', 'countries'],
			'nested'        => ['/shop/orders', 'shop.orders'],
			'with a dash'   => ['/order-items', 'order_items'],
			'in capitals'   => ['/Shop/Order-Items/', 'shop.order_items'],
		];
	}

	/**
	 * @dataProvider paths
	 */
	public function testNamesAServiceAfterItsBasePath(string $path, string $name): void
	{
		self::assertSame($name, ServiceGenerator::defaultName($path));
		self::assertMatchesRegularExpression(RESTFulService::SERVICE_NAME_REG, $name);
	}
}
