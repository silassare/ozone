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
use OZONE\Core\Cli\Server\Enums\PackageManager;
use OZONE\Core\Cli\Server\Provisioner;
use OZONE\Core\Cli\Server\ProvisionPlan;
use OZONE\Core\Cli\Utils\Requirements;
use PHPUnit\Framework\TestCase;

/**
 * Class ProvisionerTest.
 *
 * Building a plan runs nothing, so every distribution's commands can be asserted from one machine.
 * What running a plan does on a real host is `ProvisionRunTest` (group `provision`), which
 * provisions a throwaway container.
 *
 * @internal
 *
 * @covers \OZONE\Core\Cli\Server\Enums\PackageManager
 * @covers \OZONE\Core\Cli\Server\Provisioner
 * @covers \OZONE\Core\Cli\Server\ProvisionPlan
 * @covers \OZONE\Core\Cli\Server\ProvisionStep
 */
final class ProvisionerTest extends TestCase
{
	public function testAnUnknownPackageManagerIsRefused(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Provisioner(PackageManager::UNKNOWN);
	}

	public function testHomebrewIsRefusedAsAServerTarget(): void
	{
		// A Mac is a developer machine: no systemd, no distribution service names. `install`
		// supports Homebrew for getting `oz` onto one; provisioning a server does not.
		self::assertFalse(PackageManager::BREW->isServerTarget());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('~developer machine~');

		new Provisioner(PackageManager::BREW);
	}

	public function testOnlyTheContainerTestedManagersClaimToBeVerified(): void
	{
		// The claim has to match ServerProvisionTest's platform matrix: apt and apk are provisioned
		// for real there, and `oz server provision` warns for anything else.
		self::assertTrue(PackageManager::APT->isVerified());
		self::assertTrue(PackageManager::APK->isVerified());
		self::assertFalse(PackageManager::DNF->isVerified());
		self::assertFalse(PackageManager::PACMAN->isVerified());
	}

	public function testAlpineTakesLibxmlFromThePhpXmlPackage(): void
	{
		// Verified against alpine:3: every extension OZone requires has a php{ver}-* package except
		// libxml, which comes with php{ver}-xml. Asking apk for php84-libxml aborts the install.
		$prefix = PackageManager::alpinePrefix();

		self::assertSame($prefix . '-xml', PackageManager::APK->phpExtensionPackage('libxml'));
		self::assertSame($prefix . '-pdo', PackageManager::APK->phpExtensionPackage('pdo'));
		self::assertSame($prefix . '-json', PackageManager::APK->phpExtensionPackage('json'));

		$command = self::stepCommands((new Provisioner(PackageManager::APK))->plan(), 'php')[0];

		self::assertStringNotContainsString("'" . $prefix . "-libxml'", $command);
		self::assertStringContainsString("'" . $prefix . "-xml'", $command);
	}

