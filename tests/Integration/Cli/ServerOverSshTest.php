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

use OZONE\Core\Cli\Server\SshHost;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * `oz server` and `oz deploy` acting on a server over SSH, for real: throwaway containers that run
 * nothing but sshd (no PHP, no OZone), reached from this machine with a throwaway key. What the host
 * answers, the package manager detected, the provision applied and recorded, and the deploy root read
 * and rolled back, are all the container's.
 *
 * Runs on the host, as ServerProvisionTest does: it drives Docker and the system `ssh` client
 * (`make test-provision`). The client's known hosts are kept apart through `OZ_SSH_COMMAND`.
 *
 * @internal
 *
 * @coversNothing
 *
 * @group provision
 */
final class ServerOverSshTest extends TestCase
{
	private const PLATFORMS = [
		'debian' => [
			'image'     => 'debian:12-slim',
			'manager'   => 'apt',
			'bootstrap' => 'apt-get update -qq'
				. ' && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends openssh-server'
				. ' && mkdir -p /run/sshd',
		],
		'alpine' => [
			'image'     => 'alpine:3',
			'manager'   => 'apk',
			'bootstrap' => 'apk add --no-cache openssh && ssh-keygen -A',
		],
	];

	private static string $dir = '';

	/** @var array<string, int> the SSH port of each started platform */
	private static array $ports = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		foreach (['docker', 'ssh', 'ssh-keygen'] as $tool) {
			if (!self::available($tool)) {
				if (\getenv('OZ_TEST_PROVISION_REQUIRED')) {
					self::fail($tool . ' is unavailable (OZ_TEST_PROVISION_REQUIRED is set).');
				}

				self::markTestSkipped($tool . ' is unavailable.');
			}
		}

		self::$dir = \sys_get_temp_dir() . '/oz_ssh_' . \bin2hex(\random_bytes(4));

		\mkdir(self::$dir, 0o700, true);

		(new Process(['ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-f', self::$dir . '/id']))->mustRun();

