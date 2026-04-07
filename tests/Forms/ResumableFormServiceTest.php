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

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Enums\FormResumePhase;
use OZONE\Core\Forms\FormResumeProgress;
use OZONE\Core\Forms\Services\ResumableFormService;
use PHPUnit\Framework\TestCase;

/**
 * Class ResumableFormServiceTest.
 *
 * Unit tests for {@see FormResumeProgress} and {@see ResumableFormService} constants.
 *
 * Full end-to-end handler tests (initSession, nextStep, etc.) require HTTP scaffolding
 * and live in the integration suite: tests/Integration/Forms/.
 *
 * @internal
 *
 * @coversNothing
 */
final class ResumableFormServiceTest extends TestCase
{
	// -----------------------------------------------------------------------
	// FormResumeProgress — defaults
	// -----------------------------------------------------------------------

	public function testDefaultPhaseIsSteps(): void
	{
		$progress = new FormResumeProgress();

		self::assertSame(FormResumePhase::STEPS, $progress->getPhase());
	}

	public function testDefaultStepIndexIsZero(): void
	{
		$progress = new FormResumeProgress();

		self::assertSame(0, $progress->getStepIndex());
	}

	// -----------------------------------------------------------------------
	// FormResumeProgress — setters / getters
	// -----------------------------------------------------------------------

	public function testSetGetPhase(): void
	{
		$progress = new FormResumeProgress();
		$progress->setPhase(FormResumePhase::DONE);

		self::assertSame(FormResumePhase::DONE, $progress->getPhase());
	}

	public function testSetPhaseToInit(): void
	{
		$progress = new FormResumeProgress();
		$progress->setPhase(FormResumePhase::INIT);

		self::assertSame(FormResumePhase::INIT, $progress->getPhase());
	}

	public function testSetGetStepIndex(): void
	{
		$progress = new FormResumeProgress();
		$progress->setStepIndex(7);

		self::assertSame(7, $progress->getStepIndex());
	}

	// -----------------------------------------------------------------------
	// FormResumeProgress — provider-owned state
	// -----------------------------------------------------------------------

	public function testProviderStatePersistsRoundTrip(): void
	{
		$progress = new FormResumeProgress();
		$progress->set('my_key', 'my_value');

		self::assertSame('my_value', $progress->get('my_key'));
	}

	public function testProviderStateDefaultReturnsNullForMissingKey(): void
	{
		$progress = new FormResumeProgress();

		self::assertNull($progress->get('nonexistent'));
	}

	public function testProviderStateDefaultValueReturned(): void
	{
		$progress = new FormResumeProgress();

		self::assertSame('fallback', $progress->get('missing', 'fallback'));
	}

	public function testSetReservedKeyThrowsRuntimeException(): void
	{
		$progress = new FormResumeProgress();

		$this->expectException(RuntimeException::class);

		$progress->set('_reserved', 'value');
	}

	public function testSetWithDoubleUnderscorePrefixThrowsRuntimeException(): void
	{
		$progress = new FormResumeProgress();

		$this->expectException(RuntimeException::class);

		$progress->set('__meta', 'value');
	}

	// -----------------------------------------------------------------------
	// FormResumeProgress — serialisation / restore
	// -----------------------------------------------------------------------

	public function testToArrayIsEmptyOnDefaultConstruction(): void
	{
		$progress = new FormResumeProgress();

		self::assertSame([], $progress->toArray());
	}

	public function testToArrayIncludesAllSetData(): void
	{
		$progress = new FormResumeProgress();
		$progress->setPhase(FormResumePhase::STEPS);
		$progress->setStepIndex(2);
		$progress->set('provider_flag', true);

		$arr = $progress->toArray();

		self::assertSame(FormResumePhase::STEPS->value, $arr['_phase']);
		self::assertSame(2, $arr['_step_index']);
		self::assertTrue($arr['provider_flag']);
	}

	public function testFromStateArrayRestoresAllValues(): void
	{
		$state = [
			'_phase'      => 'done',
			'_step_index' => 5,
			'my_data'     => 'hello',
		];

		$progress = new FormResumeProgress($state);

		self::assertSame(FormResumePhase::DONE, $progress->getPhase());
		self::assertSame(5, $progress->getStepIndex());
		self::assertSame('hello', $progress->get('my_data'));
	}

	public function testFromStateArrayWithoutPhaseDefaultsToSteps(): void
	{
		$progress = new FormResumeProgress(['_step_index' => 3]);

		self::assertSame(FormResumePhase::STEPS, $progress->getPhase());
		self::assertSame(3, $progress->getStepIndex());
	}

	// -----------------------------------------------------------------------
	// ResumableFormService — constants
	// -----------------------------------------------------------------------

	public function testCacheNamespaceConstant(): void
	{
		self::assertSame('oz.form.sessions', ResumableFormService::CACHE_NAMESPACE);
	}

	public function testRouteInitConstant(): void
	{
		self::assertSame('oz:form:init', ResumableFormService::ROUTE_INIT);
	}

	public function testRouteStateConstant(): void
	{
		self::assertSame('oz:form:state', ResumableFormService::ROUTE_STATE);
	}

	public function testRouteNextConstant(): void
	{
		self::assertSame('oz:form:next', ResumableFormService::ROUTE_NEXT);
	}

	public function testRouteBackConstant(): void
	{
		self::assertSame('oz:form:back', ResumableFormService::ROUTE_BACK);
	}

	public function testRouteCancelConstant(): void
	{
		self::assertSame('oz:form:cancel', ResumableFormService::ROUTE_CANCEL);
	}

	public function testRouteEvaluateConstant(): void
	{
		self::assertSame('oz:form:evaluate', ResumableFormService::ROUTE_EVALUATE);
	}

	public function testAllRouteConstantsAreDistinct(): void
	{
		$names = [
			ResumableFormService::ROUTE_INIT,
			ResumableFormService::ROUTE_STATE,
			ResumableFormService::ROUTE_NEXT,
			ResumableFormService::ROUTE_BACK,
			ResumableFormService::ROUTE_CANCEL,
			ResumableFormService::ROUTE_EVALUATE,
		];

		self::assertSame(\count($names), \count(\array_unique($names)));
	}
}
