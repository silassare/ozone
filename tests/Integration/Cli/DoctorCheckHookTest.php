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

namespace OZONE\Tests\Integration\Cli;

use Override;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * `oz doctor check` in a real project whose boot adds checks of its own, as a plugin does: they
 * are reported with OZone's, and one that throws is a failed check naming it, the others still
 * reported.
 *
 * @internal
 */
#[CoversNothing]
final class DoctorCheckHookTest extends TestCase
{
	private static ?OZTestProject $proj = null;

	#[Override]
	public static function tearDownAfterClass(): void
	{
		self::$proj?->destroy();
		self::$proj = null;

		parent::tearDownAfterClass();
	}

	public function testChecksAddedByABootAreReportedAndOneThatThrowsFailsByName(): void
	{
		$proj = self::$proj = OZTestProject::create('doctor-hook', fresh: true);
		$ns   = $proj->getNamespace();

		$proj->writeEnv(['OZ_DB_RDBMS' => 'sqlite', 'OZ_DB_HOST' => $proj->getPath() . '/doctor_hook.sqlite']);
		$proj->writeFile('app/DoctorWitness.php', \sprintf(<<<'PHP'
			<?php

			declare(strict_types=1);

			namespace %s;

			use OZONE\Core\Hooks\Events\DoctorCheckHook;
			use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
			use RuntimeException;

			final class DoctorWitness implements BootHookReceiverInterface
			{
				public static function boot(): void
				{
					DoctorCheckHook::listen(self::reports(...));
					DoctorCheckHook::listen(self::broken(...));
				}

				public static function reports(DoctorCheckHook $hook): void
				{
					$hook->add('Witness', 'warn', 'something to look at', 'Look at it.');
				}

				public static function broken(DoctorCheckHook $hook): void
				{
					throw new RuntimeException('the witness tripped');
				}
			}

			PHP, $ns));
		$proj->setSetting('oz.boot', "{$ns}\\DoctorWitness", true);

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$proc = $proj->oz('doctor', 'check', '--json');
		$proc->run();

		$out = \json_decode($proc->getOutput(), true);

		self::assertIsArray($out, $proc->getOutput() . $proc->getErrorOutput());

		$checks = \array_column($out['data']['checks'] ?? [], null, 'name');

		self::assertSame(
			['name' => 'Witness', 'status' => 'warn', 'detail' => 'something to look at', 'fix' => 'Look at it.'],
			$checks['Witness'] ?? null
		);
		self::assertSame('fail', $checks["{$ns}\\DoctorWitness::broken"]['status'] ?? null);
		self::assertSame('the witness tripped', $checks["{$ns}\\DoctorWitness::broken"]['detail'] ?? null);
		// OZone's own checks are still there, and the failure fails the run.
		self::assertSame('ok', $checks['Database']['status'] ?? null);
		self::assertSame(1, $proc->getExitCode());
	}
}