		\putenv('OZ_SSH_COMMAND=' . self::sshCommand());
	}

	public static function tearDownAfterClass(): void
	{
		foreach (\array_keys(self::$ports) as $platform) {
			self::docker(['rm', '-f', self::container((string) $platform)]);
		}

		self::$ports = [];

		\putenv('OZ_SSH_COMMAND');
		(new Process(['rm', '-rf', self::$dir]))->run();

		parent::tearDownAfterClass();
	}

	/**
	 * @dataProvider providePlatforms
	 */
	public function testTheHostAnswersOverSsh(string $platform): void
	{
		$host = $this->host($platform);

		self::assertFalse($host->isLocal());
		self::assertTrue($host->isRoot());
		self::assertTrue($host->hasBinary('sh'));
		self::assertFalse($host->hasBinary('oz-no-such-binary'));

		$output = '';

		self::assertSame(0, $host->run('mkdir -p /srv/probe/releases/r1 /srv/probe/releases/r2 /srv/probe/empty'
			. ' && ln -s /srv/probe/releases/r2 /srv/probe/current && echo made', $output));
		self::assertStringContainsString('made', $output);
		self::assertSame(3, $host->run('exit 3'));

		self::assertTrue($host->exists('/srv/probe/current'));
		self::assertTrue($host->isDir('/srv/probe/current'));
		self::assertTrue($host->isLink('/srv/probe/current'));
		self::assertFalse($host->isLink('/srv/probe/releases'));
		self::assertFalse($host->exists('/srv/probe/nope'));
		self::assertSame('/srv/probe/releases/r2', $host->readLink('/srv/probe/current'));
		self::assertNull($host->readLink('/srv/probe/releases'));
		self::assertSame(['r1', 'r2'], $host->listDirs('/srv/probe/releases'));
		self::assertSame([], $host->listDirs('/srv/probe/empty'));
		self::assertSame([], $host->listDirs('/srv/probe/nope'));

		// Bytes a shell would mangle, through the connection's standard input.
		$content = "line one\n\0binary \xff 'quoted' \$HOME\n";

		self::assertTrue($host->writeFile('/srv/probe/deep/dir/file.bin', $content));
		self::assertSame($content, $host->readFile('/srv/probe/deep/dir/file.bin'));
		self::assertNull($host->readFile('/srv/probe/missing'));

		$local = self::$dir . '/upload.bin';

		\file_put_contents($local, \random_bytes(256 * 1024));

		self::assertTrue($host->upload($local, '/srv/probe/uploads/upload.bin'));
		self::assertSame(\md5_file($local), \md5((string) $host->readFile('/srv/probe/uploads/upload.bin')));
	}

	/**
	 * @dataProvider providePlatforms
	 */
	public function testThePackageManagerOfTheServerIsDetected(string $platform): void
	{
		$result = $this->oz($platform, ['server', 'provision', '--dry-run']);

		self::assertSame(0, $result['code'], $result['output']);
		self::assertStringContainsString('Host: ' . $this->host($platform)->describe(), $result['output']);
		// Alpine answers apk although this machine may well have apt: the server was asked.
		self::assertStringContainsString('Package manager: ' . self::PLATFORMS[$platform]['manager'], $result['output']);
		self::assertNotSame(0, self::exec($platform, 'test -f /etc/ozone/provision.json')['code'], 'a dry run writes nothing');
	}

	/**
	 * @dataProvider providePlatforms
	 */
	public function testTheServerIsProvisionedOverSsh(string $platform): void
	{
		$result = $this->oz($platform, ['server', 'provision', '--yes', '--web-server=nginx', '--db=none', '--ssh-port=22']);

		self::assertSame(0, $result['code'], $result['output']);

		// On the server, not here: the packages, the firewall and the manifest.
		self::assertSame(0, self::exec($platform, 'command -v nginx')['code'], 'nginx was not installed');
		self::assertStringContainsString('22/tcp', self::exec($platform, 'ufw status')['output']);

		$manifest = \json_decode(self::exec($platform, 'cat /etc/ozone/provision.json')['output'], true);

		self::assertIsArray($manifest, 'the manifest is written on the server');
		self::assertArrayHasKey('web:nginx', $manifest['steps']);

		// The firewall kept SSH open: the server is still reachable, and reports what was done.
		$status = $this->oz($platform, ['server', 'status', '--json']);
		$json   = \json_decode($status['output'], true);

		self::assertIsArray($json, $status['output']);
		self::assertTrue($json['provisioned']);
		self::assertContains('web:nginx', \array_column($json['steps'], 'step'));

		$again = $this->oz($platform, ['server', 'provision', '--yes', '--web-server=nginx', '--db=none', '--ssh-port=22']);

		self::assertSame(0, $again['code'], $again['output']);
		self::assertStringContainsString('0 step(s) applied', $again['output']);
	}

	/**
	 * @dataProvider providePlatforms
	 */
	public function testADeployRootIsReadAndRolledBackOverSsh(string $platform): void
	{
		$host = $this->host($platform);

		$host->run('rm -rf /srv/app && mkdir -p /srv/app/releases/20260101000000 /srv/app/releases/20260102000000'
			. ' && touch /srv/app/.env && ln -s /srv/app/releases/20260102000000 /srv/app/current');

		$releases = \json_decode($this->oz($platform, ['deploy', 'releases', '--root=/srv/app', '--json'])['output'], true);

		self::assertIsArray($releases);
		self::assertTrue($releases['deployed']);
		self::assertSame('20260102000000', $releases['current']);
		self::assertSame(['20260101000000', '20260102000000'], $releases['releases']);

		$rollback = $this->oz($platform, ['deploy', 'rollback', '--root=/srv/app']);

		self::assertSame(0, $rollback['code'], $rollback['output']);
		self::assertSame('/srv/app/releases/20260101000000', $host->readLink('/srv/app/current'));
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function providePlatforms(): iterable
	{
		foreach (\array_keys(self::PLATFORMS) as $platform) {
			yield (string) $platform => [(string) $platform];
		}
	}

	/**
	 * The SSH host of a platform, its container started on first use.
	 */
	private function host(string $platform): SshHost
	{
		if (!isset(self::$ports[$platform])) {
			$this->start($platform);
		}

		return SshHost::fromTarget('root@127.0.0.1:' . self::$ports[$platform], self::$dir . '/id');
	}

	private function start(string $platform): void
	{
		$config = self::PLATFORMS[$platform];
		$name   = self::container($platform);

		self::docker(['rm', '-f', $name]);

		// NET_ADMIN so ufw can really load its rules. Port 22 published on a free local port.
		$run = self::docker(['run', '-d', '--name', $name, '--cap-add=NET_ADMIN', '-p', '127.0.0.1::22', $config['image'], 'sleep', '3600']);

		if (0 !== $run['code']) {
			self::fail('Unable to start the ' . $platform . ' container: ' . $run['output']);
		}

		$key = \trim((string) \file_get_contents(self::$dir . '/id.pub'));

		// systemd does not run in a container: systemctl is a stub recording its calls, as in
		// ServerProvisionTest.
		$prepare = self::exec($platform, $config['bootstrap']
			. ' && mkdir -p /root/.ssh && chmod 700 /root/.ssh'
			. ' && echo ' . \escapeshellarg($key) . ' > /root/.ssh/authorized_keys && chmod 600 /root/.ssh/authorized_keys'
			. ' && printf \'#!/bin/sh\necho "systemctl $*" >> /var/log/oz-systemctl.log\n\' > /usr/local/bin/systemctl'
			. ' && chmod +x /usr/local/bin/systemctl');

		if (0 !== $prepare['code']) {
			self::fail('Unable to prepare the ' . $platform . ' container: ' . $prepare['output']);
		}

		self::docker(['exec', '-d', $name, '/usr/sbin/sshd', '-D', '-e']);

		$port = self::docker(['port', $name, '22/tcp']);

		if (0 !== $port['code'] || !\preg_match('~:(\d+)\s*$~m', \trim(\explode("\n", $port['output'])[0]), $m)) {
			self::fail('No published SSH port for ' . $platform . ': ' . $port['output']);
		}

		self::$ports[$platform] = (int) $m[1];

		$host     = $this->host($platform);
		$deadline = \microtime(true) + 60;

		while (0 !== $host->run('true')) {
			if (\microtime(true) > $deadline) {
				self::fail('sshd of ' . $platform . ' did not accept the key within 60 seconds.');
			}

			\usleep(500_000);
		}
	}

	/**
	 * Runs this machine's `oz` against a platform's server, from a directory that is not a project.
	 *
	 * @param list<string> $args
	 *
	 * @return array{code: int, output: string}
	 */
	private function oz(string $platform, array $args): array
	{
		// starts the container on first use
		$target = $this->host($platform)->describe();

		$process = new Process(
			[\PHP_BINARY, \dirname(__DIR__, 3) . '/bin/oz', ...$args, '--host=' . $target, '--identity=' . self::$dir . '/id'],
			self::$dir,
			['OZ_SSH_COMMAND' => self::sshCommand()],
			null,
			1800.0
		);

		$process->run();

		return ['code' => (int) $process->getExitCode(), 'output' => $process->getOutput() . $process->getErrorOutput()];
	}

	private static function sshCommand(): string
	{
		return 'ssh -o UserKnownHostsFile=' . self::$dir . '/known_hosts -o LogLevel=ERROR';
	}

	private static function container(string $platform): string
	{
		return 'oz-test-ssh-' . $platform;
	}

	/**
	 * @return array{code: int, output: string}
	 */
	private static function exec(string $platform, string $command): array
	{
		return self::docker(['exec', self::container($platform), 'sh', '-c', $command]);
	}

	/**
	 * @param list<string> $args
	 *
	 * @return array{code: int, output: string}
	 */
	private static function docker(array $args): array
	{
		$process = new Process(['docker', ...$args], null, null, null, 1800.0);

		$process->run();

		return ['code' => (int) $process->getExitCode(), 'output' => $process->getOutput() . $process->getErrorOutput()];
	}

	private static function available(string $tool): bool
	{
		return 0 === (new Process(['sh', '-c', 'command -v ' . \escapeshellarg($tool)]))->run();
	}
}
