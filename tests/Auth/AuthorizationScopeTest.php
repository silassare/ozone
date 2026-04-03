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

namespace OZONE\Tests\Auth;

use InvalidArgumentException;
use OZONE\Core\Auth\AuthorizationScope;
use OZONE\Core\Exceptions\UnauthorizedException;
use PHPUnit\Framework\TestCase;

/**
 * Tests AuthorizationScope configuration and its interaction with access rights.
 *
 * @internal
 *
 * @coversNothing
 */
final class AuthorizationScopeTest extends TestCase
{
	public function testDefaultLifetimeIsPositive(): void
	{
		$scope = new AuthorizationScope();
		self::assertGreaterThan(0, $scope->getLifetime());
	}

	public function testDefaultTryMaxIsZeroOrPositive(): void
	{
		$scope = new AuthorizationScope();
		self::assertGreaterThanOrEqual(0, $scope->getTryMax());
	}

	public function testSetLifetimeAcceptsPositiveInteger(): void
	{
		$scope = new AuthorizationScope();
		$scope->setLifetime(120);
		self::assertSame(120, $scope->getLifetime());
	}

	public function testSetLifetimeRejectsZero(): void
	{
		$this->expectException(InvalidArgumentException::class);
		(new AuthorizationScope())->setLifetime(0);
	}

	public function testSetLifetimeRejectsNegative(): void
	{
		$this->expectException(InvalidArgumentException::class);
		(new AuthorizationScope())->setLifetime(-5);
	}

	public function testSetTryMaxRejectsZero(): void
	{
		$this->expectException(InvalidArgumentException::class);
		(new AuthorizationScope())->setTryMax(0);
	}

	public function testSetTryMaxAcceptsPositiveInteger(): void
	{
		$scope = new AuthorizationScope();
		$scope->setTryMax(5);
		self::assertSame(5, $scope->getTryMax());
	}

	public function testSetLabelPersists(): void
	{
		$scope = new AuthorizationScope();
		$scope->setLabel('Email verification');
		self::assertSame('Email verification', $scope->getLabel());
	}

	public function testAccessRightDefaultsAreEmpty(): void
	{
		$scope = new AuthorizationScope();
		self::assertSame([], $scope->getAccessRight()->toArray());
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function testSetAccessRightAllowsScoping(): void
	{
		$scope = new AuthorizationScope();
		$scope->getAccessRight()->allow('files.download');
		self::assertTrue($scope->getAccessRight()->can('files.download'));
		self::assertFalse($scope->getAccessRight()->can('files.delete'));
	}
}
