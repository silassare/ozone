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

namespace OZONE\Core\Cli\Cmd;

use Kli\KliAction;
use Kli\KliArgs;
use Override;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Deploy\DeployInitializer;
use OZONE\Core\Cli\Deploy\ReleaseLayout;
use OZONE\Core\Cli\Deploy\Releaser;
use OZONE\Core\Cli\Server\ProvisionStep;
use OZONE\Core\Cli\Server\ShellRunner;
use OZONE\Core\Cli\Utils\Utils;

/**
 * Class DeployCmd.
 *
 * Generates a project's deployment files.
 *
 * They are generated into the project and committed: a deployment that lives in the repository can
 * be reviewed, and a server can be rebuilt from it. Nothing here touches a server -- that is
 * `oz server provision` for the host, and the generated files for the rest.
 */
final class DeployCmd extends Command
{
	/**
	 * Writes the deployment files.
	 *
	 * @param KliArgs $args
	 */
	public function init(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		$cli   = $this->getCli();
		$force = (bool) $args->get('force');

		$initializer = new DeployInitializer(
			(string) $args->get('target'),
			(string) $args->get('ci'),
			self::parseList((string) $args->get('scopes')),
			(string) $args->get('user'),
			(string) $args->get('web-user'),
			(string) $args->get('branch'),
			(string) $args->get('max-body-size'),
			!$args->get('no-preload'),
			(string) $args->get('cron'),
		);

		$plan = $initializer->plan();

		if ($plan->isEmpty()) {
			$cli->info('Nothing to generate.');

			return;
		}

		if ($args->get('dry-run')) {
			foreach ($plan->files() as $file) {
				$cli->writeLn(\sprintf('%s -- %s', $file->path, $file->reason));
			}

			$cli->info('Nothing was written (--dry-run).');

			return;
		}

		$result = $plan->write(app()->getProjectDir(), $force);

		foreach ($result['written'] as $path) {
			$cli->success($path);
		}

		foreach ($result['skipped'] as $path) {
			// Never silently: these files are edited after generation (a domain, a certificate path,
			// a worker count), and replacing one would throw that away.
			$cli->info(\sprintf('%s: already there, kept (use --force to replace)', $path));
		}

		$cli->writeLn();
		$cli->info(\sprintf(
			'%d file(s) written, %d kept. Review them, commit them, then install them on the server.',
			\count($result['written']),
			\count($result['skipped'])
		));

		if (DeployInitializer::TARGET_BARE === $args->get('target')) {
			$cli->writeLn('The generated files are not installed: copy them where their header says,'
				. ' then reload nginx, php-fpm and systemd.');
		}
	}

	/**
	 * Deploys a release.
	 *
	 * @param KliArgs $args
	 */
	public function run(KliArgs $args): void
	{
		$cli  = $this->getCli();
		$root = \rtrim((string) $args->get('root'), DS);

		$releaser = new Releaser(
			$root,
			(string) $args->get('repository'),
			(string) $args->get('ref'),
			(string) $args->get('release'),
			(int) $args->get('keep'),
			!$args->get('skip-migrations'),
			($url = (string) $args->get('health-url')) === '' ? null : $url,
			self::parseList((string) $args->get('restart')),
		);

		$cli->writeLn(\sprintf('Deploy root: %s', $root));
		$cli->writeLn(\sprintf('Release:     %s', $releaser->releaseName()));
		$cli->writeLn(\sprintf('Current:     %s', ReleaseLayout::currentRelease($root) ?? '(none)'));
		$cli->writeLn();

		foreach ($releaser->steps() as $step) {
			$cli->writeLn(\sprintf('[%s] %s', $step->name, $step->reason));

			foreach ($step->commands as $command) {
				$cli->writeLn('        ' . $command);
			}
		}

		$cli->writeLn();

		if ($args->get('dry-run')) {
			$cli->info('Nothing was run (--dry-run).');

			return;
		}

		$ran = $releaser->run(new ShellRunner(), static function (ProvisionStep $step, string $state) use ($cli): void {
			match ($state) {
				'running'      => $cli->writeLn('> ' . $step->name),
				'done'         => $cli->success('  ' . $step->name . ': ok'),
				'rolling-back' => $cli->error(
					'  ' . $step->name . ': failed after going live, rolling back',
					true,
					null
				),
				default        => null,
			};
		});

		$cli->success(\sprintf('Release %s is live (%d steps).', $releaser->releaseName(), \count($ran)));
	}

	/**
	 * Points `current` back at a previous release.
	 *
	 * @param KliArgs $args
	 */
	public function rollback(KliArgs $args): void
	{
		$cli  = $this->getCli();
		$root = \rtrim((string) $args->get('root'), DS);
		$to   = (string) $args->get('to');

		if ('' === $to) {
			$to = (string) ReleaseLayout::previousRelease($root);

			if ('' === $to) {
				$cli->error('There is no previous release to roll back to.');

				return;
			}
		}

		$releaser = new Releaser(
			$root,
			'',
			'main',
			'',
			5,
			false,
			null,
			self::parseList((string) $args->get('restart'))
		);
		$steps    = $releaser->rollbackSteps($to);

		foreach ($steps as $step) {
			$cli->writeLn(\sprintf('[%s] %s', $step->name, $step->reason));

			foreach ($step->commands as $command) {
				$cli->writeLn('        ' . $command);
			}
		}

		if ($args->get('dry-run')) {
			$cli->info('Nothing was run (--dry-run).');

			return;
		}

		$runner = new ShellRunner();

		foreach ($steps as $step) {
			foreach ($step->commands as $command) {
				$output = '';

				if (0 !== $runner->run($command, $output)) {
					$cli->error(\sprintf('Rollback failed on: %s', $command));

					return;
				}
			}
		}

		$cli->success(\sprintf('Rolled back to %s.', $to));
		$cli->info('A rollback does not undo a migration: check that the schema still fits this release.');
	}

