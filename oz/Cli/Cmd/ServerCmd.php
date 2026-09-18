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

use Kli\KliArgs;
use Kli\Table\KliTable;
use Override;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Server\Enums\PackageManager;
use OZONE\Core\Cli\Server\Interfaces\HostInterface;
use OZONE\Core\Cli\Server\Provisioner;
use OZONE\Core\Cli\Server\ProvisionManifest;
use OZONE\Core\Cli\Server\ProvisionPlan;
use OZONE\Core\Cli\Server\ProvisionStep;

/**
 * Class ServerCmd.
 *
 * Prepares a server that will not run Docker (or installs Docker on one that will).
 *
 * This is deliberately not in the `install` script: here it is versioned with the framework, it can
 * be printed before it runs, it reads the extensions the project actually requires, and it records
 * what it did so a second run is a diff.
 */
final class ServerCmd extends Command
{
	/**
	 * Provisions this host.
	 *
	 * @param KliArgs $args
	 */
	public function provision(KliArgs $args): void
	{
		$cli     = $this->getCli();
		$dry_run = (bool) $args->get('dry-run');
		$yes     = (bool) $args->get('yes');
		$host    = self::hostFromArgs($args);
		$manager = PackageManager::detect($host);

		$provisioner = new Provisioner(
			$manager,
			(string) $args->get('mode'),
			(string) $args->get('web-server'),
			(string) $args->get('db'),
			(bool) $args->get('redis'),
			(int) $args->get('ssh-port'),
			self::parseList((string) $args->get('domains')),
			(string) $args->get('tls-email'),
			!$args->get('no-firewall'),
		);

		$plan     = $provisioner->plan();
		$manifest = ProvisionManifest::load((string) $args->get('manifest'), $host);

		if ($plan->isEmpty()) {
			$cli->info('Nothing to do.');

			return;
		}

		$this->showPlan($plan, $manifest, $host, $manager);

		if ($dry_run) {
			$cli->info('Nothing was run (--dry-run).');

			return;
		}

		if (!$host->isRoot()) {
			$cli->error(\sprintf(
				'Provisioning needs root on %s. Re-run as root, or use --dry-run to see the plan.',
				$host->describe()
			));

			return;
		}

		if (!$yes) {
			if (!$cli->canPrompt()) {
				$cli->error('Refusing to provision without confirmation: pass --yes, or run --dry-run first.');

				return;
			}

			$answer = \strtolower(\trim($cli->readLine('Run this plan? [y/N] ')));

			if ('y' !== $answer && 'yes' !== $answer) {
				$cli->info('Cancelled.');

				return;
			}
		}

		$ran = $plan->run($host, $manifest, static function (ProvisionStep $step, string $state) use ($cli): void {
			match ($state) {
				'skipped' => $cli->info(\sprintf('- %s: already done', $step->name)),
				'running' => $cli->writeLn(\sprintf('> %s', $step->name)),
				'done'    => $cli->success(\sprintf('  %s: ok', $step->name)),
				default   => null,
			};
		});

		$cli->success(\sprintf(
			'%d step(s) applied. What was installed is recorded in %s.',
			\count($ran),
			$manifest->path
		));
	}

