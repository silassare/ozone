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

use InvalidArgumentException;
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Utils\Requirements;
use OZONE\Core\Http\Uri;
use OZONE\Core\Queue\Queue;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use Throwable;

/**
 * Class DeployInitializer.
 *
 * Builds the {@see DeployPlan} of `oz deploy init`.
 *
 * A pure function of the project and the options: it reads settings and resolves directories, and
 * writes nothing, so the plan can be listed with `--dry-run` and asserted in tests.
 *
 * The two targets are deliberately asymmetric. **docker** copies `conf/docker/` out of the package,
 * so the generated files and the documented samples cannot drift apart. **bare** generates one
 * nginx vhost and one PHP-FPM pool per scope, from that scope's own `OZ_DEFAULT_ORIGIN`, plus the
 * systemd units for the cron runner and the queue workers -- things no sample can get right without
 * knowing the project's paths -- and the PHP-FPM setting that turns OPcache preloading on, which the
 * Docker sample does in its entrypoint.
 */
final class DeployInitializer
{
	public const TARGET_DOCKER = 'docker';
	public const TARGET_BARE   = 'bare';

	public const CI_GITHUB = 'github';
	public const CI_NONE   = 'none';

	/**
	 * Cron on a bare server: a systemd timer starting `oz cron run` every minute.
	 */
	public const CRON_TIMER = 'timer';

	/**
	 * Cron on a bare server: `oz cron work`, a long-running process, as a service.
	 */
	public const CRON_DAEMON = 'daemon';

	/**
	 * @param string       $target   {@see self::TARGET_DOCKER} or {@see self::TARGET_BARE}
	 * @param string       $ci       {@see self::CI_GITHUB} or {@see self::CI_NONE}
	 * @param list<string> $scopes   the scopes to generate for; every one of the project by default
	 * @param string       $user     the system user PHP runs as
	 * @param string       $web_user the system user the web server runs as
	 * @param string       $branch   the branch CI deploys from
	 * @param string       $max_body the largest request body to accept
	 * @param bool         $preload  for a bare server: whether PHP-FPM preloads the project
	 * @param string       $cron     for a bare server: {@see self::CRON_TIMER} or {@see self::CRON_DAEMON}
	 */
	public function __construct(
		private readonly string $target = self::TARGET_DOCKER,
		private readonly string $ci = self::CI_NONE,
		private readonly array $scopes = [],
		private readonly string $user = 'www-data',
		private readonly string $web_user = 'www-data',
		private readonly string $branch = 'main',
		private readonly string $max_body = '64M',
		private readonly bool $preload = true,
		private readonly string $cron = self::CRON_TIMER,
	) {
		if (!\in_array($this->target, [self::TARGET_DOCKER, self::TARGET_BARE], true)) {
			throw new InvalidArgumentException(\sprintf('Unknown deploy target "%s".', $this->target));
		}

		if (!\in_array($this->ci, [self::CI_GITHUB, self::CI_NONE], true)) {
			throw new InvalidArgumentException(\sprintf('Unknown CI provider "%s".', $this->ci));
		}

		if (!\in_array($this->cron, [self::CRON_TIMER, self::CRON_DAEMON], true)) {
			throw new InvalidArgumentException(\sprintf('Unknown cron set-up "%s".', $this->cron));
		}
	}

	/**
	 * The plan for these options.
	 */
	public function plan(): DeployPlan
	{
		$plan = new DeployPlan();

		self::TARGET_DOCKER === $this->target ? $this->planDocker($plan) : $this->planBare($plan);

		if (self::CI_GITHUB === $this->ci) {
			$this->planGithub($plan);
		}

		return $plan;
	}

	/**
	 * The scopes to generate for: those asked for, or every one the project has.
	 *
	 * @return list<string>
	 */
	public function scopes(): array
	{
		if (!empty($this->scopes)) {
			return $this->scopes;
		}

		$names = [ScopeInterface::ROOT_SCOPE];
		$root  = app()->getProjectDir()->resolve('scopes');

		if (\is_dir($root)) {
			foreach (\scandir($root) ?: [] as $entry) {
				if ('.' !== $entry && '..' !== $entry && \is_dir($root . DS . $entry)) {
					$names[] = $entry;
				}
			}
		}

		return $names;
	}