	/**
	 * @dataProvider provideBadOptionsAreRefusedCases
	 */
	public function testBadOptionsAreRefused(array $args): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Provisioner(PackageManager::APT, ...$args);
	}

	public static function provideBadOptionsAreRefusedCases(): iterable
	{
		return [
			'mode'           => [['mode' => 'chroot']],
			'web server'     => [['web_server' => 'lighttpd']],
			'database'       => [['database' => 'oracle']],
			'tls no email'   => [['domains' => ['example.com']]],
		];
	}

	public function testBarePlanInstallsPhpFpmAndEveryExtensionThatNeedsAPackage(): void
	{
		$plan     = (new Provisioner(PackageManager::APT))->plan();
		$commands = $plan->commands();
		$php      = self::stepCommands($plan, 'php');

		self::assertCount(1, $php);
		self::assertStringContainsString("'php-fpm'", $php[0]);

		foreach (Requirements::extensions() as $extension) {
			$package = PackageManager::APT->phpExtensionPackage($extension);

			if (null === $package) {
				continue;
			}

			self::assertStringContainsString("'" . $package . "'", $php[0], 'ext-' . $extension);
		}

		self::assertSame('apt-get update -qq', $commands[0], 'The index is refreshed first.');
	}

	/**
	 * The failure a real Debian container caught: `php-json`, `php-pdo`, `php-openssl`,
	 * `php-posix`, `php-fileinfo` and `php-libxml` are not packages -- they are built into
	 * `php-cli`, and asking apt for them aborts the whole install.
	 *
	 * @dataProvider provideAptNeverAsksForAnExtensionBuiltIntoPhpCases
	 */
	public function testAptNeverAsksForAnExtensionBuiltIntoPhp(string $extension): void
	{
		self::assertNull(PackageManager::APT->phpExtensionPackage($extension));

		$command = self::stepCommands((new Provisioner(PackageManager::APT))->plan(), 'php')[0];

		self::assertStringNotContainsString("'php-" . $extension . "'", $command);
	}

	public static function provideAptNeverAsksForAnExtensionBuiltIntoPhpCases(): iterable
	{
		return [
			'pdo'       => ['pdo'],
			'json'      => ['json'],
			'openssl'   => ['openssl'],
			'posix'     => ['posix'],
			'fileinfo'  => ['fileinfo'],
			'libxml'    => ['libxml'],
		];
	}

	public function testAptGroupsTheXmlExtensionsIntoPhpXml(): void
	{
		self::assertSame('php-xml', PackageManager::APT->phpExtensionPackage('simplexml'));

		$command = self::stepCommands((new Provisioner(PackageManager::APT))->plan(), 'php')[0];

		self::assertStringContainsString("'php-xml'", $command);
		self::assertStringNotContainsString("'php-simplexml'", $command);
	}

	public function testAlpinePackagesCarryThePhpSeries(): void
	{
		$prefix  = PackageManager::alpinePrefix();
		$command = self::stepCommands((new Provisioner(PackageManager::APK))->plan(), 'php')[0];

		self::assertMatchesRegularExpression('~^php\d{2,3}$~', $prefix);
		self::assertStringContainsString("'" . $prefix . "-fpm'", $command);
		self::assertStringContainsString("'" . $prefix . "-simplexml'", $command);
		self::assertStringNotContainsString("'php-fpm'", $command, 'Alpine ships no unversioned php');
	}

	public function testFedoraPutsPosixInPhpProcess(): void
	{
		self::assertSame('php-process', PackageManager::DNF->phpExtensionPackage('posix'));
		self::assertSame('php-xml', PackageManager::DNF->phpExtensionPackage('simplexml'));
	}

	public function testTheFirewallAllowsSshBeforeEnablingItself(): void
	{
		$commands = self::stepCommands((new Provisioner(PackageManager::APT))->plan(), 'firewall');
		$allow    = self::indexOf($commands, 'ufw allow 22/tcp');
		$enable   = self::indexOf($commands, 'ufw --force enable');
		$deny     = self::indexOf($commands, 'ufw default deny incoming');

		self::assertGreaterThan(-1, $allow);
		self::assertGreaterThan($allow, $deny, 'Denying before allowing SSH locks the operator out.');
		self::assertGreaterThan($deny, $enable);
	}

	public function testTheFirewallIsLastSoItNeverPrecedesTheServices(): void
	{
		$names = self::stepNames((new Provisioner(PackageManager::APT))->plan());

		self::assertSame('firewall', \end($names));
	}

	public function testTheFirewallCanBeLeftAlone(): void
	{
		$plan = (new Provisioner(PackageManager::APT, firewall: false))->plan();

		self::assertNotContains('firewall', self::stepNames($plan));
	}

	public function testNoWebServerMeansNoHttpPorts(): void
	{
		$plan     = (new Provisioner(PackageManager::APT, web_server: 'none'))->plan();
		$commands = self::stepCommands($plan, 'firewall');

		self::assertNotContains('web:nginx', self::stepNames($plan));
		self::assertNotContains('ufw allow 80/tcp', $commands);
		self::assertContains('ufw allow 22/tcp', $commands);
	}

	public function testSshPortIsHonoured(): void
	{
		$commands = self::stepCommands((new Provisioner(PackageManager::APT, ssh_port: 2222))->plan(), 'firewall');

		self::assertContains('ufw allow 2222/tcp', $commands);
		self::assertNotContains('ufw allow 22/tcp', $commands);
	}

	public function testNoDatabaseByDefaultSoAManagedOneIsNotShadowed(): void
	{
		$names = self::stepNames((new Provisioner(PackageManager::APT))->plan());

		self::assertNotContains('db:mysql', $names);
		self::assertNotContains('db:postgresql', $names);
	}

	/**
	 * @dataProvider provideDatabasePackagePerManagerCases
	 */
	public function testDatabasePackagePerManager(PackageManager $manager, string $database, string $package): void
	{
		$commands = self::stepCommands(
			(new Provisioner($manager, database: $database))->plan(),
			'db:' . $database
		);

		self::assertStringContainsString("'" . $package . "'", $commands[0]);
	}

	public static function provideDatabasePackagePerManagerCases(): iterable
	{
		return [
			'apt mysql'         => [PackageManager::APT, 'mysql', 'mariadb-server'],
			'apt postgresql'    => [PackageManager::APT, 'postgresql', 'postgresql'],
			'dnf mysql'         => [PackageManager::DNF, 'mysql', 'mariadb-server'],
			'dnf postgresql'    => [PackageManager::DNF, 'postgresql', 'postgresql-server'],
			'pacman mysql'      => [PackageManager::PACMAN, 'mysql', 'mariadb'],
			'apk postgresql'    => [PackageManager::APK, 'postgresql', 'postgresql'],
		];
	}

	public function testDockerModeInstallsOnlyDockerAndTheFirewall(): void
	{
		$names = self::stepNames((new Provisioner(PackageManager::APT, mode: Provisioner::MODE_DOCKER))->plan());

		self::assertSame(['packages:refresh', 'docker', 'services:enable', 'firewall'], $names);
	}

	public function testDockerModeStillOpensTheHttpPorts(): void
	{
		$commands = self::stepCommands(
			(new Provisioner(PackageManager::APT, mode: Provisioner::MODE_DOCKER))->plan(),
			'firewall'
		);

		self::assertContains('ufw allow 443/tcp', $commands);
	}

	public function testTlsIsSkippedWithoutADomain(): void
	{
		self::assertNotContains('tls', self::stepNames((new Provisioner(PackageManager::APT))->plan()));
	}

	public function testTlsRequestsEveryDomainOnce(): void
	{
		$plan = (new Provisioner(
			PackageManager::APT,
			domains: ['example.com', 'www.example.com'],
			tls_email: 'ops@example.com'
		))->plan();

		$commands = self::stepCommands($plan, 'tls');
		$certbot  = \end($commands);

		self::assertStringContainsString('--nginx', $certbot);
		self::assertStringContainsString("-d 'example.com'", $certbot);
		self::assertStringContainsString("-d 'www.example.com'", $certbot);
		self::assertStringContainsString("-m 'ops@example.com'", $certbot);
	}

	public function testTlsUsesTheApachePluginForApache(): void
	{
		$plan = (new Provisioner(
			PackageManager::APT,
			web_server: 'apache',
			domains: ['example.com'],
			tls_email: 'ops@example.com'
		))->plan();

		self::assertStringContainsString('--apache', \end(self::stepCommands($plan, 'tls')));
	}

	public function testFedoraUsesFirewalldAndHttpd(): void
	{
		$plan  = (new Provisioner(PackageManager::DNF, web_server: 'apache'))->plan();
		$fw    = self::stepCommands($plan, 'firewall');
		$web   = self::stepCommands($plan, 'web:apache');

		self::assertStringContainsString("'httpd'", $web[0]);
		self::assertContains('firewall-cmd --reload', $fw);
		self::assertContains('firewall-cmd --permanent --add-port=22/tcp', $fw);
	}

	public function testAlpineAndArchGetTheirOwnCommands(): void
	{
		self::assertStringStartsWith(
			'apk add --no-cache',
			self::stepCommands((new Provisioner(PackageManager::APK))->plan(), 'php')[0]
		);
		self::assertStringStartsWith(
			'pacman -S --needed --noconfirm',
			self::stepCommands((new Provisioner(PackageManager::PACMAN))->plan(), 'php')[0]
		);
	}

	public function testPacmanFoldsBuiltInExtensionsIntoThePhpPackage(): void
	{
		// Arch ships pdo, json, posix and friends inside `php`; asking for `php-pdo` fails.
		$command = self::stepCommands((new Provisioner(PackageManager::PACMAN))->plan(), 'php')[0];

		self::assertStringNotContainsString("'php-pdo'", $command);
		self::assertNull(PackageManager::PACMAN->phpExtensionPackage('pdo'));
	}

	public function testNoCommandIsEverInteractive(): void
	{
		foreach ([PackageManager::APT, PackageManager::DNF, PackageManager::PACMAN, PackageManager::APK] as $manager) {
			$plan = (new Provisioner(
				$manager,
				database: 'mysql',
				redis: true,
				domains: ['example.com'],
				tls_email: 'ops@example.com'
			))->plan();

			// Only the package manager's own commands: `firewall-cmd --add-port` is not an install.
			$prefix = \explode(' ', \ltrim($manager->installCommand(['probe']), 'DEBIAN_FRONTEND=noninteractive '))[0];

			foreach ($plan->commands() as $command) {
				if (!\str_contains($command, $prefix . ' install') && !\str_contains($command, $prefix . ' add')) {
					continue;
				}

				self::assertMatchesRegularExpression(
					'~(-y|--noconfirm|--no-cache|--non-interactive)~',
					$command,
					$command
				);
			}
		}
	}

	/**
	 * @return list<string>
	 */
	private static function stepNames(ProvisionPlan $plan): array
	{
		return \array_map(static fn ($step): string => $step->name, $plan->steps());
	}

	/**
	 * @return list<string>
	 */
	private static function stepCommands(ProvisionPlan $plan, string $name): array
	{
		foreach ($plan->steps() as $step) {
			if ($step->name === $name) {
				return $step->commands;
			}
		}

		self::fail(\sprintf('No step "%s" in the plan (%s).', $name, \implode(', ', self::stepNames($plan))));
	}

	/**
	 * @param list<string> $commands
	 */
	private static function indexOf(array $commands, string $command): int
	{
		$index = \array_search($command, $commands, true);

		return false === $index ? -1 : (int) $index;
	}
}
