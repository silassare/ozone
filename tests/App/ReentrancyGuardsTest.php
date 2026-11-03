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

namespace OZONE\Tests\App;

use InvalidArgumentException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Http\Headers;
use OZONE\Core\Http\Request;
use OZONE\Core\Http\RequestBody;
use OZONE\Core\Http\Uri;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Lazy work that can call back into itself: the guard has to say what is *being done*, never what is
 * finished.
 *
 * @internal
 */
#[CoversNothing]
final class ReentrancyGuardsTest extends TestCase
{
	public function testAMediaTypeParserCannotReadTheBodyItIsParsing(): void
	{
		// The parser is registered by a project, and the parsed body is memoised once it answers --
		// so a parser reading it used to parse the whole body again, from inside its own parse.
		$headers = new Headers();
		$headers->set('Content-Type', ['application/x-probe']);

		$body = new RequestBody();
		$body->write('probe=1');
		$body->rewind();

		$request = new Request(
			'POST',
			Uri::createFromString('http://localhost/probe'),
			$headers,
			[],
			[],
			$body,
		);

		$request->registerMediaTypeParser('application/x-probe', static function (string $raw) use (&$request): array {
			// What a parser must not do: ask for the body it is being called to parse. The raw body
			// is what it was handed and what it would read, so the refusal happens before this
			// returns anything at all.
			return ['raw' => $raw, 'parsed' => $request->getParsedBody()];
		});

		try {
			$request->getParsedBody();

			self::fail('A parser reading what it is parsing must be stopped.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('while it was being parsed', $e->getMessage());
		}
	}
}
