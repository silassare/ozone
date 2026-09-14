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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Provisions throwaway containers for real, one per package manager OZone supports in production:
 * the packages are installed, the manifest is written, the firewall really activates, and a second
 * run skips what the first did.
 *
 * Debian (apt) and Alpine (apk) are what OZone recommends for a server, and they are exactly what
 * `PackageManager::isVerified()` reports as covered. dnf and pacman are written from their
 * documented package splits and deliberately not run here -- `oz server provision` warns on them
 * instead of implying the same confidence -- and Homebrew is refused outright as a server target.
 *
 * Nothing is stubbed but `systemctl`: a provisioner only ever asserted as a list of strings is not
 * known to work, and the apt map was wrong in exactly that way (`php-json` and `php-pdo` are not
 * Debian packages) until a container caught it. The sources are copied in rather than bind-mounted,
 * so the test does not depend on the host's Docker file sharing.
 *
 * @internal
 *
 * @group provision
 *
 * @coversNothing
 */
final class ServerProvisionTest extends TestCase
{
	/**
	 * Per platform: the image, the package manager `detect()` must find, and the commands that give
	 * the container a PHP able to run `oz` at all -- what the `install` script checks for.
	 *
	 * @var array<string, array{image: string, manager: string, bootstrap: string}>
	 */
	private const PLATFORMS = [
		'debian' => [
			'image'     => 'debian:12-slim',
			'manager'   => 'apt',
			// php-mbstring included: Kli renders every table through mb_*.
			'bootstrap' => 'apt-get update -qq'
				. ' && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends'
				. ' php-cli php-xml php-bcmath php-gd php-mbstring',
		],
		'alpine' => [
			'image'     => 'alpine:3',
			'manager'   => 'apk',
			// Alpine names its packages after the PHP series, and ships no unversioned `php`.
			'bootstrap' => 'apk update --quiet'
				. ' && apk add --no-cache php84 php84-xml php84-simplexml php84-bcmath php84-gd'
				. ' php84-mbstring php84-posix php84-fileinfo php84-openssl php84-pdo'
				. ' && ln -sf /usr/bin/php84 /usr/bin/php',
		],
	];

