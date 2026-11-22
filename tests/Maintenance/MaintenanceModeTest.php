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

namespace OZONE\Tests\Maintenance;

use Override;
use OZONE\Core\Maintenance\Enums\MaintenanceCoverage;
use OZONE\Core\Maintenance\Maintenance;
use OZONE\Core\Maintenance\MaintenanceMode;
use OZONE\Core\Scopes\StateLayout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class MaintenanceModeTest.
 *
 * The record of a maintenance and the file that holds it: written, read and removed, what it holds,
 * and what a file that cannot be read stands for.
 *
 * @internal
 */
#[CoversClass(Maintenance::class)]
#[CoversClass(MaintenanceMode::class)]
final class MaintenanceModeTest extends TestCase
{
	#[Override]
	protected function tearDown(): void
	{
		Maintenance::disable();
	}

	public function testOnWritesTheRecordInTheApplicationsStateDirectoryAndOffRemovesIt(): void
	{
		self::assertNull(Maintenance::current());

		[$mode] = MaintenanceMode::start(MaintenanceCoverage::API, [], ['10.0.0.0/8'], 120, 'Back soon', true);

		Maintenance::enable($mode);

		self::assertSame(
			StateLayout::path(app(), StateLayout::STATE) . \DIRECTORY_SEPARATOR . 'maintenance.json',
			Maintenance::file()
		);
		self::assertFileExists(Maintenance::file());
		self::assertEquals($mode, Maintenance::current());
		self::assertTrue(Maintenance::jobsPaused());

		self::assertTrue(Maintenance::disable());
		self::assertFileDoesNotExist(Maintenance::file());
		self::assertNull(Maintenance::current());
		self::assertFalse(Maintenance::jobsPaused());
		self::assertFalse(Maintenance::disable());
	}

	public function testTheFileHoldsTheHashOfTheSecretAndNeverTheSecret(): void
	{
		[$mode, $secret] = MaintenanceMode::start();

		Maintenance::enable($mode);

		$json = (string) \file_get_contents(Maintenance::file());

		self::assertStringNotContainsString($secret, $json);
		self::assertSame(\hash('sha256', $secret), \json_decode($json, true)['secret'] ?? null);
		self::assertTrue(Maintenance::current()?->acceptsSecret($secret));
		self::assertFalse(Maintenance::current()?->acceptsSecret(\hash('sha256', $secret)));
		self::assertFalse(Maintenance::current()?->acceptsSecret(''));
	}

	public function testEveryOnDrawsANewRunAndANewSecret(): void
	{
		[$first, $first_secret]   = MaintenanceMode::start();
		[$second, $second_secret] = MaintenanceMode::start();

		self::assertNotSame($first->run, $second->run);
		self::assertNotSame($first_secret, $second_secret);
		self::assertFalse($second->acceptsSecret($first_secret));
		self::assertNotSame($first->bypassToken(), $second->bypassToken());
	}

	public function testAWorkerSeesOnAndOffOnItsNextRead(): void
	{
		// The same process reads the file again and again, as a worker serving one request after
		// another does: PHP's stat cache must not answer for the file.
		self::assertNull(Maintenance::current());

		[$mode] = MaintenanceMode::start();
		Maintenance::enable($mode);

		self::assertNotNull(Maintenance::current());

		\unlink(Maintenance::file());

		self::assertNull(Maintenance::current());

		[$other] = MaintenanceMode::start(MaintenanceCoverage::WEB);
		Maintenance::enable($other);

		self::assertSame(MaintenanceCoverage::WEB, Maintenance::current()?->coverage);
	}

	public function testTheRecordReadsBackAsItWasWritten(): void
	{
		[$mode] = MaintenanceMode::start(MaintenanceCoverage::SCOPES, ['api', 'www'], ['192.0.2.7'], 60, 'x', false);

		self::assertEquals($mode, MaintenanceMode::fromArray($mode->toArray()));
		self::assertSame([
			'format'      => 1,
			'run'         => $mode->run,
			'since'       => $mode->since,
			'covers'      => 'scopes',
			'scopes'      => ['api', 'www'],
			'allow'       => ['192.0.2.7'],
			'secret'      => $mode->secretHash,
			'retry_after' => 60,
			'message'     => 'x',
			'pause_jobs'  => false,
		], $mode->toArray());
	}

	public function testEveryScopeIsHeldByDefault(): void
	{
		[$mode] = MaintenanceMode::start();

		self::assertTrue($mode->covers('api', true));
		self::assertTrue($mode->covers('www', false));
		self::assertTrue($mode->covers('root', true));
	}

