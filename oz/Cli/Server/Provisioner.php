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

namespace OZONE\Core\Cli\Server;

use InvalidArgumentException;
use OZONE\Core\Cli\Server\Enums\PackageManager;
use OZONE\Core\Cli\Server\Interfaces\HostInterface;
use OZONE\Core\Cli\Utils\Requirements;

/**
 * Class Provisioner.
 *
 * Turns the options of `oz server provision` into a {@see ProvisionPlan}.
 *
 * Building a plan runs nothing and needs no privileges: it is a pure function of the options and the
 * package manager, which is what makes `--dry-run` honest and the whole thing testable for every
 * distribution from one machine.
 */
final readonly class Provisioner
{
	public const string MODE_BARE   = 'bare';
	public const string MODE_DOCKER = 'docker';

	/**
	 * The PHP repository used where a distribution's own PHP is older than OZone's minimum.
	 *
	 * Debian and Ubuntu ship one series per release and it is often behind -- Debian 13 has 8.4 --
	 * while `deb.sury.org` carries every supported series for both. The keyring goes in a file of
	 * its own and the source trusts that file alone (`Signed-By`), so it signs nothing else, and the
	 * download is checked against the digest rather than trusted for having arrived over TLS.
	 */
	public const string PHP_REPO_KEY_URL    = 'https://packages.sury.org/php/apt.gpg';
	public const string PHP_REPO_KEY_SHA256 = 'b486fd5488185c4c46467960fa69c53d5085fec492cf76b9eaf3db33561c9d7c';
	public const string PHP_REPO_KEYRING    = '/usr/share/keyrings/debsuryorg-archive-keyring.gpg';
	public const string PHP_REPO_SOURCES    = '/etc/apt/sources.list.d/php.sources';

	public const array WEB_SERVERS = ['nginx', 'apache', 'caddy', 'none'];
	public const array DATABASES   = ['mysql', 'postgresql', 'none'];

	/**
	 * @param PackageManager $manager    the package manager to write the plan for
	 * @param string         $mode       {@see self::MODE_BARE} or {@see self::MODE_DOCKER}
	 * @param string         $web_server one of {@see self::WEB_SERVERS}
	 * @param string         $database   one of {@see self::DATABASES}
	 * @param bool           $redis      whether to install Redis
	 * @param int            $ssh_port   the SSH port the firewall must keep open
	 * @param list<string>   $domains    the domains to request a certificate for; none to skip TLS
	 * @param string         $tls_email  the address certbot registers
	 * @param bool           $firewall   whether to configure the firewall
	 */
	public function __construct(
		private PackageManager $manager,
		private string $mode = self::MODE_BARE,
		private string $web_server = 'nginx',
		private string $database = 'none',
		private bool $redis = false,
		private int $ssh_port = 22,
		private array $domains = [],
		private string $tls_email = '',
		private bool $firewall = true,
	) {
		if (!$this->manager->isServerTarget()) {
			throw new InvalidArgumentException(\sprintf(
				PackageManager::BREW === $this->manager
					? 'Homebrew is a developer machine, not a server: there is no systemd and no'
					. ' distribution service names to configure. Use Docker locally, or run this on'
					. ' the Linux host you are deploying to.'
					: 'No supported package manager found (apt, apk, dnf, pacman). Install what'
					. ' `oz doctor check` reports yourself, or run OZone in Docker.%s',
				''
			));
		}

		if (!\in_array($this->mode, [self::MODE_BARE, self::MODE_DOCKER], true)) {
			throw new InvalidArgumentException(\sprintf('Unknown provision mode "%s".', $this->mode));
		}

		if (!\in_array($this->web_server, self::WEB_SERVERS, true)) {
			throw new InvalidArgumentException(\sprintf('Unknown web server "%s".', $this->web_server));
		}

		if (!\in_array($this->database, self::DATABASES, true)) {
			throw new InvalidArgumentException(\sprintf('Unknown database "%s".', $this->database));
		}

		if (!empty($this->domains) && '' === $this->tls_email) {
			throw new InvalidArgumentException('A TLS certificate needs an email address (--tls-email).');
		}
	}

	/**
	 * The plan for these options.
	 */
	public function plan(): ProvisionPlan
	{
		$plan = new ProvisionPlan();

		if (null !== ($refresh = $this->manager->refreshCommand())) {
			$plan->add(new ProvisionStep(
				'packages:refresh',
				'Refresh the package index.',
				[$refresh],
			));
		}

		self::MODE_DOCKER === $this->mode ? $this->planDocker($plan) : $this->planBare($plan);

		// The firewall comes last, and only opens ports for what was installed: enabling it before
		// the services exist would deny traffic to a half-provisioned host.
		$this->planFirewall($plan);

		return $plan;
	}

	/**
	 * Adds the PHP repository when the distribution does not carry the series OZone runs on.
	 *
	 * Nothing is added when it does: a server whose own index has the packages gets no third-party
	 * source. {@see self::PHP_REPO_KEY_URL} for what is trusted and how.
	 */
	private function phpRepositoryStep(): ProvisionStep
	{
		$cli = $this->manager->phpCliPackage();

		return new ProvisionStep(
			'php:repository',
			\sprintf('A repository carrying %s, when the distribution does not.', $cli),
			[\sprintf(
				'if apt-cache policy %1$s 2>/dev/null | grep -q \'Candidate: [0-9]\'; then' . \PHP_EOL
				. '  echo "%1$s is in the distribution index: no PHP repository added."' . \PHP_EOL
				. 'else' . \PHP_EOL
				. '  %2$s' . \PHP_EOL
				. '  wget -qO %3$s.new %4$s || { echo "Cannot fetch %4$s." >&2; exit 1; }' . \PHP_EOL
				. '  echo "%5$s  %3$s.new" | sha256sum -c - >/dev/null 2>&1 || {' . \PHP_EOL
				. '    rm -f %3$s.new;' . \PHP_EOL
				. '    echo "The key at %4$s is not the one OZone pins (%5$s)." >&2;' . \PHP_EOL
				. '    exit 1; }' . \PHP_EOL
				. '  mv %3$s.new %3$s' . \PHP_EOL
				. '  . /etc/os-release' . \PHP_EOL
				. '  printf \'Types: deb\nURIs: %6$s\nSuites: %%s\nComponents: main\nSigned-By: %3$s\n\''
				. ' "$VERSION_CODENAME" > %7$s' . \PHP_EOL
				. '  apt-get update -qq' . \PHP_EOL
				. 'fi',
				$cli,
				$this->manager->installCommand(['ca-certificates', 'wget']),
				self::PHP_REPO_KEYRING,
				self::PHP_REPO_KEY_URL,
				self::PHP_REPO_KEY_SHA256,
				\dirname(self::PHP_REPO_KEY_URL) . '/',
				self::PHP_REPO_SOURCES,
			)],
		);
	}

	/**
	 * PHP-FPM, a web server, a database and Redis, installed on the host.
	 */
	private function planBare(ProvisionPlan $plan): void
	{
		$packages = [$this->manager->phpCliPackage(), $this->manager->phpFpmPackage()];

		foreach (Requirements::extensions() as $extension) {
			$package = $this->manager->phpExtensionPackage($extension);

			// null: the distribution builds the extension into PHP, so there is nothing to install.
			if (null !== $package) {
				$packages[] = $package;
			}
		}

		if (PackageManager::APT === $this->manager) {
			$plan->add($this->phpRepositoryStep());
		}

		$plan->add(new ProvisionStep(
			'php',
			'PHP (its command line and FPM) and the extensions OZone requires.',
			[$this->manager->installCommand(\array_values(\array_unique($packages)))],
		));

		// A distribution ships one PHP series (Debian 12 has 8.2): installed is not enough, it must
		// be one OZone runs on, else the provision stops here rather than leave a server that cannot.
		$min = Requirements::minPhpVersion();
		$php = $this->manager->phpBinary();

		$plan->add(new ProvisionStep(
			'php:version',
			\sprintf('PHP %s or newer, which OZone needs: the distribution\'s is checked.', $min),
			[\sprintf(
				'%1$s -r %2$s %3$s || { echo "OZone needs PHP %3$s or newer, this server has $(%1$s -r %4$s):'
				. ' its distribution ships an older one and no repository carrying %3$s was added.'
				. ' Use a distribution whose PHP is %3$s or newer, or add a repository that has it." >&2;'
				. ' exit 1; }',
				$php,
				\escapeshellarg('exit(version_compare(PHP_VERSION, $argv[1], ">=") ? 0 : 1);'),
				$min,
				\escapeshellarg('echo PHP_VERSION;'),
			)],
		));

		$services = [$this->manager->phpFpmService()];

		if ('none' !== $this->web_server) {
			$package = (string) $this->manager->servicePackage($this->web_server);

			// The binary is not always the package name: Apache is apache2 on Debian, httpd on
			// Fedora.
			$binary = match ($this->web_server) {
				'apache' => PackageManager::DNF === $this->manager ? 'httpd' : 'apache2',
				default  => $this->web_server,
			};

			$plan->add(new ProvisionStep(
				'web:' . $this->web_server,
				\sprintf('The %s web server.', $this->web_server),
				[$this->manager->installCommand([$package])],
				static fn (HostInterface $host): bool => $host->hasBinary($binary),
			));

			$services[] = $package;
		}

		if ('none' !== $this->database) {
			$package = (string) $this->manager->servicePackage($this->database);

			$plan->add(new ProvisionStep(
				'db:' . $this->database,
				\sprintf('The %s server. Use --db=none for a managed database.', $this->database),
				[$this->manager->installCommand([$package])],
			));

			$services[] = $package;
		}

		if ($this->redis) {
			$package = (string) $this->manager->servicePackage('redis');

			$plan->add(new ProvisionStep(
				'redis',
				'Redis, for the cache and the job queue.',
				[$this->manager->installCommand([$package])],
			));

			$services[] = $package;
		}

		$plan->add(new ProvisionStep(
			'services:enable',
			'Start the services, and start them at boot.',
			\array_map(
				static fn (string $service): string => \sprintf('systemctl enable --now %s', \escapeshellarg($service)),
				$services
			),
		));

		$this->planTls($plan);
	}

	/**
	 * Docker only: the rest of a project's deployment is generated by `oz deploy init`.
	 */
	private function planDocker(ProvisionPlan $plan): void
	{
		$packages = match ($this->manager) {
			PackageManager::APT    => ['docker.io', 'docker-compose-plugin'],
			PackageManager::DNF    => ['docker', 'docker-compose-plugin'],
			PackageManager::PACMAN => ['docker', 'docker-compose'],
			PackageManager::APK    => ['docker', 'docker-cli-compose'],
			default                => ['docker'],
		};

		$plan->add(new ProvisionStep(
			'docker',
			'Docker and the compose plugin.',
			[$this->manager->installCommand($packages)],
			static fn (HostInterface $host): bool => $host->hasBinary('docker'),
		));

		$plan->add(new ProvisionStep(
			'services:enable',
			'Start Docker, and start it at boot.',
			['systemctl enable --now docker'],
		));
	}

	/**
	 * A certificate for the given domains, when any.
	 */
	private function planTls(ProvisionPlan $plan): void
	{
		if (empty($this->domains)) {
			return;
		}

		$package  = (string) $this->manager->servicePackage('certbot');
		$commands = [$this->manager->installCommand([$package])];

		$args = '';

		foreach ($this->domains as $domain) {
			$args .= ' -d ' . \escapeshellarg($domain);
		}

		$commands[] = \sprintf(
			'certbot %s --non-interactive --agree-tos -m %s%s',
			'apache' === $this->web_server ? '--apache' : '--nginx',
			\escapeshellarg($this->tls_email),
			$args
		);

		$plan->add(new ProvisionStep(
			'tls',
			\sprintf('A certificate for %s.', \implode(', ', $this->domains)),
			$commands,
		));
	}

	/**
	 * The firewall: SSH, and HTTP/HTTPS when a web server was installed.
	 */
	private function planFirewall(ProvisionPlan $plan): void
	{
		if (!$this->firewall) {
			return;
		}

		$firewall = $this->manager->firewall();
		$package  = (string) $this->manager->servicePackage($firewall);
		$web      = 'none' !== $this->web_server || self::MODE_DOCKER === $this->mode;

		if ('ufw' === $firewall) {
			$commands = [$this->manager->installCommand([$package])];

			// SSH is allowed before anything is denied or enabled: the opposite order locks the
			// operator out of the server they are provisioning.
			$commands[] = \sprintf('ufw allow %d/tcp', $this->ssh_port);

			if ($web) {
				$commands[] = 'ufw allow 80/tcp';
				$commands[] = 'ufw allow 443/tcp';
			}

			$commands[] = 'ufw default deny incoming';
			$commands[] = 'ufw default allow outgoing';
			$commands[] = 'ufw --force enable';
		} else {
			$commands = [
				$this->manager->installCommand([$package]),
				'systemctl enable --now firewalld',
				\sprintf('firewall-cmd --permanent --add-port=%d/tcp', $this->ssh_port),
			];

			if ($web) {
				$commands[] = 'firewall-cmd --permanent --add-service=http';
				$commands[] = 'firewall-cmd --permanent --add-service=https';
			}

			$commands[] = 'firewall-cmd --reload';
		}

		$plan->add(new ProvisionStep(
			'firewall',
			\sprintf(
				'Allow SSH on %d%s through %s, and deny the rest.',
				$this->ssh_port,
				$web ? ', HTTP and HTTPS' : '',
				$firewall
			),
			$commands,
		));
	}
}