	/** @var array<string, bool> platforms whose container is up and prepared */
	private static array $ready = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		if (!self::hasDocker()) {
			if (\getenv('OZ_TEST_PROVISION_REQUIRED')) {
				self::fail('Docker is unavailable (OZ_TEST_PROVISION_REQUIRED is set).');
			}

			self::markTestSkipped('Docker is unavailable; `oz server provision` cannot be run safely.');
		}
	}

	public static function tearDownAfterClass(): void
	{
		foreach (\array_keys(self::$ready) as $platform) {
			self::docker(['rm', '-f', self::container((string) $platform)]);
		}

		self::$ready = [];

		parent::tearDownAfterClass();
	}

	/**
	 * @dataProvider providePlatforms
	 */
	public function testTheExpectedPackageManagerIsDetected(string $platform): void
	{
		$this->prepare($platform);

		$result = self::oz($platform, 'server provision --dry-run');

		self::assertSame(0, $result['code'], $result['output']);
		self::assertStringContainsString(
			'Package manager: ' . self::PLATFORMS[$platform]['manager'],
			$result['output']
		);

		// Both of these are covered by this very test, so neither may warn.
		self::assertStringNotContainsString('not covered by OZone', $result['output']);
	}

	/**
	 * @dataProvider providePlatforms
	 */
	public function testDryRunChangesNothing(string $platform): void
	{
		$this->prepare($platform);

		$result = self::oz($platform, 'server provision --dry-run --db=postgresql --redis');

		self::assertSame(0, $result['code'], $result['output']);
		self::assertStringContainsString('ufw allow 22/tcp', $result['output']);

		// Nothing installed, and no manifest.
		self::assertNotSame(0, self::exec($platform, 'test -f /etc/ozone/provision.json')['code']);
		self::assertNotSame(0, self::exec($platform, 'command -v nginx')['code']);
	}

	/**
	 * @dataProvider providePlatforms
	 */
	public function testProvisionInstallsAndRecordsWhatItDid(string $platform): void
	{
		$this->prepare($platform);

		$result = self::oz($platform, 'server provision --yes --web-server=nginx --db=none --ssh-port=2222');

		self::assertSame(0, $result['code'], $result['output']);

		// The packages are really there: this is what proves the names exist on this distribution.
		self::assertSame(0, self::exec($platform, 'command -v nginx')['code'], 'nginx was not installed');
		self::assertSame(0, self::exec($platform, 'command -v ufw')['code'], 'ufw was not installed');

		// PHP-FPM too, under whatever name this distribution gives it.
		self::assertSame(
			0,
			self::exec($platform, 'ls /usr/sbin/php-fpm* /usr/sbin/php*-fpm /usr/bin/php-fpm* 2>/dev/null | head -1')['code'],
			'php-fpm was not installed'
		);

		// The services were asked to start, on the port that was requested.
		$systemctl = self::exec($platform, 'cat /var/log/oz-systemctl.log')['output'];

		self::assertStringContainsString('nginx', $systemctl);

		$rules = self::exec($platform, 'ufw status')['output'];

		self::assertStringContainsString('Status: active', $rules);
		self::assertStringContainsString('2222/tcp', $rules);
		self::assertStringContainsString('443/tcp', $rules);

		// And the manifest records it.
		$manifest = \json_decode(
			self::exec($platform, 'cat /etc/ozone/provision.json')['output'],
			true,
			512,
			\JSON_THROW_ON_ERROR
		);

		self::assertIsArray($manifest);
		self::assertArrayHasKey('php', $manifest['steps']);
		self::assertArrayHasKey('web:nginx', $manifest['steps']);
		self::assertArrayHasKey('firewall', $manifest['steps']);
	}

	/**
	 * @dataProvider providePlatforms
	 */
	public function testASecondRunSkipsWhatTheFirstDid(string $platform): void
	{
		$this->prepare($platform);

		$result = self::oz($platform, 'server provision --yes --web-server=nginx --db=none --ssh-port=2222');

		self::assertSame(0, $result['code'], $result['output']);
		self::assertStringContainsString('already done', $result['output']);
		self::assertStringContainsString('0 step(s) applied', $result['output']);
	}

	/**
	 * @dataProvider providePlatforms
	 */
	public function testStatusReportsTheManifest(string $platform): void
	{
		$this->prepare($platform);

		$result = self::oz($platform, 'server status');

		self::assertSame(0, $result['code'], $result['output']);
		self::assertStringContainsString('web:nginx', $result['output']);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function providePlatforms(): iterable
	{
		$sets = [];

		foreach (\array_keys(self::PLATFORMS) as $platform) {
			$sets[(string) $platform] = [(string) $platform];
		}

		return $sets;
	}

	/**
	 * Starts the container of a platform and gives it a PHP, once.
	 */
	private function prepare(string $platform): void
	{
		if (isset(self::$ready[$platform])) {
			return;
		}

		$config = self::PLATFORMS[$platform];
		$name   = self::container($platform);

		self::docker(['rm', '-f', $name]);

		// NET_ADMIN so ufw can really load its rules: a firewall step that is only ever printed is
		// not a tested firewall step.
		$run = self::docker([
			'run', '-d', '--name', $name, '--cap-add=NET_ADMIN',
			$config['image'], 'sleep', '1200',
		]);

		if (0 !== $run['code']) {
			self::markTestSkipped('Unable to start the ' . $platform . ' container: ' . $run['output']);
		}

		self::$ready[$platform] = true;

		// systemd is not running in a container, so `systemctl` is replaced by a stub that records
		// its calls; a service manager would need a privileged container. It goes in
		// /usr/local/bin, which comes first on PATH and is not overwritten when a package pulls in
		// systemd itself.
		$prepare = self::exec($platform, $config['bootstrap']
			. ' && printf \'#!/bin/sh\necho "systemctl $*" >> /var/log/oz-systemctl.log\n\' > /usr/local/bin/systemctl'
			. ' && chmod +x /usr/local/bin/systemctl');

		if (0 !== $prepare['code']) {
			self::markTestSkipped('Unable to prepare the ' . $platform . ' container: ' . $prepare['output']);
		}

		$root = \dirname(__DIR__, 3);

		self::exec($platform, 'mkdir -p /ozone');

		// Only what `bin/oz` loads: the sources, the autoloader and the manifest it reads the
		// requirements from.
		foreach (['bin', 'oz', 'vendor', 'composer.json', 'VERSION'] as $path) {
			$copy = self::docker(['cp', $root . \DIRECTORY_SEPARATOR . $path, $name . ':/ozone/' . $path]);

			if (0 !== $copy['code']) {
				self::markTestSkipped('Unable to copy the sources into the container: ' . $copy['output']);
			}
		}
	}

	private static function container(string $platform): string
	{
		return 'oz-test-provision-' . $platform;
	}

	/**
	 * Runs `oz` in a platform's container, from a directory that is not a project.
	 *
	 * @return array{code: int, output: string}
	 */
	private static function oz(string $platform, string $args): array
	{
		return self::exec($platform, 'cd /tmp && php /ozone/bin/oz ' . $args);
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
		$process = new Process(['docker', ...$args], null, null, null, 900.0);

		$process->run();

		return [
			'code'   => (int) $process->getExitCode(),
			'output' => $process->getOutput() . $process->getErrorOutput(),
		];
	}

	private static function hasDocker(): bool
	{
		$process = new Process(['docker', 'info'], null, null, null, 30.0);

		$process->run();

		return $process->isSuccessful();
	}
}
