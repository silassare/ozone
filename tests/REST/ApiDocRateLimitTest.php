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

use Override;
use OZONE\Core\Exceptions\RateLimitReachedException;
use OZONE\Core\REST\ApiDoc;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * What the API documentation states for a request over a rate limit is what a client is sent.
 *
 * @internal
 */
#[CoversClass(ApiDoc::class)]
final class ApiDocRateLimitTest extends TestCase
{
	#[Override]
	protected function tearDown(): void
	{
		ApiDoc::release();

		parent::tearDown();
	}

	public function testEveryDocumented429IsTheCodeARateLimitSends(): void
	{
		$sent  = new RateLimitReachedException()->getMessage();
		$paths = \json_decode(\json_encode(ApiDoc::get()->toArray()), true)['spec']['paths'];
		$found = [];

		foreach ($paths as $path => $operations) {
			foreach ($operations as $method => $operation) {
				$response = \is_array($operation) ? ($operation['responses']['429'] ?? null) : null;

				if (null !== $response) {
					$found[$method . ' ' . $path] = $response['content']['application/json']['schema']['properties']['msg']['default'] ?? null;
				}
			}
		}

		// The sign-in and the opening of a form session, at least.
		self::assertGreaterThanOrEqual(2, \count($found), \implode(', ', \array_keys($found)));
		self::assertSame(\array_fill_keys(\array_keys($found), $sent), $found);
	}
}
