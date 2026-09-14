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

use Gobl\ORM\Exceptions\ORMQueryException;
use OZONE\Core\App\Context;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\REST\RESTFulAPIRequest;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Class RESTFulAPIRequestTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\REST\RESTFulAPIRequest
 */
final class RESTFulAPIRequestTest extends TestCase
{
	public function testOffsetPagination(): void
	{
		$request = new RESTFulAPIRequest(self::context(), ['max' => 5, 'page' => 2]);

		self::assertSame(5, $request->getMax());
		self::assertSame(2, $request->getPage());
		self::assertFalse($request->isCursorBased());
	}

	public function testCursorPagination(): void
	{
		$request = new RESTFulAPIRequest(self::context(), [
			'max'           => 10,
			'cursor'        => 42,
			'cursor_column' => 'id',
			'cursor_dir'    => 'desc',
		]);

		self::assertTrue($request->isCursorBased());
		self::assertSame(42, $request->getCursor());
		self::assertSame('id', $request->getCursorColumn());
		self::assertEqualsIgnoringCase('desc', (string) $request->getCursorDirection());
	}

	public function testPageAndCursorAreMutuallyExclusive(): void
	{
		$failed = false;

		try {
			$request = new RESTFulAPIRequest(self::context(), ['page' => 2, 'cursor' => 1]);
			$request->getPage();
			$request->isCursorBased();
		} catch (Throwable) {
			$failed = true;
		}

		self::assertTrue($failed, 'Mixing page and cursor pagination must be rejected.');
	}

	public function testScopedInstanceKeepsTheContext(): void
	{
		$context = self::context();
		$request = new RESTFulAPIRequest($context, ['max' => 3]);

		self::assertSame($context, $request->createScopedInstance('relation')->getContext());
	}

	public function testFiltersMustBeAnArray(): void
	{
		$this->expectException(ORMQueryException::class);
		$this->expectExceptionMessage('GOBL_ORM_REQUEST_INVALID_FILTERS');

		new RESTFulAPIRequest(self::context(), ['filters' => '["user_id", "eq", 1]']);
	}

	public function testEnsuredFiltersAreAndedBeforeTheClientFilters(): void
	{
		$request = new RESTFulAPIRequest(self::context(), [
			'filters' => [['user_email', 'eq', 'jane@example.com']],
		]);

		self::assertSame([['user_email', 'eq', 'jane@example.com']], $request->getFilters());

		// e.g. the key filter of an entry route
		$request->ensureOnlyFilters(['user_id', 'eq', 7]);

		self::assertSame(
			[[['user_id', 'eq', 7]], 'and', [['user_email', 'eq', 'jane@example.com']]],
			$request->getFilters()
		);
	}

	public function testCollectionParameter(): void
	{
		$request = new RESTFulAPIRequest(self::context(), ['collection' => 'active']);

		self::assertSame('active', $request->getRequestedCollection());

		$this->expectException(ORMQueryException::class);
		$this->expectExceptionMessage('GOBL_ORM_REQUEST_INVALID_COLLECTION');

		new RESTFulAPIRequest(self::context(), ['collection' => 'not a name!']);
	}

	public function testOtherParametersAreTheFormData(): void
	{
		$request = new RESTFulAPIRequest(self::context(), [
			'max'        => 5,
			'filters'    => [],
			'user_email' => 'jane@example.com',
		]);

		self::assertSame(['user_email' => 'jane@example.com'], $request->getFormData());
	}

	private static function context(): Context
	{
		return new Context(HTTPEnvironment::mock(), null, Context::root());
	}
}
