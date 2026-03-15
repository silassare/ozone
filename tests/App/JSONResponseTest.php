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

use OZONE\Core\App\JSONResponse;
use PHPUnit\Framework\TestCase;

/**
 * Class JSONResponseTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class JSONResponseTest extends TestCase
{
    public function testDefaultsToSuccessWithOkMessage(): void
    {
        $r = new JSONResponse();
        $a = $r->toArray();

        self::assertSame(JSONResponse::RESPONSE_CODE_SUCCESS, $a['error']);
        self::assertSame('OK', $a['msg']);
        self::assertSame([], $a['data']);
    }

    public function testSetErrorFlagAndMessage(): void
    {
        $r = (new JSONResponse())->setError('SOMETHING_WENT_WRONG');
        $a = $r->toArray();

        self::assertSame(JSONResponse::RESPONSE_CODE_ERROR, $a['error']);
        self::assertSame('SOMETHING_WENT_WRONG', $a['msg']);
    }

    public function testSetErrorDefaultMessage(): void
    {
        $r = (new JSONResponse())->setError();
        self::assertSame('OZ_ERROR_INTERNAL', $r->toArray()['msg']);
    }

    public function testSetDoneResetsToSuccess(): void
    {
        $r = (new JSONResponse())
            ->setError('OOPS')
            ->setDone('TASK_DONE');

        $a = $r->toArray();
        self::assertSame(JSONResponse::RESPONSE_CODE_SUCCESS, $a['error']);
        self::assertSame('TASK_DONE', $a['msg']);
    }

    public function testSetDataOverwritesData(): void
    {
        $r = (new JSONResponse())->setData(['items' => [1, 2, 3]]);
        self::assertSame(['items' => [1, 2, 3]], $r->toArray()['data']);
    }

    public function testSetDataKeyAddsKeyToData(): void
    {
        $r = (new JSONResponse())
            ->setDataKey('count', 42)
            ->setDataKey('name', 'Alice');

        $a = $r->toArray();
        self::assertSame(42, $a['data']['count']);
        self::assertSame('Alice', $a['data']['name']);
    }

    public function testGetDataKeyReturnsValueOrDefault(): void
    {
        $r = (new JSONResponse())->setDataKey('x', 99);
        self::assertSame(99, $r->getDataKey('x'));
        self::assertNull($r->getDataKey('missing'));
        self::assertSame('def', $r->getDataKey('missing', 'def'));
    }

    public function testSetDataKeyIgnoresEmptyKey(): void
    {
        $r   = new JSONResponse();
        $r->setDataKey('', 'should-be-ignored');
        self::assertSame([], $r->toArray()['data']);
    }

    public function testMergeOverwritesFromOtherResponse(): void
    {
        $source = (new JSONResponse())->setError('ERR')->setDataKey('x', 1);
        $target = new JSONResponse();
        $target->merge($source);

        $a = $target->toArray();
        self::assertSame(JSONResponse::RESPONSE_CODE_ERROR, $a['error']);
        self::assertSame('ERR', $a['msg']);
        self::assertSame(1, $a['data']['x']);
    }

    public function testToArraySnapshotForComplexResponse(): void
    {
        $r = (new JSONResponse())
            ->setDone('USER_LISTED')
            ->setDataKey('users', [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']])
            ->setDataKey('total', 2);

        self::assertEquals([
            'error' => 0,
            'msg'   => 'USER_LISTED',
            'data'  => [
                'users' => [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']],
                'total' => 2,
            ],
        ], $r->toArray());
    }

    public function testToArrayExcludesFormKeyWhenFormIsNull(): void
    {
        $r = new JSONResponse();
        self::assertArrayNotHasKey('form', $r->toArray());
    }

    public function testConstantsValues(): void
    {
        self::assertSame(0, JSONResponse::RESPONSE_CODE_SUCCESS);
        self::assertSame(1, JSONResponse::RESPONSE_CODE_ERROR);
    }
}
