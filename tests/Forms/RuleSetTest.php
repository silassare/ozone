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

use OZONE\Core\Forms\DynamicValue;
use OZONE\Core\Forms\Enums\RuleSetCondition;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\Rule;
use OZONE\Core\Forms\RuleSet;
use OZONE\Core\Forms\RuleViolation;
use PHPUnit\Framework\TestCase;

/**
 * Class RuleSetTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class RuleSetTest extends TestCase
{
	public function testEqPassesWhenEqual(): void
	{
		$rs = (new RuleSet())->eq('type', 'admin');
		self::assertTrue($rs->check($this->fd(['type' => 'admin'])));
	}

	public function testEqFailsWhenNotEqual(): void
	{
		$rs = (new RuleSet())->eq('type', 'admin');
		self::assertFalse($rs->check($this->fd(['type' => 'user'])));
	}

	public function testNeqPassesWhenNotEqual(): void
	{
		$rs = (new RuleSet())->neq('status', 'banned');
		self::assertTrue($rs->check($this->fd(['status' => 'active'])));
	}

	public function testNeqFailsWhenEqual(): void
	{
		$rs = (new RuleSet())->neq('status', 'banned');
		self::assertFalse($rs->check($this->fd(['status' => 'banned'])));
	}

	public function testGtPassesWhenGreater(): void
	{
		$rs = (new RuleSet())->gt('age', 18);
		self::assertTrue($rs->check($this->fd(['age' => 25])));
		self::assertFalse($rs->check($this->fd(['age' => 18])));
		self::assertFalse($rs->check($this->fd(['age' => 10])));
	}

	public function testGtePassesWhenGreaterOrEqual(): void
	{
		$rs = (new RuleSet())->gte('age', 18);
		self::assertTrue($rs->check($this->fd(['age' => 18])));
		self::assertTrue($rs->check($this->fd(['age' => 25])));
		self::assertFalse($rs->check($this->fd(['age' => 17])));
	}

	public function testLtPassesWhenLess(): void
	{
		$rs = (new RuleSet())->lt('score', 100);
		self::assertTrue($rs->check($this->fd(['score' => 50])));
		self::assertFalse($rs->check($this->fd(['score' => 100])));
	}

	public function testLtePassesWhenLessOrEqual(): void
	{
		$rs = (new RuleSet())->lte('score', 100);
		self::assertTrue($rs->check($this->fd(['score' => 100])));
		self::assertTrue($rs->check($this->fd(['score' => 50])));
		self::assertFalse($rs->check($this->fd(['score' => 101])));
	}

	public function testInPassesWhenValueIsInList(): void
	{
		$rs = (new RuleSet())->in('role', ['admin', 'editor', 'user']);
		self::assertTrue($rs->check($this->fd(['role' => 'admin'])));
		self::assertFalse($rs->check($this->fd(['role' => 'guest'])));
	}

	public function testNotInPassesWhenValueIsNotInList(): void
	{
		$rs = (new RuleSet())->notIn('role', ['banned', 'deleted']);
		self::assertTrue($rs->check($this->fd(['role' => 'admin'])));
		self::assertFalse($rs->check($this->fd(['role' => 'banned'])));
	}

	public function testIsNullPassesWhenFieldAbsent(): void
	{
		$rs = (new RuleSet())->isNull('optional_field');
		self::assertTrue($rs->check($this->fd([])));
	}

	public function testIsNullFailsWhenFieldPresent(): void
	{
		$rs = (new RuleSet())->isNull('field');
		self::assertFalse($rs->check($this->fd(['field' => 'something'])));
	}

	public function testIsNotNullPassesWhenFieldPresent(): void
	{
		$rs = (new RuleSet())->isNotNull('field');
		self::assertTrue($rs->check($this->fd(['field' => 'value'])));
	}

	public function testIsNotNullFailsWhenFieldAbsent(): void
	{
		$rs = (new RuleSet())->isNotNull('field');
		self::assertFalse($rs->check($this->fd([])));
	}

	public function testEqWithTwoFieldsPassesWhenBothMatch(): void
	{
		$form = new Form();
		$pw   = $form->field('password');
		$cpw  = $form->field('confirm_password');

		$rs = (new RuleSet())->eq($pw, $cpw);

		self::assertTrue($rs->check($this->fd([
			'password'         => 'secret',
			'confirm_password' => 'secret',
		])));

		self::assertFalse($rs->check($this->fd([
			'password'         => 'secret',
			'confirm_password' => 'other',
		])));
	}

	public function testMultipleChainedRulesAllMustPass(): void
	{
		$rs = (new RuleSet())
			->isNotNull('name')
			->gt('age', 0)
			->in('role', ['admin', 'user']);

		self::assertTrue($rs->check($this->fd([
			'name' => 'Alice',
			'age'  => 25,
			'role' => 'admin',
		])));

		self::assertFalse($rs->check($this->fd([
			'name' => 'Alice',
			'age'  => 25,
			'role' => 'forbidden',
		])));
	}

	public function testOrGroupPassesWhenAnyBranchMatches(): void
	{
		$rs = (new RuleSet())->or(static function (RuleSet $sub): void {
			$sub->eq('status', 'active')->eq('status', 'pending');
		});

		// Direct OR: all children of $sub are AND-combined — both can't pass simultaneously
		// Let's use two separate OR branches via nested or():
		$rs2 = (new RuleSet())
			->or(static function (RuleSet $sub): void {
				$sub->eq('role', 'admin');
			})
			->or(static function (RuleSet $sub): void {
				$sub->eq('role', 'moderator');
			});

		// The outer $rs2 is AND — both OR sub-groups must pass
		// Let's use a proper OR top-level rule set:
		$rs3 = new RuleSet(RuleSetCondition::OR);
		$rs3->eq('role', 'admin');
		$rs3->eq('role', 'moderator');

		self::assertTrue($rs3->check($this->fd(['role' => 'admin'])));
		self::assertTrue($rs3->check($this->fd(['role' => 'moderator'])));
		self::assertFalse($rs3->check($this->fd(['role' => 'guest'])));
	}

	public function testOrGroupFailsWhenNoBranchMatches(): void
	{
		$rs = new RuleSet(RuleSetCondition::OR);
		$rs->eq('status', 'active');
		$rs->eq('status', 'pending');

		self::assertFalse($rs->check($this->fd(['status' => 'banned'])));
	}

	public function testEmptyOrGroupAlwaysPasses(): void
	{
		$rs = new RuleSet(RuleSetCondition::OR);
		self::assertTrue($rs->check($this->fd([])));
	}

	public function testAndNestingAllMustPass(): void
	{
		$rs = (new RuleSet())
			->eq('type', 'premium')
			->and(static function (RuleSet $sub): void {
				$sub->isNotNull('email')->gt('age', 18);
			});

		self::assertTrue($rs->check($this->fd([
			'type'  => 'premium',
			'email' => 'x@x.com',
			'age'   => 25,
		])));

		self::assertFalse($rs->check($this->fd([
			'type'  => 'premium',
			'email' => 'x@x.com',
			'age'   => 10,
		])));
	}

	public function testGetViolationIsNullBeforeCheck(): void
	{
		$rs = (new RuleSet())->eq('x', 'y');
		self::assertNull($rs->getViolation());
	}

	public function testGetViolationReturnsNullOnPass(): void
	{
		$rs = (new RuleSet())->eq('x', 'y');
		$rs->check($this->fd(['x' => 'y']));
		self::assertNull($rs->getViolation());
	}

	public function testGetViolationReturnsViolationOnFailure(): void
	{
		$rs = (new RuleSet())->eq('x', 'expected', 'VALUE_MISMATCH');
		$rs->check($this->fd(['x' => 'actual']));

		$violation = $rs->getViolation();
		self::assertInstanceOf(RuleViolation::class, $violation);
		self::assertSame('VALUE_MISMATCH', $violation->getMessage());
	}

	public function testGetViolationMessageReturnsMessageAfterFailedCheck(): void
	{
		$rs = (new RuleSet())->eq('x', 'expected', 'VALUE_MISMATCH');
		$rs->check($this->fd(['x' => 'actual']));
		self::assertSame('VALUE_MISMATCH', $rs->getViolationMessage());
	}

	public function testGetViolationMessageIsNullBeforeCheck(): void
	{
		$rs = (new RuleSet())->eq('x', 'y');
		self::assertNull($rs->getViolationMessage());
	}

	public function testViolationNodeIsRule(): void
	{
		$rs = (new RuleSet())->eq('x', 'val');
		$rs->check($this->fd(['x' => 'other']));
		self::assertInstanceOf(Rule::class, $rs->getViolation()?->getNode());
	}

	public function testIsServerOnlyFalseForScalarValues(): void
	{
		$rs = (new RuleSet())->eq('field', 'value');
		self::assertFalse($rs->isServerOnly());
	}

	public function testIsServerOnlyTrueWhenDynamicValuePresent(): void
	{
		$rs = (new RuleSet())->eq('field', new DynamicValue(static fn () => 'x'));
		self::assertTrue($rs->isServerOnly());
	}

	public function testIsServerOnlyTrueWhenDynamicValueInNestedGroup(): void
	{
		$rs = (new RuleSet())
			->eq('a', 'b')
			->and(static function (RuleSet $sub): void {
				$sub->eq('x', new DynamicValue(static fn () => 'val'));
			});

		self::assertTrue($rs->isServerOnly());
	}

	public function testToArrayStructureForFlatAnd(): void
	{
		$rs = (new RuleSet())
			->eq('type', 'admin', 'TYPE_MUST_BE_ADMIN')
			->isNotNull('email')
			->in('status', ['active', 'pending']);

		$arr = $rs->toArray();

		self::assertSame('and', $arr['condition']);
		self::assertCount(3, $arr['rules']);

		self::assertSame([
			'field_ref'   => 'type',
			'rule'        => 'eq',
			'server_only' => false,
			'message'     => 'TYPE_MUST_BE_ADMIN',
			'value'       => 'admin',
		], $arr['rules'][0]);

		self::assertSame([
			'field_ref'   => 'email',
			'rule'        => 'is_not_null',
			'server_only' => false,
			'message'     => null,
			'value'       => null,
		], $arr['rules'][1]);

		self::assertSame([
			'field_ref'   => 'status',
			'rule'        => 'in',
			'server_only' => false,
			'message'     => null,
			'value'       => ['active', 'pending'],
		], $arr['rules'][2]);
	}

	public function testToArrayNestedOrGroup(): void
	{
		$rs = (new RuleSet())
			->eq('type', 'premium')
			->or(static function (RuleSet $sub): void {
				$sub->eq('status', 'active');
				$sub->eq('status', 'trial');
			});

		$arr = $rs->toArray();

		self::assertSame('and', $arr['condition']);
		self::assertCount(2, $arr['rules']);
		self::assertSame('or', $arr['rules'][1]['condition']);
		self::assertCount(2, $arr['rules'][1]['rules']);
	}

	public function testToArrayReturnsAsyncForServerOnly(): void
	{
		$rs = (new RuleSet())->eq('field', new DynamicValue(static fn () => 'x'));
		self::assertSame(['$async' => true], $rs->toArray());
	}

	public function testToArrayCrossFieldRuleHasTargetRef(): void
	{
		$form = new Form();
		$pw   = $form->field('password');
		$cpw  = $form->field('confirm_password');

		$rs  = (new RuleSet())->eq($pw, $cpw);
		$arr = $rs->toArray();

		self::assertSame('password', $arr['rules'][0]['field_ref']);
		self::assertSame('confirm_password', $arr['rules'][0]['target_ref']);
		self::assertArrayNotHasKey('value', $arr['rules'][0]);
	}

	public function testGetConditionDefaultIsAnd(): void
	{
		$rs = new RuleSet();
		self::assertSame(RuleSetCondition::AND, $rs->getCondition());
	}

	public function testGetConditionOr(): void
	{
		$rs = new RuleSet(RuleSetCondition::OR);
		self::assertSame(RuleSetCondition::OR, $rs->getCondition());
	}

	public function testGetChildrenContainsAddedRules(): void
	{
		$rs = (new RuleSet())->eq('x', 1)->neq('y', 2);
		self::assertCount(2, $rs->getChildren());

		foreach ($rs->getChildren() as $child) {
			self::assertInstanceOf(Rule::class, $child);
		}
	}

	private function fd(array $data): FormData
	{
		return new FormData($data);
	}
}