	/**
	 * Lists the releases of a deploy root.
	 *
	 * @param KliArgs $args
	 */
	public function releases(KliArgs $args): void
	{
		$cli  = $this->getCli();
		$root = \rtrim((string) $args->get('root'), DS);

		if (!ReleaseLayout::isDeployRoot($root)) {
			$cli->info(\sprintf('No releases in %s: nothing was deployed there.', $root));

			return;
		}

		$current = ReleaseLayout::currentRelease($root);

		foreach (ReleaseLayout::releases($root) as $release) {
			$cli->writeLn(\sprintf('%s %s', $release === $current ? '*' : ' ', $release));
		}
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function describe(): void
	{
		$this->description('Generate the deployment files of your project, and deploy releases.');

		$init = $this->action('init', 'Write the deployment files into the project.');

		$init->option('target', 't', [], 1)
			->description('docker: images and a compose file. bare: nginx, PHP-FPM, systemd, logrotate.')
			->string()
			->def(DeployInitializer::TARGET_DOCKER);
		$init->option('ci', 'c', [], 2)
			->description('Also write a CI workflow: github, or none.')
			->string()
			->def(DeployInitializer::CI_NONE);
		$init->option('scopes', 's', [], 3)
			->description('Comma-separated scopes to generate for; every scope of the project by default.')
			->string()
			->def('');
		$init->option('user', 'u', [], 4)
			->description('The system user PHP runs as.')
			->string()
			->def('www-data');
		$init->option('web-user', '', [], 5)
			->description('The system user the web server runs as.')
			->string()
			->def('www-data');
		$init->option('branch', 'b', [], 6)
			->description('The branch CI deploys from.')
			->string()
			->def('main');
		$init->option('max-body-size', '', [], 7)
			->description('The largest request body to accept (nginx and PHP-FPM).')
			->string()
			->def('64M');
		$init->option('dry-run', '', [], 8)
			->description('List what would be written, and write nothing.')
			->bool()
			->def(false);
		$init->option('force', 'f', [], 9)
			->description('Replace files that are already there.')
			->bool()
			->def(false);
		$init->option('no-preload', '', [], 10)
			->description('For --target=bare: leave OPcache preloading out of the PHP-FPM setup.')
			->bool()
			->def(false);
		$init->option('cron', '', [], 11)
			->description(
				'For --target=bare: "timer" (a systemd timer runs `oz cron run` every minute) or "daemon"'
					. ' (`oz cron work`, a long-running process, as a service).'
			)
			->string()
			->def(DeployInitializer::CRON_TIMER);

		$init->handler($this->init(...));

		// action: deploy run
		$run = $this->action('run', 'Deploy a release, atomically.');
		self::rootOption($run, 1);
		$run->option('repository', 'r', [], 2)
			->description('The git repository to check out.')
			->string()
			->def('');
		$run->option('ref', '', [], 3)
			->description('The branch, tag or commit to deploy.')
			->string()
			->def('main');
		$run->option('release', '', [], 4)
			->description('The release directory name; a UTC timestamp by default.')
			->string()
			->def('');
		$run->option('keep', 'k', [], 5)
			->description('How many old releases to keep.')
			->number()
			->def(5);
		$run->option('skip-migrations', '', [], 6)
			->description('Do not run the pending migrations.')
			->bool()
			->def(false);
		$run->option('health-url', '', [], 7)
			->description('A URL that must answer after the swap; the release is rolled back otherwise.')
			->string()
			->def('');
		$run->option('restart', '', [], 8)
			->description('Comma-separated systemd units to restart after the swap (workers, php-fpm).')
			->string()
			->def('');
		$run->option('dry-run', '', [], 9)
			->description('Print the steps and run nothing.')
			->bool()
			->def(false);
		$run->handler($this->run(...));

		// action: deploy rollback
		$rollback = $this->action('rollback', 'Point `current` back at a previous release.');
		self::rootOption($rollback, 1);
		$rollback->option('to', 't', [], 2)
			->description('The release to go back to; the one before the current by default.')
			->string()
			->def('');
		$rollback->option('restart', '', [], 3)
			->description('Comma-separated systemd units to restart.')
			->string()
			->def('');
		$rollback->option('dry-run', '', [], 4)
			->description('Print the steps and run nothing.')
			->bool()
			->def(false);
		$rollback->handler($this->rollback(...));

		// action: deploy releases
		$releases = $this->action('releases', 'List the releases of a deploy root.');
		self::rootOption($releases, 1);
		$releases->handler($this->releases(...));
	}

	/**
	 * The deploy root option, shared by the release actions.
	 */
	private static function rootOption(KliAction $action, int $offset): void
	{
		$action->option('root', '', [], $offset)
			->description('The deploy root: it holds releases/, current, data/ and .ozone/.')
			->string()
			->def(\dirname(OZ_PROJECT_DIR, 2));
	}

	/**
	 * A comma-separated option as a list, empty entries dropped.
	 *
	 * @return list<string>
	 */
	private static function parseList(string $value): array
	{
		return \array_values(\array_filter(
			\array_map('trim', \explode(',', $value)),
			static fn (string $v): bool => '' !== $v
		));
	}
}
