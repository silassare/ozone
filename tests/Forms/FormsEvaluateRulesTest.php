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
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormRegistry;
use OZONE\Core\Forms\RuleSet;
use OZONE\Core\Forms\Services\FormsService;
use PHPUnit\Framework\TestCase;

/**
 * Class FormsEvaluateRulesTest.
 *
 * Tests the server-only expect-rule evaluation logic used by
 * {@see FormsService} evaluate endpoint.
 *
 * @internal
 *
 * @coversNothing
 */
final class FormsEvaluateRulesTest extends TestCase
{
	protected function setUp(): void
	{
		FormRegistry::clear();
	}

	// -----------------------------------------------------------
	// Empty / no server-only rules
	// -----------------------------------------------------------

	public function testFormNotFoundReturnsNull(): void
	{
		self::assertNull(FormRegistry::get('non.existent.form'));
	}

	public function testEmptyFormYieldsEmptyResults(): void
	{
		$form = new Form();
		$form->key('eval.empty');

		$results = $this->evaluateServerOnlyExpectRules($form, new FormData());

		self::assertSame([], $results);
	}

	public function testNonServerOnlyExpectRulesYieldEmptyResults(): void
	{
		$form = new Form();
		$form->key('eval.non-server-only');
		// Plain scalar comparison — not server-only
		$form->expect()->eq('plan', 'premium');

		$results = $this->evaluateServerOnlyExpectRules($form, new FormData(['plan' => 'premium']));

		self::assertSame([], $results);
	}

	// -----------------------------------------------------------
	// Single server-only expect rule
	// -----------------------------------------------------------

	public function testSingleServerOnlyRulePasses(): void
	{
		$form = new Form();
		$form->key('eval.single.pass');
		// DynamicValue makes the rule server-only; returns the field value to satisfy eq
		$form->expect()->eq('code', new DynamicValue(static fn () => 'VALID'));

		$results = $this->evaluateServerOnlyExpectRules($form, new FormData(['code' => 'VALID']));

		self::assertCount(1, $results);
		self::assertTrue($results[0]['passed']);
		self::assertNull($results[0]['message']);
	}

	public function testSingleServerOnlyRuleFailsAndReturnsMessage(): void
	{
		$form = new Form();
		$form->key('eval.single.fail');
		$form->expect()->eq('code', new DynamicValue(static fn () => 'VALID'), 'INVALID_CODE');

		$results = $this->evaluateServerOnlyExpectRules($form, new FormData(['code' => 'WRONG']));

		self::assertCount(1, $results);
		self::assertFalse($results[0]['passed']);
		self::assertSame('INVALID_CODE', $results[0]['message']);
	}

	public function testFailingRuleWithNoMessageReturnsNullMessage(): void
	{
		$form = new Form();
		$form->key('eval.no-message');
		$form->expect()->eq('code', new DynamicValue(static fn () => 'VALID')); // no custom message

		$results = $this->evaluateServerOnlyExpectRules($form, new FormData(['code' => 'WRONG']));

		self::assertCount(1, $results);
		self::assertFalse($results[0]['passed']);
		self::assertNull($results[0]['message']);
	}

	// -----------------------------------------------------------
	// Multiple server-only expect rules — all evaluated independently
	// -----------------------------------------------------------

	public function testMultipleServerOnlyRulesAllEvaluated(): void
	{
		$form = new Form();
		$form->key('eval.multi');
		$form->expect()->eq('a', new DynamicValue(static fn () => 'ok'));
		$form->expect()->eq('b', new DynamicValue(static fn () => 'ok'), 'B_FAILED');

		$results = $this->evaluateServerOnlyExpectRules($form, new FormData(['a' => 'ok', 'b' => 'bad']));

		self::assertCount(2, $results);
		self::assertTrue($results[0]['passed']);
		self::assertNull($results[0]['message']);
		self::assertFalse($results[1]['passed']);
		self::assertSame('B_FAILED', $results[1]['message']);
	}

