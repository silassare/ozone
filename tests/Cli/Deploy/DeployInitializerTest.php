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

namespace OZONE\Tests\Cli\Deploy;

use InvalidArgumentException;
use OZONE\Core\Cli\Deploy\DeployFile;
use OZONE\Core\Cli\Deploy\DeployInitializer;
use OZONE\Core\Cli\Deploy\DeployTemplates;
use OZONE\Core\Exceptions\RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Class DeployInitializerTest.
 *
 * Building a plan writes nothing, so what it would generate is asserted here. `DeployInitTest`
 * (integration) then runs the command in a real project, and the nginx file it produces is checked
 * with a real `nginx -t`.
 *
 * @internal
 *
 * @covers \OZONE\Core\Cli\Deploy\DeployFile
 * @covers \OZONE\Core\Cli\Deploy\DeployInitializer
 * @covers \OZONE\Core\Cli\Deploy\DeployPlan
 * @covers \OZONE\Core\Cli\Deploy\DeployTemplates
 */
final class DeployInitializerTest extends TestCase
{
	/**
	 * @dataProvider provideBadOptionsAreRefusedCases
	 */
	public function testBadOptionsAreRefused(array $args): void
	{
		$this->expectException(InvalidArgumentException::class);

		new DeployInitializer(...$args);
	}

	public static function provideBadOptionsAreRefusedCases(): iterable
	{
		return [
			'target' => [['kubernetes']],
			'ci'     => [[DeployInitializer::TARGET_DOCKER, 'jenkins']],
			'cron'   => [[
				DeployInitializer::TARGET_BARE,
				DeployInitializer::CI_NONE,
				[],
				'www-data',
				'www-data',
				'main',
				'64M',
				true,
				'hourly',
			]],
		];
	}

	public function testDockerPlanIsThePackageSample(): void
	{
		$plan  = (new DeployInitializer(DeployInitializer::TARGET_DOCKER))->plan();
		$paths = \array_map(static fn ($f): string => $f->path, $plan->files());

		self::assertContains('docker' . \DIRECTORY_SEPARATOR . 'Dockerfile', $paths);
		self::assertContains('docker' . \DIRECTORY_SEPARATOR . 'compose.yaml', $paths);

		// Every file comes from conf/docker/, so the generated deployment cannot drift from the
		// documented sample.
		$source = \dirname(OZ_OZONE_DIR) . \DIRECTORY_SEPARATOR . 'conf' . \DIRECTORY_SEPARATOR . 'docker';

		foreach ($plan->files() as $file) {
			$relative = \substr($file->path, \strlen('docker' . \DIRECTORY_SEPARATOR));

			self::assertSame(
				\file_get_contents($source . \DIRECTORY_SEPARATOR . $relative),
				$file->content,
				$file->path
			);
		}
	}

	public function testTheEntrypointIsGeneratedExecutable(): void
	{
		$plan = (new DeployInitializer(DeployInitializer::TARGET_DOCKER))->plan();
		$file = $plan->get('docker' . \DIRECTORY_SEPARATOR . 'docker-entrypoint.sh');

		self::assertNotNull($file);
		self::assertTrue($file->executable, 'a shell script that is not executable is useless');
	}

	public function testBarePlanHasOneVhostAndOnePoolPerScope(): void
	{
		$initializer = new DeployInitializer(DeployInitializer::TARGET_BARE);
		$plan        = $initializer->plan();
		$paths       = \array_map(static fn ($f): string => $f->path, $plan->files());
		$scopes      = $initializer->scopes();

		self::assertNotEmpty($scopes);

		$vhosts = \array_filter($paths, static fn (string $p): bool => \str_contains($p, 'nginx'));
		$pools  = \array_filter($paths, static fn (string $p): bool => \str_contains($p, 'php-fpm'));

		self::assertCount(\count($scopes), $vhosts);
		self::assertCount(\count($scopes), $pools);
	}

	public function testBarePlanCarriesTheCronTimerAndTheWorkerTemplateUnit(): void
	{
		$paths = \array_map(
			static fn ($f): string => $f->path,
			(new DeployInitializer(DeployInitializer::TARGET_BARE))->plan()->files()
		);

		$units = \array_values(\array_filter($paths, static fn (string $p): bool => \str_contains($p, 'systemd')));

		self::assertCount(3, $units, 'a cron service, its timer, and the worker template unit');

		$joined = \implode(' ', $units);

		self::assertStringContainsString('-cron.service', $joined);
		self::assertStringContainsString('-cron.timer', $joined);
		self::assertStringContainsString('-worker@.service', $joined);
	}