	/**
	 * The Docker sample of the package, copied as `docker/`.
	 */
	private function planDocker(DeployPlan $plan): void
	{
		$source = \dirname(OZ_OZONE_DIR) . DS . 'conf' . DS . 'docker';

		if (!\is_dir($source)) {
			throw new InvalidArgumentException(\sprintf('The Docker sample is missing from the package: %s', $source));
		}

		foreach (self::filesUnder($source) as $relative) {
			// `Dockerfile.dockerignore` is the ignore file of the Dockerfile, and Docker looks for
			// it under that exact name next to the build file.
			$plan->add(new DeployFile(
				'docker' . DS . $relative,
				(string) \file_get_contents($source . DS . $relative),
				\str_ends_with($relative, '.sh'),
				'Docker deployment: ' . $relative
			));
		}
	}

	/**
	 * nginx, PHP-FPM, systemd and logrotate, for a server without Docker.
	 */
	private function planBare(DeployPlan $plan): void
	{
		$app          = app();
		$project      = $app->getProjectDir()->getRoot();
		$project_name = (string) Settings::get('oz.config', 'OZ_PROJECT_NAME', 'ozone');
		$slug         = self::slug($project_name);
		$logs         = $app->getLogsDir()->getRoot();
		$php_version  = \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION;

		foreach ($this->scopes() as $scope_name) {
			$scope       = $app->getScope($scope_name);
			$server_name = $this->serverName($scope_name);
			$pool        = $slug . '-' . $scope_name;

			$plan->add(new DeployFile(
				'deploy' . DS . 'nginx' . DS . $server_name . '.conf',
				DeployTemplates::render('nginx.vhost.conf', [
					'scope'         => $scope_name,
					'project'       => $project_name,
					'server_name'   => $server_name,
					'document_root' => $scope->getDocumentRootDir()->getRoot(),
					'pool_name'     => $pool,
					'max_body_size' => $this->max_body,
				]),
				false,
				\sprintf('nginx vhost for the "%s" scope', $scope_name)
			));

			$plan->add(new DeployFile(
				'deploy' . DS . 'php-fpm' . DS . $pool . '.conf',
				DeployTemplates::render('php-fpm.pool.conf', [
					'scope'         => $scope_name,
					'project'       => $project_name,
					'pool_name'     => $pool,
					'php_version'   => $php_version,
					'user'          => $this->user,
					'group'         => $this->user,
					'web_user'      => $this->web_user,
					'web_group'     => $this->web_user,
					'logs_dir'      => $logs,
					'project_dir'   => $project,
					'max_body_size' => $this->max_body,
				]),
				false,
				\sprintf('PHP-FPM pool for the "%s" scope', $scope_name)
			));
		}

		if ($this->preload) {
			$plan->add(new DeployFile(
				'deploy' . DS . 'php' . DS . $slug . '-preload.ini',
				DeployTemplates::render('php.preload.ini', [
					'project'      => $project_name,
					'name'         => $slug,
					'php_version'  => $php_version,
					'preload_file' => $project . '.ozone' . DS . 'preload.php',
					'user'         => $this->user,
				]),
				false,
				'OPcache preloading, for every pool of the PHP-FPM server'
			));
		}

		$php = \PHP_BINARY;
		$oz  = $project . 'vendor' . DS . 'bin' . DS . 'oz';

		$cron_tokens = [
			'project'     => $project_name,
			'unit'        => $slug . '-cron',
			'user'        => $this->user,
			'group'       => $this->user,
			'project_dir' => $project,
			'php'         => $php,
			'oz'          => $oz,
		];

		// Real unit files, named as systemd expects them, so they are copied rather than
		// transcribed -- and so `systemd-analyze verify` can be run on them.
		if (self::CRON_DAEMON === $this->cron) {
			$plan->add(new DeployFile(
				'deploy' . DS . 'systemd' . DS . $slug . '-cron.service',
				DeployTemplates::render('systemd.cron-daemon.service', $cron_tokens),
				false,
				'systemd service for `oz cron work`, the scheduler process'
			));
		} else {
			foreach (['service' => 'systemd.cron.service', 'timer' => 'systemd.cron.timer'] as $kind => $template) {
				$plan->add(new DeployFile(
					'deploy' . DS . 'systemd' . DS . $slug . '-cron.' . $kind,
					DeployTemplates::render($template, $cron_tokens),
					false,
					\sprintf('systemd %s for `oz cron run`', $kind)
				));
			}
		}

		$plan->add(new DeployFile(
			'deploy' . DS . 'systemd' . DS . $slug . '-worker@.service',
			DeployTemplates::render('systemd.worker.service', [
				'project'     => $project_name,
				'unit'        => $slug . '-worker',
				'user'        => $this->user,
				'group'       => $this->user,
				'project_dir' => $project,
				'php'         => $php,
				'oz'          => $oz,
				'queue'       => Queue::DEFAULT,
			]),
			false,
			'systemd template unit for the queue workers'
		));

		$plan->add(new DeployFile(
			'deploy' . DS . 'logrotate' . DS . $slug,
			DeployTemplates::render('logrotate.conf', [
				'project'  => $project_name,
				'name'     => $slug,
				'logs_dir' => $logs,
				'user'     => $this->user,
				'group'    => $this->user,
			]),
			false,
			'logrotate for .ozone/logs'
		));
	}

