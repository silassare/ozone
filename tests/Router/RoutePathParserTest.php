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

namespace OZONE\Tests\Router;

use InvalidArgumentException;
use OZONE\Core\Router\Route;
use OZONE\Core\Router\RoutePathParser;
use OZONE\Core\Router\Router;
use OZONE\Tests\TestUtils;
use PHPUnit\Framework\TestCase;

/**
 * Class RoutePathParserTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class RoutePathParserTest extends TestCase
{
	private Router $router;

	protected function setUp(): void
	{
		$this->router = TestUtils::router();
	}

	public function testIsValidParameterAcceptsAlphaStart(): void
	{
		self::assertTrue(RoutePathParser::isValidParameter('id'));
		self::assertTrue(RoutePathParser::isValidParameter('userId'));
		self::assertTrue(RoutePathParser::isValidParameter('user_id'));
		self::assertTrue(RoutePathParser::isValidParameter('_hidden'));
	}

	public function testIsValidParameterAcceptsUnderscoreStart(): void
	{
		self::assertTrue(RoutePathParser::isValidParameter('_id'));
		self::assertTrue(RoutePathParser::isValidParameter('_'));
	}

	public function testIsValidParameterRejectsDigitStart(): void
	{
		self::assertFalse(RoutePathParser::isValidParameter('1id'));
		self::assertFalse(RoutePathParser::isValidParameter('9'));
	}

	public function testIsValidParameterRejectsEmptyString(): void
	{
		self::assertFalse(RoutePathParser::isValidParameter(''));
	}

	public function testIsValidParameterRejectsSpecialChars(): void
	{
		self::assertFalse(RoutePathParser::isValidParameter('user-id'));
		self::assertFalse(RoutePathParser::isValidParameter('user.id'));
		self::assertFalse(RoutePathParser::isValidParameter('user id'));
	}

	public function testParseStaticSegment(): void
	{
		$parser  = new RoutePathParser('/users/list', $this->router);
		$pattern = $parser->parse('/users/list');
		// '/' is not special with the '~' delimiter so preg_quote leaves it unchanged.
		self::assertSame('/users/list', $pattern);
	}

	public function testParseEmptyPath(): void
	{
		$parser  = new RoutePathParser('/', $this->router);
		$pattern = $parser->parse('/');
		self::assertSame('/', $pattern);
	}

	public function testParseColonParameter(): void
	{
		$parser  = new RoutePathParser('/users/:id', $this->router);
		$pattern = $parser->parse('/users/:id');

		self::assertStringContainsString('(?P<id>', $pattern);
		self::assertStringContainsString(Route::DEFAULT_PARAM_PATTERN, $pattern);
	}

	public function testParseColonParameterWithCustomConstraint(): void
	{
		$parser  = new RoutePathParser('/items/:id', $this->router);
		$pattern = $parser->parse('/items/:id', ['id' => '[0-9]+']);

		self::assertStringContainsString('(?P<id>[0-9]+)', $pattern);
	}

	public function testParseMultipleColonParameters(): void
	{
		$parser  = new RoutePathParser('/users/:userId/posts/:postId', $this->router);
		$params  = [];
		$pattern = $parser->parse('/users/:userId/posts/:postId', [], $params);

		self::assertStringContainsString('(?P<userId>', $pattern);
		self::assertStringContainsString('(?P<postId>', $pattern);
		self::assertArrayHasKey('userId', $params);
		self::assertArrayHasKey('postId', $params);
	}

	public function testParseBraceParameter(): void
	{
		$parser  = new RoutePathParser('/users/{id}', $this->router);
		$pattern = $parser->parse('/users/{id}');

		self::assertStringContainsString('(?P<id>', $pattern);
		self::assertStringContainsString(Route::DEFAULT_PARAM_PATTERN, $pattern);
	}

	public function testParseBraceParameterWithConstraint(): void
	{
		$parser  = new RoutePathParser('/items/{id}', $this->router);
		$pattern = $parser->parse('/items/{id}', ['id' => '\d+']);

		self::assertStringContainsString('(?P<id>\d+)', $pattern);
	}

	public function testParseOptionalSegment(): void
	{
		$parser  = new RoutePathParser('/posts[/:slug]', $this->router);
		$params  = [];
		$pattern = $parser->parse('/posts[/:slug]', [], $params);

		// Optional part should be wrapped in non-capturing optional group.
		self::assertStringContainsString('(?:', $pattern);
		self::assertStringContainsString(')?', $pattern);
		self::assertStringContainsString('(?P<slug>', $pattern);
		self::assertSame(0, $params['slug']); // 0 = optional
	}

	public function testParseRequiredParamIsMarkedAsRequired(): void
	{
		$parser = new RoutePathParser('/:id', $this->router);
		$params = [];
		$parser->parse('/:id', [], $params);

		self::assertSame(1, $params['id']); // 1 = required
	}

	public function testParseThrowsOnDuplicateParameter(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$parser = new RoutePathParser('/:id/:id', $this->router);
		$parser->parse('/:id/:id');
	}

	public function testParseThrowsOnUnclosedBrace(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$parser = new RoutePathParser('/users/{id', $this->router);
		$parser->parse('/users/{id');
	}

	public function testParseThrowsOnUnclosedOptionalBracket(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$parser = new RoutePathParser('/users[/:id', $this->router);
		$parser->parse('/users[/:id');
	}

	public function testParseThrowsOnEmptyOptionalPart(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$parser = new RoutePathParser('/users[]', $this->router);
		$parser->parse('/users[]');
	}

	public function testParsedPatternMatchesDynamicUrl(): void
	{
		$parser  = new RoutePathParser('/users/:id', $this->router);
		$pattern = $parser->parse('/users/:id');
		$regexp  = Route::REG_DELIMITER . '^' . $pattern . '$' . Route::REG_DELIMITER;

		self::assertSame(1, \preg_match($regexp, '/users/42', $matches));
		self::assertSame('42', $matches['id']);
	}

	public function testParsedPatternDoesNotMatchWrongUrl(): void
	{
		$parser  = new RoutePathParser('/users/:id', $this->router);
		$pattern = $parser->parse('/users/:id');
		$regexp  = Route::REG_DELIMITER . '^' . $pattern . '$' . Route::REG_DELIMITER;

		self::assertSame(0, \preg_match($regexp, '/posts/42'));
	}

	public function testParsedOptionalPatternMatchesWithAndWithoutOptional(): void
	{
		$parser  = new RoutePathParser('/articles[/:slug]', $this->router);
		$pattern = $parser->parse('/articles[/:slug]');
		$regexp  = Route::REG_DELIMITER . '^' . $pattern . '$' . Route::REG_DELIMITER;

		// With optional segment.
		self::assertSame(1, \preg_match($regexp, '/articles/my-post', $matches));
		self::assertSame('my-post', $matches['slug']);

		// Without optional segment.
		self::assertSame(1, \preg_match($regexp, '/articles'));
	}
}
