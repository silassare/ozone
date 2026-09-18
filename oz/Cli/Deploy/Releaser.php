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

namespace OZONE\Core\Cli\Deploy;

use OZONE\Core\Cli\Server\Interfaces\HostInterface;
use OZONE\Core\Cli\Server\LocalHost;
use OZONE\Core\Cli\Server\ProvisionStep;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class Releaser.
 *
 * Builds the steps of `oz deploy run`: an atomic release.
 *
 * The order is the safety property, not an implementation detail. Everything that can fail happens
 * **before** the `current` symlink moves -- checkout, dependencies, ORM classes, migrations -- so a
 * failure leaves the running release untouched and serving. The swap itself is one `rename()`, then
 * the workers restart and the release is health-checked; a failed check moves the symlink back.
 *
 * Migrations are the one step a rollback cannot undo, which is why they run with a backup and why
 * `--skip-migrations` exists for a release that must not touch the schema.
 */
final class Releaser
{
	/**
	 * @param string       $root       the deploy root, holding releases/, current, data/ and .ozone/
	 * @param string       $repository the git repository to check out
	 * @param string       $ref        the branch, tag or commit to deploy
	 * @param string       $release    the name of the release directory
	 * @param int          $keep       how many old releases to keep
	 * @param bool         $migrations whether to run pending migrations
	 * @param null|string  $health_url a URL that must answer 2xx after the swap
	 * @param list<string> $restart    systemd units to restart after the swap
	 * @param string       $php        the PHP binary
	 */
	public function __construct(
		private readonly string $root,
		private readonly string $repository,
		private readonly string $ref = 'main',
		private readonly string $release = '',
		private readonly int $keep = 5,
		private readonly bool $migrations = true,
		private readonly ?string $health_url = null,
		private readonly array $restart = [],
		private readonly string $php = \PHP_BINARY,
		private readonly string $archive = '',
	) {}

	/**
	 * The release name this run uses.
	 */
	public function releaseName(): string
	{
		return '' === $this->release ? ReleaseLayout::releaseName() : $this->release;
	}