	/**
	 * Shows what an earlier run installed.
	 *
	 * @param KliArgs $args
	 */
	public function status(KliArgs $args): void
	{
		$cli      = $this->getCli();
		$manifest = ProvisionManifest::load((string) $args->get('manifest'), self::hostFromArgs($args));
		$steps    = $manifest->steps();

		if ($args->get('json')) {
			$cli->writeJson([
				'manifest'    => $manifest->path,
				'provisioned' => !empty($steps),
				'steps'       => \array_map(static fn(string $step): array => [
					'step'       => $step,
					'applied_at' => $manifest->at($step),
				], $steps),
			]);
		}

		if (empty($steps)) {
			$cli->info(\sprintf(
				'No provision manifest at %s: this host was not provisioned by OZone.',
				$manifest->path
			));

			return;
		}

		$table = new KliTable();
		$table->addHeader('Step', 'step')->alignLeft();
		$table->addHeader('Applied', 'at')->alignLeft();
		$table->addRows(\array_map(static fn(string $step): array => [
			'step' => $step,
			'at'   => \date('Y-m-d H:i', (int) $manifest->at($step)),
		], $steps));

		$cli->writeLn((string) $table, false);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function describe(): void
	{
		$this->description('Prepare a server to run OZone.');

		$provision = $this->action('provision', 'Install and configure what a server needs.');

		$provision->option('mode', 'm', [], 1)
			->description('bare: PHP-FPM, a web server and a database on the host. docker: Docker only.')
			->string()
			->def(Provisioner::MODE_BARE);
		$provision->option('web-server', 'w', [], 2)
			->description('nginx, apache, caddy, or none.')
			->string()
			->def('nginx');
		$provision->option('db', 'd', [], 3)
			->description('mysql, postgresql, or none for a managed database.')
			->string()
			->def('none');
		$provision->option('redis', 'r', [], 4)
			->description('Also install Redis, for the cache and the job queue.')
			->bool()
			->def(false);
		$provision->option('ssh-port', '', [], 5)
			->description('The SSH port the firewall keeps open.')
			->number()
			->def(22);
		$provision->option('domains', '', [], 6)
			->description('Comma-separated domains to request a TLS certificate for; none to skip.')
			->string()
			->def('');
		$provision->option('tls-email', '', [], 7)
			->description('The address to register the certificate with.')
			->string()
			->def('');
		$provision->option('no-firewall', '', [], 8)
			->description('Leave the firewall alone.')
			->bool()
			->def(false);
		$provision->option('dry-run', '', [], 9)
			->description('Print the plan and run nothing.')
			->bool()
			->def(false);
		$provision->option('yes', 'y', [], 10)
			->description('Do not ask for confirmation.')
			->bool()
			->def(false);
		$provision->option('manifest', '', [], 11)
			->description('Where to record what was installed.')
			->string()
			->def(ProvisionManifest::DEFAULT_PATH);

		self::withHostOptions($provision);
		$provision->handler($this->provision(...));

		$status = $this->action('status', 'Show what an OZone provision run installed on this host.');
		$status->option('manifest', '', [], 1)
			->description('Where the manifest is.')
			->string()
			->def(ProvisionManifest::DEFAULT_PATH);

		self::withJsonSupport($status);
		self::withHostOptions($status);

		$status->handler($this->status(...));
	}

	/**
	 * Prints the plan, marking what an earlier run already did.
	 */
	private function showPlan(
		ProvisionPlan $plan,
		ProvisionManifest $manifest,
		HostInterface $host,
		PackageManager $manager
	): void {
		$cli = $this->getCli();

		$cli->writeLn(\sprintf('Host: %s', $host->describe()));
		$cli->writeLn(\sprintf('Package manager: %s', $manager->value));

		if (!$manager->isVerified()) {
			// apt and apk are provisioned for real by the container tests; the others are written
			// from their documented package splits. The apt map was wrong in exactly that way until
			// a container caught it, so say so instead of implying the same confidence.
			$cli->warn(\sprintf(
				'The %s package names are not covered by OZone\'s container tests. Run --dry-run'
					. ' first and check them.',
				$manager->value
			));
		}

		$cli->writeLn();

		foreach ($plan->steps() as $step) {
			$done = $manifest->has($step->name) || $step->isSatisfied($host);

			$cli->writeLn(\sprintf('%s %s -- %s', $done ? '[skip]' : '[ run]', $step->name, $step->reason));

			foreach ($step->commands as $command) {
				$cli->writeLn('        ' . $command);
			}
		}

		$cli->writeLn();
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
			static fn(string $v): bool => '' !== $v
		));
	}
}
