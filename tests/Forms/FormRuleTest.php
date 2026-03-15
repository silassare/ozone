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

namespace OZONE\Tests\Forms;

use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormRule;
use OZONE\Core\Forms\FormValidationContext;
use PHPUnit\Framework\TestCase;

/**
 * Class FormRuleTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class FormRuleTest extends TestCase
{
    private function makeContext(array $unsafe): FormValidationContext
    {
        return new FormValidationContext(
            new FormData($unsafe),
            new FormData([])
        );
    }

    // -----------------------------------------------------------
    // eq
    // -----------------------------------------------------------

    public function testEqPassesWhenEqual(): void
    {
        $rule = (new FormRule())->eq('type', 'admin');
        self::assertTrue($rule->check($this->makeContext(['type' => 'admin'])));
    }

    public function testEqFailsWhenNotEqual(): void
    {
        $rule = (new FormRule())->eq('type', 'admin');
        self::assertFalse($rule->check($this->makeContext(['type' => 'user'])));
    }

    // -----------------------------------------------------------
    // neq
    // -----------------------------------------------------------

    public function testNeqPassesWhenNotEqual(): void
    {
        $rule = (new FormRule())->neq('status', 'banned');
        self::assertTrue($rule->check($this->makeContext(['status' => 'active'])));
    }

    public function testNeqFailsWhenEqual(): void
    {
        $rule = (new FormRule())->neq('status', 'banned');
        self::assertFalse($rule->check($this->makeContext(['status' => 'banned'])));
    }

    // -----------------------------------------------------------
    // gt / gte / lt / lte
    // -----------------------------------------------------------

    public function testGtPassesWhenGreater(): void
    {
        $rule = (new FormRule())->gt('age', 18);
        self::assertTrue($rule->check($this->makeContext(['age' => 25])));
        self::assertFalse($rule->check($this->makeContext(['age' => 18])));
        self::assertFalse($rule->check($this->makeContext(['age' => 10])));
    }

    public function testGtePassesWhenGreaterOrEqual(): void
    {
        $rule = (new FormRule())->gte('age', 18);
        self::assertTrue($rule->check($this->makeContext(['age' => 18])));
        self::assertTrue($rule->check($this->makeContext(['age' => 25])));
        self::assertFalse($rule->check($this->makeContext(['age' => 17])));
    }

    public function testLtPassesWhenLess(): void
    {
        $rule = (new FormRule())->lt('score', 100);
        self::assertTrue($rule->check($this->makeContext(['score' => 50])));
        self::assertFalse($rule->check($this->makeContext(['score' => 100])));
    }

    public function testLtePassesWhenLessOrEqual(): void
    {
        $rule = (new FormRule())->lte('score', 100);
        self::assertTrue($rule->check($this->makeContext(['score' => 100])));
        self::assertTrue($rule->check($this->makeContext(['score' => 50])));
        self::assertFalse($rule->check($this->makeContext(['score' => 101])));
    }

    // -----------------------------------------------------------
    // in / notIn
    // -----------------------------------------------------------

    public function testInPassesWhenValueIsInList(): void
    {
        $rule = (new FormRule())->in('role', ['admin', 'editor', 'user']);
        self::assertTrue($rule->check($this->makeContext(['role' => 'admin'])));
        self::assertFalse($rule->check($this->makeContext(['role' => 'guest'])));
    }

    public function testNotInPassesWhenValueIsNotInList(): void
    {
        $rule = (new FormRule())->notIn('role', ['banned', 'deleted']);
        self::assertTrue($rule->check($this->makeContext(['role' => 'admin'])));
        self::assertFalse($rule->check($this->makeContext(['role' => 'banned'])));
    }

    // -----------------------------------------------------------
    // isNull / isNotNull
    // -----------------------------------------------------------

    public function testIsNullPassesWhenFieldAbsent(): void
    {
        $rule = (new FormRule())->isNull('optional_field');
        self::assertTrue($rule->check($this->makeContext([])));
    }

    public function testIsNullFailsWhenFieldPresent(): void
    {
        $rule = (new FormRule())->isNull('field');
        self::assertFalse($rule->check($this->makeContext(['field' => 'something'])));
    }

    public function testIsNotNullPassesWhenFieldPresent(): void
    {
        $rule = (new FormRule())->isNotNull('field');
        self::assertTrue($rule->check($this->makeContext(['field' => 'value'])));
    }

    public function testIsNotNullFailsWhenFieldAbsent(): void
    {
        $rule = (new FormRule())->isNotNull('field');
        self::assertFalse($rule->check($this->makeContext([])));
    }

    // -----------------------------------------------------------
    // Cross-field compare (Field as right-hand side)
    // -----------------------------------------------------------

    public function testEqWithTwoFieldsPassesWhenBothMatch(): void
    {
        $form = new \OZONE\Core\Forms\Form();
        $pw   = $form->field('password');
        $cpw  = $form->field('confirm_password');

        $rule = (new FormRule())->eq($pw, $cpw);

        self::assertTrue($rule->check($this->makeContext([
            'password'         => 'secret',
            'confirm_password' => 'secret',
        ])));

        self::assertFalse($rule->check($this->makeContext([
            'password'         => 'secret',
            'confirm_password' => 'other',
        ])));
    }

    // -----------------------------------------------------------
    // Multiple chained conditions
    // -----------------------------------------------------------

    public function testMultipleChainedRulesAllMustPass(): void
    {
        $rule = (new FormRule())
            ->isNotNull('name')
            ->gt('age', 0)
            ->in('role', ['admin', 'user']);

        self::assertTrue($rule->check($this->makeContext([
            'name' => 'Alice',
            'age'  => 25,
            'role' => 'admin',
        ])));

        self::assertFalse($rule->check($this->makeContext([
            'name' => 'Alice',
            'age'  => 25,
            'role' => 'forbidden',
        ])));
    }

    // -----------------------------------------------------------
    // toArray snapshot
    // -----------------------------------------------------------

    public function testToArraySnapshot(): void
    {
        $rule = (new FormRule())
            ->eq('type', 'admin', 'TYPE_MUST_BE_ADMIN')
            ->isNotNull('email')
            ->in('status', ['active', 'pending']);

        self::assertEquals([
            ['field' => 'type',   'rule' => 'eq',         'value' => 'admin',              'message' => 'TYPE_MUST_BE_ADMIN'],
            ['field' => 'email',  'rule' => 'is_not_null', 'value' => null,                 'message' => null],
            ['field' => 'status', 'rule' => 'in',          'value' => ['active', 'pending'], 'message' => null],
        ], $rule->toArray());
    }

    // -----------------------------------------------------------
    // getErrorMessage
    // -----------------------------------------------------------

    public function testGetErrorMessageReturnsNullBeforeCheck(): void
    {
        $rule = (new FormRule())->eq('x', 'y');
        self::assertNull($rule->getErrorMessage());
    }

    public function testGetErrorMessageReturnsMessageAfterFailedCheck(): void
    {
        $rule = (new FormRule())->eq('x', 'expected', 'VALUE_MISMATCH');
        $rule->check($this->makeContext(['x' => 'actual']));
        self::assertSame('VALUE_MISMATCH', $rule->getErrorMessage());
    }
}
