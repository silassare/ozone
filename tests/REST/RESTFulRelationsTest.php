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

use Gobl\DBAL\Relations\Interfaces\RelationInterface;
use Gobl\ORM\Exceptions\ORMQueryException;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\REST\RESTFulAPIRequest;
use OZONE\Core\REST\RESTFulRelationsHelper;
use PHPUnit\Framework\TestCase;

/**
 * Class RESTFulRelationsTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\REST\RESTFulAPIRequest
 * @covers \OZONE\Core\REST\RESTFulRelationsHelper
 */
final class RESTFulRelationsTest extends TestCase
{
	public function testRelationsParameterTakesAListOrAnArray(): void
	{
		$listed = new RESTFulAPIRequest(context(), ['relations' => 'files|country|files']);
		$array  = new RESTFulAPIRequest(context(), ['relations' => ['roles']]);

		self::assertSame(['files', 'country'], $listed->getRequestedRelations());
		self::assertSame(['roles'], $array->getRequestedRelations());
	}

	public function testRelationsParameterRejectsInvalidNames(): void
	{
		$this->expectException(ORMQueryException::class);
		$this->expectExceptionMessage('GOBL_ORM_REQUEST_INVALID_RELATIONS');

		new RESTFulAPIRequest(context(), ['relations' => 'files|bad name!']);
	}

	public function testPublicRelationsCanBeRequested(): void
	{
		RESTFulRelationsHelper::assertNotPrivateRelation(db()->getTableOrFail('oz_users')->getRelation('files'));

		$this->addToAssertionCount(1);
	}

	public function testPrivateRelationsAreForbidden(): void
	{
		$relation = $this->createMock(RelationInterface::class);
		$relation->method('isPrivate')->willReturn(true);
		$relation->method('getName')->willReturn('secrets');

		$this->expectException(ForbiddenException::class);

		RESTFulRelationsHelper::assertNotPrivateRelation($relation);
	}
}
