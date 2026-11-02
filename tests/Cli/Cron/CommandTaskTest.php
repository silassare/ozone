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

namespace OZONE\Tests\Cli\Cron;

use OZONE\Core\Cli\Cron\Tasks\CommandTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A scheduled command, and what its result says about how the command ended: the scheduler's record
 * of a run is the only place anyone reads it afterwards.
 *
 * @internal
 */
#[CoversClass(CommandTask::class)]
final class CommandTaskTest extends TestCase
{
	public function testWhatACommandPrintsAndAnswersIsRecorded(): void
	{
		$task = new CommandTask('printf done; printf oops >&2', 'probe', 'A probe.');

		$task->run();

		$result = $task->getResult();

		self::assertTrue($result->isDone());
		self::assertSame(0, $result->getDataKey('exit_code'));
		self::assertSame('done', $result->getDataKey('stdout'));
		self::assertSame('oops', $result->getDataKey('stderr'));
	}

	public function testACommandThatFailsIsReportedWithItsExitCode(): void
	{
		$task = new CommandTask('exit 3', 'probe', 'A probe.');

		$task->run();

		self::assertTrue($task->getResult()->isError());
		self::assertSame('Command task "probe" failed with exit code 3.', $task->getResult()->toArray()['msg']);
	}

	public function testACommandThatWasKilledSaysWhichSignalRatherThanAnExitCode(): void
	{
		// A scheduled command is a process like any other, and a box that runs out of memory takes
		// whatever is running on it: the code 137 is not a code the command chose, and reporting it
		// as one says the command failed when nothing of it ever ran to a verdict.
		$task = new CommandTask('printf begun; kill -9 $$', 'probe', 'A probe.');

		$task->run();

		$result = $task->getResult();

		self::assertTrue($result->isError());
		self::assertStringContainsString(
			'Command task "probe" killed by signal 9 (SIGKILL',
			$result->toArray()['msg']
		);

		// And what it printed until then is kept with it, as for any other outcome.
		self::assertSame(137, $result->getDataKey('exit_code'));
		self::assertSame('begun', $result->getDataKey('stdout'));
	}
}