	public function testMultipleServerOnlyRulesAllPass(): void
	{
		$form = new Form();
		$form->key('eval.multi.all-pass');
		$form->expect()->eq('a', new DynamicValue(static fn () => 'ok'));
		$form->expect()->eq('b', new DynamicValue(static fn () => 'yes'));

		$results = $this->evaluateServerOnlyExpectRules($form, new FormData(['a' => 'ok', 'b' => 'yes']));

		self::assertCount(2, $results);
		self::assertTrue($results[0]['passed']);
		self::assertTrue($results[1]['passed']);
	}

	// -----------------------------------------------------------
	// Mixed: server-only and non-server-only rules
	// -----------------------------------------------------------

	public function testOnlyServerOnlyRulesAreIncludedInResults(): void
	{
		$form = new Form();
		$form->key('eval.mixed');
		$form->expect()->eq('plan', 'premium'); // NOT server-only
		$form->expect()->eq('username', new DynamicValue(
			static fn (FormData $fd) => \in_array($fd->get('username'), ['taken'], true) ? null : $fd->get('username')
		), 'USERNAME_TAKEN'); // server-only

		$results = $this->evaluateServerOnlyExpectRules($form, new FormData(['plan' => 'premium', 'username' => 'taken']));

		// Only the server-only rule is in results; the plain scalar rule is omitted
		self::assertCount(1, $results);
		self::assertFalse($results[0]['passed']);
		self::assertSame('USERNAME_TAKEN', $results[0]['message']);
	}

	// -----------------------------------------------------------
	// Dynamic value uses FormData context
	// -----------------------------------------------------------

	public function testDynamicValueReceivesUnsafeFormData(): void
	{
		$captured = null;
		$form     = new Form();
		$form->key('eval.capture');
		$form->expect()->eq('token', new DynamicValue(static function (FormData $fd) use (&$captured) {
			$captured = $fd->get('extra');

			return 'TOKEN';
		}));

		$this->evaluateServerOnlyExpectRules($form, new FormData(['token' => 'TOKEN', 'extra' => 'ctx-value']));

		self::assertSame('ctx-value', $captured);
	}

	// -----------------------------------------------------------
	// Result order matches rule definition order
	// -----------------------------------------------------------

	public function testResultOrderMatchesDefinitionOrder(): void
	{
		$order = [];
		$form  = new Form();
		$form->key('eval.order');
		$form->expect()->eq('first', new DynamicValue(static function () use (&$order) {
			$order[] = 'first';

			return 'ok';
		}));
		$form->expect()->eq('second', new DynamicValue(static function () use (&$order) {
			$order[] = 'second';

			return 'ok';
		}));
		$form->expect()->eq('third', new DynamicValue(static function () use (&$order) {
			$order[] = 'third';

			return 'ok';
		}));

		$this->evaluateServerOnlyExpectRules($form, new FormData(['first' => 'ok', 'second' => 'ok', 'third' => 'ok']));

		self::assertSame(['first', 'second', 'third'], $order);
	}

	// -----------------------------------------------------------
	// Helper: mirrors the logic in FormsService::actionEvaluateRules
	// -----------------------------------------------------------

	/**
	 * Mirrors the core evaluation loop in FormsService::actionEvaluateRules.
	 *
	 * Collects results for every server-only expect RuleSet of `$form`
	 * and evaluates each against `$unsafe_fd`.
	 *
	 * @param Form     $form
	 * @param FormData $unsafe_fd
	 *
	 * @return list<array{passed: bool, message: mixed}>
	 */
	private function evaluateServerOnlyExpectRules(Form $form, FormData $unsafe_fd): array
	{
		$results = [];

		foreach ($form->t_pre_validation_rules as $rule_set) {
			/** @var RuleSet $rule_set */
			if ($rule_set->isServerOnly()) {
				$passed    = $rule_set->check($unsafe_fd);
				$results[] = [
					'passed'  => $passed,
					'message' => $passed ? null : $rule_set->getViolationMessage(),
				];
			}
		}

		return $results;
	}
}