	/**
	 * The steps of the release, in order.
	 *
	 * @return list<ProvisionStep>
	 */
	public function steps(): array
	{
		$name    = $this->releaseName();
		$root    = \rtrim($this->root, DS);
		$release = ReleaseLayout::releaseDir($this->root, $name);
		$current = ReleaseLayout::currentLink($this->root);
		$oz      = $release . DS . 'vendor' . DS . 'bin' . DS . 'oz';
		$steps   = [];

		$steps[] = new ProvisionStep(
			'prepare',
			'Create the deploy root, its releases directory and the shared state directories.',
			\array_merge(
				[\sprintf('mkdir -p %s', \escapeshellarg(ReleaseLayout::releasesDir($this->root)))],
				\array_map(
					static fn (string $dir): string => \sprintf(
						'mkdir -p %s',
						\escapeshellarg($root . DS . $dir)
					),
					ReleaseLayout::sharedDirs()
				)
			),
		);

		$steps[] = $this->checkoutStep($release);

		// The state lives beside the releases, so the release links to it: nothing inside a release
		// directory may hold state, or a deploy would orphan it. The same goes for `.env`, which is
		// not in the repository at all.
		$link_shared = [];

		foreach (\array_merge(ReleaseLayout::sharedDirs(), ReleaseLayout::sharedFiles()) as $shared) {
			$link_shared[] = \sprintf('rm -rf %s', \escapeshellarg($release . DS . $shared));
			$link_shared[] = \sprintf(
				'ln -s %s %s',
				\escapeshellarg($root . DS . $shared),
				\escapeshellarg($release . DS . $shared)
			);
		}

		$steps[] = new ProvisionStep(
			'link-shared',
			'Point the release at the shared data/, .ozone/ and .env.',
			$link_shared,
		);

		$steps[] = new ProvisionStep(
			'dependencies',
			'Install the dependencies, without the development ones.',
			[
				\sprintf(
					'cd %s && composer install --no-dev --no-interaction --no-progress --prefer-dist'
						. ' --optimize-autoloader',
					\escapeshellarg($release)
				),
			],
		);

		$steps[] = new ProvisionStep(
			'link-public',
			'Create this release\'s state directories and public files symlinks.',
			[\sprintf('cd %s && %s %s project link', \escapeshellarg($release), $this->php, \escapeshellarg($oz))],
		);

		$steps[] = new ProvisionStep(
			'orm',
			'Generate the ORM classes of OZone and of the plugins.',
			[
				\sprintf(
					'cd %s && %s %s db build --build-all --class-only',
					\escapeshellarg($release),
					$this->php,
					\escapeshellarg($oz)
				),
			],
		);

		if ($this->migrations) {
			$steps[] = new ProvisionStep(
				'migrations',
				'Apply the pending migrations, after backing the database up.',
				[
					\sprintf(
						'cd %s && %s %s migrations run',
						\escapeshellarg($release),
						$this->php,
						\escapeshellarg($oz)
					),
				],
			);
		}

		// After the migrations, which change a setting the route tables are keyed by.
		$steps[] = new ProvisionStep(
			'build',
			'Compile what the requests would compile at first use (`oz project build`).',
			[
				\sprintf(
					'cd %s && %s %s project build --skip-orm',
					\escapeshellarg($release),
					$this->php,
					\escapeshellarg($oz)
				),
			],
		);

		// Everything that can fail has run. The swap is one atomic rename: `ln -sfn` on a temporary
		// link then `mv -T`, because `ln -sfn` onto an existing symlink is not atomic everywhere.
		$steps[] = new ProvisionStep(
			'go-live',
			\sprintf('Point %s at the new release.', ReleaseLayout::CURRENT),
			[
				\sprintf('ln -sfn %s %s', \escapeshellarg($release), \escapeshellarg($current . '.new')),
				\sprintf('mv -T %s %s', \escapeshellarg($current . '.new'), \escapeshellarg($current)),
			],
		);

		if (!empty($this->restart)) {
			$steps[] = new ProvisionStep(
				'restart',
				'Restart the services, so they run the new release.',
				\array_map(
					static fn (string $unit): string => \sprintf('systemctl restart %s', \escapeshellarg($unit)),
					$this->restart
				),
			);
		}

		if (null !== $this->health_url) {
			$steps[] = new ProvisionStep(
				'health',
				\sprintf('Check that %s answers.', $this->health_url),
				[
					\sprintf(
						'curl -fsS --max-time 30 -o /dev/null %s',
						\escapeshellarg($this->health_url)
					),
				],
			);
		}

		$steps[] = new ProvisionStep(
			'prune',
			\sprintf('Remove the releases older than the last %d.', $this->keep),
			[$this->pruneCommand()],
		);

		return $steps;
	}

	/**
	 * Rolls `current` back to a release, and restarts the services.
	 *
	 * @param string $to the release to go back to
	 *
	 * @return list<ProvisionStep>
	 */
	public function rollbackSteps(string $to, ?HostInterface $host = null): array
	{
		$release = ReleaseLayout::releaseDir($this->root, $to);
		$current = ReleaseLayout::currentLink($this->root);

		if (!($host ?? new LocalHost())->isDir($release)) {
			throw new RuntimeException(\sprintf('No such release: "%s".', $to));
		}

		$steps = [
			new ProvisionStep(
				'rollback',
				\sprintf('Point %s back at %s.', ReleaseLayout::CURRENT, $to),
				[
					\sprintf('ln -sfn %s %s', \escapeshellarg($release), \escapeshellarg($current . '.new')),
					\sprintf('mv -T %s %s', \escapeshellarg($current . '.new'), \escapeshellarg($current)),
				],
			),
		];

		if (!empty($this->restart)) {
			$steps[] = new ProvisionStep(
				'restart',
				'Restart the services on the previous release.',
				\array_map(
					static fn (string $unit): string => \sprintf('systemctl restart %s', \escapeshellarg($unit)),
					$this->restart
				),
			);
		}

		// A rollback does not undo a migration: the schema of the release being restored has to
		// still accept the data. That is a decision, not something to do silently.
		return $steps;
	}