	public function testOnlyTheApiScopesOrOnlyTheWebOnes(): void
	{
		[$api] = MaintenanceMode::start(MaintenanceCoverage::API);
		[$web] = MaintenanceMode::start(MaintenanceCoverage::WEB);

		self::assertTrue($api->covers('api', true));
		self::assertFalse($api->covers('www', false));
		self::assertFalse($web->covers('api', true));
		self::assertTrue($web->covers('www', false));
	}

	public function testOnlyTheScopesNamed(): void
	{
		[$mode] = MaintenanceMode::start(MaintenanceCoverage::SCOPES, ['www']);

		self::assertTrue($mode->covers('www', false));
		self::assertFalse($mode->covers('api', true));
		// a name is a name: a web scope not named is served
		self::assertFalse($mode->covers('admin', false));
	}

	public function testAFileThatIsNotJsonHoldsEveryScopeLetsNobodyThroughAndPausesTheJobs(): void
	{
		self::writeRecord('{"format": 1, "run": ');

		$mode = Maintenance::current();

		self::assertNotNull($mode);
		self::assertFalse($mode->isReadable());
		self::assertStringStartsWith('not JSON', (string) $mode->fault);
		self::assertTrue($mode->covers('api', true));
		self::assertTrue($mode->covers('www', false));
		self::assertFalse($mode->allowsIp('127.0.0.1'));
		self::assertNull($mode->bypassToken());
		self::assertFalse($mode->acceptsBypassToken(''));
		self::assertTrue($mode->pauseJobs);
		self::assertTrue(Maintenance::jobsPaused());
		// it started when the file was written, which is all that is left to say it
		self::assertSame(\filemtime(Maintenance::file()), $mode->since);
	}

	/**
	 * @param array<string, mixed> $change what a hand edit, or another OZone, made of a record
	 */
	#[DataProvider('provideARecordThatCannotBeTrustedIsUnreadableCases')]
	public function testARecordThatCannotBeTrustedIsUnreadable(array $change, string $fault): void
	{
		[$mode] = MaintenanceMode::start(MaintenanceCoverage::WEB, [], ['10.0.0.0/8'], 30, null, false);

		$data = \array_merge($mode->toArray(), $change);

		foreach ($change as $key => $value) {
			if (null === $value) {
				unset($data[$key]);
			}
		}

		self::writeRecord((string) \json_encode($data));

		$read = Maintenance::current();

		self::assertNotNull($read);
		self::assertFalse($read->isReadable());
		self::assertStringContainsString($fault, (string) $read->fault);
		// read as everything held and the jobs paused, never as the WEB-only, jobs-running record
		self::assertTrue($read->covers('api', true));
		self::assertTrue($read->pauseJobs);
		self::assertFalse($read->allowsIp('10.1.2.3'));
	}

	/**
	 * @return iterable<string, array{0: array<string, mixed>, 1: string}>
	 */
	public static function provideARecordThatCannotBeTrustedIsUnreadableCases(): iterable
	{
		yield 'another format' => [['format' => 2], 'format 2'];

		yield 'no format' => [['format' => null], 'format null'];

		yield 'no run' => [['run' => null], '"run"'];

		yield 'a coverage it does not know' => [['covers' => 'mobile'], '"covers"'];

		yield 'chosen scopes, none named' => [['covers' => 'scopes', 'scopes' => []], '"scopes" is empty'];

		yield 'an address that is none' => [['allow' => ['10.0.0.0/40']], '"allow"'];

		yield 'no word on the jobs' => [['pause_jobs' => null], '"pause_jobs"'];

		yield 'a secret that is no hash' => [['secret' => 'plain'], '"secret"'];

		yield 'no time to wait' => [['retry_after' => 0], '"retry_after"'];

		yield 'scopes that are no list' => [['scopes' => 'api'], '"scopes" is not a list'];
	}

	public function testAJsonValueThatIsNoObjectIsUnreadable(): void
	{
		self::writeRecord('"on"');

		self::assertSame('not a JSON object', Maintenance::current()?->fault);
	}

	public function testAnAddressIsLetThroughByItselfOrByARange(): void
	{
		[$mode] = MaintenanceMode::start(allow: ['203.0.113.7', '10.0.0.0/8', '2001:db8::/32']);

		self::assertTrue($mode->allowsIp('203.0.113.7'));
		self::assertFalse($mode->allowsIp('203.0.113.8'));
		self::assertTrue($mode->allowsIp('10.200.3.4'));
		self::assertFalse($mode->allowsIp('11.0.0.1'));
		self::assertTrue($mode->allowsIp('2001:db8::1'));
		self::assertFalse($mode->allowsIp('2001:db9::1'));
		self::assertFalse($mode->allowsIp(null));
		self::assertFalse($mode->allowsIp('not an address'));
	}

	private static function writeRecord(string $content): void
	{
		$file = Maintenance::file();

		\file_put_contents($file, $content);
	}
}