	/**
	 * A GitHub Actions workflow: check, then deploy on a push to the branch.
	 */
	private function planGithub(DeployPlan $plan): void
	{
		$plan->add(new DeployFile(
			'.github' . DS . 'workflows' . DS . 'ci.yml',
			DeployTemplates::render('github.workflow.yml', [
				'project'        => (string) Settings::get('oz.config', 'OZ_PROJECT_NAME', 'ozone'),
				'branch'         => $this->branch,
				'php_version'    => Requirements::minPhpVersion(),
				'php_extensions' => \implode(', ', Requirements::extensions()),
			]),
			false,
			'GitHub Actions: check, then deploy'
		));
	}

	/**
	 * The host a scope answers on, from its own `OZ_DEFAULT_ORIGIN`.
	 *
	 * Read from the scope's settings **file**, not through `Settings::get()`: settings are a single
	 * layered set for the scope that is running, so the origin of a scope other than the current one
	 * is not reachable that way -- and generating every vhost with the running scope's host would be
	 * worse than useless.
	 */
	private function serverName(string $scope_name): string
	{
		$scope = app()->getScope($scope_name);

		foreach ([$scope->getSettingsDir(), $scope->getStatefulSettingsDir()] as $dir) {
			try {
				$file = $dir->resolve('oz.request.php');

				if (!\is_file($file) || !\is_readable($file)) {
					continue;
				}

				/** @psalm-suppress UnresolvableInclude */
				$settings = require $file;
				$origin   = \is_array($settings) ? (string) ($settings['OZ_DEFAULT_ORIGIN'] ?? '') : '';
				$host     = '' === $origin ? '' : Uri::createFromString($origin)->getHost();

				if ('' !== $host) {
					return $host;
				}
			} catch (Throwable) {
				// Keep looking, then fall back below.
			}
		}

		// A name the operator has to edit, rather than a vhost that silently answers on the wrong
		// host.
		return $scope_name . '.example.com';
	}

	/**
	 * The relative paths of every file under a directory.
	 *
	 * @return list<string>
	 */
	private static function filesUnder(string $dir, string $prefix = ''): array
	{
		$found = [];

		foreach (\scandir($dir) ?: [] as $entry) {
			if ('.' === $entry || '..' === $entry) {
				continue;
			}

			$path     = $dir . DS . $entry;
			$relative = '' === $prefix ? $entry : $prefix . DS . $entry;

			$found = \is_dir($path)
				? \array_merge($found, self::filesUnder($path, $relative))
				: \array_merge($found, [$relative]);
		}

		\sort($found);

		return $found;
	}

	/**
	 * A name usable in a unit, pool or file name.
	 */
	private static function slug(string $value): string
	{
		$slug = \strtolower((string) \preg_replace('~[^A-Za-z0-9]+~', '-', $value));

		return \trim($slug, '-') ?: 'ozone';
	}
}