	public function run(HostInterface $host, ?callable $on_step = null): array
	{
		// Checked before anything is created: a release whose `.env` is missing cannot boot, and
		// failing here is a great deal clearer than failing inside the first `oz` command.
		$missing = ReleaseLayout::missingSharedFiles($this->root, $host);

		if (!empty($missing) && $host->isDir($this->root)) {
			throw new RuntimeException(\sprintf(
				'The deploy root %s is missing %s. It is not in the repository (it holds the app'
					. ' secret and the database password): put it there once, and every release will'
					. ' be linked to it.',
				$this->root,
				\implode(', ', $missing)
			));
		}

		$previous = ReleaseLayout::currentRelease($this->root, $host);
		$live     = false;
		$ran      = [];

		foreach ($this->steps() as $step) {
			null !== $on_step && $on_step($step, 'running');

			foreach ($step->commands as $command) {
				$output = '';

				if (0 !== $host->run($command, $output)) {
					// Before the swap nothing is serving the new release, so failing here changes
					// nothing. After it, the release is live and broken: put the old one back.
					if ($live && null !== $previous) {
						null !== $on_step && $on_step($step, 'rolling-back');

						foreach ($this->rollbackSteps($previous, $host) as $back) {
							foreach ($back->commands as $back_command) {
								$host->run($back_command);
							}
						}
					}

					throw new RuntimeException(\sprintf(
						'Deploy step "%s" failed on: %s',
						$step->name,
						$command
					), [
						'_step'        => $step->name,
						'_command'     => $command,
						'_output'      => \substr($output, -2000),
						'_rolled_back' => $live && null !== $previous ? $previous : false,
					]);
				}
			}

			$ran[] = $step->name;

			null !== $on_step && $on_step($step, 'done');

			if ('go-live' === $step->name) {
				$live = true;
			}
		}

		return $ran;
	}

	/**
	 * Runs the steps, rolling `current` back when one fails after the swap.
	 *
	 * @param HostInterface $host
	 * @param null|callable $on_step called with (ProvisionStep, string $state)
	 *
	 * @return list<string> the names of the steps that ran
	 */
	/**
	 * Puts the code of the release in place, from exactly one source.
	 *
	 * - A git repository (`$repository`): a URL, a path, or a bundle file (`git bundle create`), which
	 *   carries the commits without a server to fetch them from. The ref is fetched, then checked out:
	 *   a branch, a tag or a full commit hash (`git clone --branch` takes no commit).
	 * - A tar archive (`$archive`, gzipped): the project at its root, as `git archive` writes it.
	 *
	 * The git directory is given explicitly rather than entered, so a relative source resolves from the
	 * working directory, as it did for `git clone`.
	 */
	private function checkoutStep(string $release): ProvisionStep
	{
		$has_repository = '' !== $this->repository;
		$has_archive    = '' !== $this->archive;

		if ($has_repository === $has_archive) {
			throw new RuntimeException('A release needs exactly one source: a git repository or a tar archive.');
		}

		if ($has_archive) {
			return new ProvisionStep(
				'checkout',
				\sprintf('Unpack %s.', $this->archive),
				[
					\sprintf('mkdir -p %s', \escapeshellarg($release)),
					\sprintf('tar -xzf %s -C %s', \escapeshellarg($this->archive), \escapeshellarg($release)),
				],
			);
		}

		$git = \sprintf(
			'git --git-dir=%s --work-tree=%s',
			\escapeshellarg($release . DS . '.git'),
			\escapeshellarg($release)
		);

		return new ProvisionStep(
			'checkout',
			\sprintf('Check out %s at %s.', $this->repository, $this->ref),
			[
				\sprintf('git init --quiet %s', \escapeshellarg($release)),
				\sprintf(
					'%s fetch --quiet --depth 1 %s %s',
					$git,
					\escapeshellarg($this->repository),
					\escapeshellarg($this->ref)
				),
				\sprintf('%s checkout --quiet FETCH_HEAD', $git),
			],
		);
	}

	/**
	 * Deletes the releases beyond the last `$keep`, and never the live one.
	 */
	private function pruneCommand(): string
	{
		$dir = ReleaseLayout::releasesDir($this->root);

		// `ls -1` sorted, all but the newest $keep, and never what `current` resolves to.
		return \sprintf(
			'cd %s && ls -1 | sort | head -n -%d | while read -r old; do '
				. '[ "$(readlink -f %s)" = "$(readlink -f "$old")" ] || rm -rf -- "$old"; done',
			\escapeshellarg($dir),
			\max(1, $this->keep),
			\escapeshellarg(ReleaseLayout::currentLink($this->root))
		);
	}
}
