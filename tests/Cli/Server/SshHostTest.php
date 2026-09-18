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

namespace OZONE\Tests\Cli\Server;

use InvalidArgumentException;
use OZONE\Core\Cli\Server\SshHost;
use PHPUnit\Framework\TestCase;

/**
 * How the SSH host calls the client. What it does on a real server is tested against sshd containers
 * (tests/Integration/Cli/SshHostTest.php, `make test-provision`).
 *
 * @internal
 *
 * @covers \OZONE\Core\Cli\Server\SshHost
 */
final class SshHostTest extends TestCase
{
	public function testATargetGivesTheDestinationAndThePort(): void
	{
		self::assertSame('deploy@example.com', SshHost::fromTarget('deploy@example.com')->describe());
		self::assertSame('deploy@example.com:2222', SshHost::fromTarget('deploy@example.com:2222')->describe());
		self::assertFalse(SshHost::fromTarget('example.com')->isLocal());
	}

	public function testTheCommandIsOneArgumentAfterTheDestination(): void
	{
		$args = SshHost::fromTarget('root@10.0.0.5:2222')->sshCommand('test -d /srv && echo "a b"');

		self::assertSame('ssh', $args[0]);
		self::assertSame(['-p', '2222'], \array_slice($args, 1, 2));
		self::assertSame(['root@10.0.0.5', '--', 'test -d /srv && echo "a b"'], \array_slice($args, -3));
	}

	public function testTheClientNeverPromptsAndTrustsNewHostKeysOnly(): void
	{
		$args = \implode(' ', SshHost::fromTarget('root@example.com')->sshCommand('true'));

		self::assertStringContainsString('-o BatchMode=yes', $args);
		self::assertStringContainsString('-o StrictHostKeyChecking=accept-new', $args);
		self::assertStringContainsString('-o ControlMaster=auto', $args);
		self::assertStringNotContainsString(' -i ', $args, 'the agent is used when no identity is given');
	}

	public function testAnIdentityAndOptionsArePassed(): void
	{
		$args = SshHost::fromTarget('root@example.com', '/keys/id_ed25519', ['UserKnownHostsFile=/dev/null'])
			->sshCommand('true');

		self::assertContains('/keys/id_ed25519', $args);
		self::assertContains('IdentitiesOnly=yes', $args);
		self::assertContains('UserKnownHostsFile=/dev/null', $args);
	}

	public function testTheClientCanBeReplacedFromTheEnvironment(): void
	{
		\putenv('OZ_SSH_COMMAND=ssh -F /tmp/oz-ssh-config  -o LogLevel=ERROR');

		try {
			$args = SshHost::fromTarget('root@example.com')->sshCommand('true');

			self::assertSame(['ssh', '-F', '/tmp/oz-ssh-config', '-o', 'LogLevel=ERROR', '-p', '22'], \array_slice($args, 0, 7));
		} finally {
			\putenv('OZ_SSH_COMMAND');
		}
	}

	public function testADestinationCannotBeAnOption(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new SshHost('-oProxyCommand=evil');
	}
}