	public function testBareCronCanBeTheSchedulerProcess(): void
	{
		$units = [];
		$plan  = (new DeployInitializer(DeployInitializer::TARGET_BARE, cron: DeployInitializer::CRON_DAEMON))->plan();

		foreach ($plan->files() as $file) {
			if (\str_contains($file->path, '-cron.')) {
				$units[$file->path] = $file->content;
			}
		}

		// One service and no timer: the process runs a tick every minute itself.
		self::assertCount(1, $units);
		self::assertStringEndsWith('-cron.service', (string) \array_key_first($units));
		self::assertStringContainsString(' cron work', (string) \reset($units));
		self::assertStringContainsString('Restart=always', (string) \reset($units));
	}

	public function testBarePlanTurnsPreloadingOnForThePoolUser(): void
	{
		$ini = self::preloadIni(new DeployInitializer(DeployInitializer::TARGET_BARE, user: 'deploy'));

		self::assertNotNull($ini);

		// the script every build rewrites, which preloads the live release
		$script = app()->getProjectDir()->getRoot() . '.ozone' . \DIRECTORY_SEPARATOR . 'preload.php';

		self::assertStringContainsString('opcache.preload = ' . $script . "\n", $ini->content);

		// PHP-FPM starts as root: without a user, PHP refuses to preload
		self::assertStringContainsString('opcache.preload_user = deploy' . "\n", $ini->content);
	}

	public function testPreloadingCanBeLeftOut(): void
	{
		self::assertNull(self::preloadIni(new DeployInitializer(DeployInitializer::TARGET_BARE, preload: false)));

		// the Docker sample turns it on in its entrypoint (OZ_PRELOAD)
		self::assertNull(self::preloadIni(new DeployInitializer(DeployInitializer::TARGET_DOCKER)));
	}

	public function testNoGeneratedFileKeepsAnUnreplacedToken(): void
	{
		foreach ([DeployInitializer::TARGET_BARE, DeployInitializer::TARGET_DOCKER] as $target) {
			$plan = (new DeployInitializer($target, DeployInitializer::CI_GITHUB))->plan();

			foreach ($plan->files() as $file) {
				self::assertDoesNotMatchRegularExpression(
					'~__[A-Z][A-Z0-9_]*__~',
					$file->content,
					$file->path
				);
			}
		}
	}

	public function testCiIsOptional(): void
	{
		$workflow = '.github' . \DIRECTORY_SEPARATOR . 'workflows' . \DIRECTORY_SEPARATOR . 'ci.yml';

		self::assertNull((new DeployInitializer())->plan()->get($workflow));
		self::assertNotNull(
			(new DeployInitializer(DeployInitializer::TARGET_DOCKER, DeployInitializer::CI_GITHUB))
				->plan()
				->get($workflow)
		);
	}

	public function testAnUnreplacedTokenIsAnError(): void
	{
		// The safety net: a token left behind in an nginx or systemd file would be found the hard
		// way, on a server.
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('~unreplaced token~');

		DeployTemplates::render('logrotate.conf', ['project' => 'P']);
	}

	public function testAnUnknownTemplateIsAnError(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('~Unable to locate~');

		DeployTemplates::render('nope.conf', []);
	}

	public function testRenderSubstitutesEveryToken(): void
	{
		$rendered = DeployTemplates::render('logrotate.conf', [
			'project'  => 'Probe',
			'name'     => 'probe',
			'logs_dir' => '/var/www/probe/.ozone/logs',
			'user'     => 'deploy',
			'group'    => 'deploy',
		]);

		self::assertStringContainsString('/var/www/probe/.ozone/logs/*.log {', $rendered);
		self::assertStringContainsString('su deploy deploy', $rendered);
	}

	private static function preloadIni(DeployInitializer $initializer): ?DeployFile
	{
		foreach ($initializer->plan()->files() as $file) {
			if (\str_ends_with($file->path, '-preload.ini')) {
				return $file;
			}
		}

		return null;
	}
}
