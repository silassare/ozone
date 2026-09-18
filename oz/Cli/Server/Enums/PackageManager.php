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

namespace OZONE\Core\Cli\Server\Enums;

use OZONE\Core\Cli\Server\Interfaces\HostInterface;
use OZONE\Core\Cli\Server\LocalHost;

/**
 * Enum PackageManager.
 *
 * The package managers `oz server provision` knows how to drive, and the package names each one
 * uses for what a server needs. Everything here is a pure mapping, so a plan can be built and
 * asserted on any machine, whatever it actually runs.
 */
enum PackageManager: string
{
	case APT     = 'apt';
	case APK     = 'apk';
	case DNF     = 'dnf';
	case PACMAN  = 'pacman';
	case BREW    = 'brew';
	case UNKNOWN = 'unknown';

	/**
	 * The manager of a host, from the binaries it has: this machine by default.
	 */
	public static function detect(?HostInterface $host = null): self
	{
		$host ??= new LocalHost();

		foreach (
			[
				'apt-get' => self::APT,
				'apk'     => self::APK,
				'dnf'     => self::DNF,
				'pacman'  => self::PACMAN,
				'brew'    => self::BREW,
			] as $binary => $manager
		) {
			if ($host->hasBinary($binary)) {
				return $manager;
			}
		}

		return self::UNKNOWN;
	}

	/**
	 * The command that refreshes the package index, or null when the manager needs none.
	 */
	public function refreshCommand(): ?string
	{
		return match ($this) {
			self::APT                              => 'apt-get update -qq',
			self::APK                              => 'apk update --quiet',
			self::BREW                             => 'brew update',
			self::DNF, self::PACMAN, self::UNKNOWN => null,
		};
	}

	/**
	 * The command that installs packages, without prompting.
	 *
	 * @param list<string> $packages
	 */
	public function installCommand(array $packages): string
	{
		\sort($packages);

		$list = \implode(' ', \array_map('escapeshellarg', $packages));

		return match ($this) {
			self::APT    => 'DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends ' . $list,
			self::APK    => 'apk add --no-cache ' . $list,
			self::DNF    => 'dnf install -y ' . $list,
			self::PACMAN => 'pacman -S --needed --noconfirm ' . $list,
			self::BREW   => 'brew install ' . $list,
			// Never reached: a plan for an unknown manager is refused before it is built.
			self::UNKNOWN => 'false # no supported package manager: install ' . $list,
		};
	}

	/**
	 * The package providing a PHP extension, or null when the distribution builds it into PHP.
	 *
	 * Most of what OZone requires is compiled into the base PHP package on most distributions:
	 * asking for `php-json` or `php-pdo` on Debian fails, because no such package exists. Returning
	 * null for those is what keeps a plan installable.
	 *
	 * Verified against Debian 12 and Alpine 3 (see `ServerProvisionTest`); the Fedora and Arch maps
	 * follow their documented package splits but are not yet covered by a container run.
	 */
	public function phpExtensionPackage(string $extension): ?string
	{
		return match ($this) {
			// Debian and Ubuntu: pdo, json, openssl, posix, fileinfo and libxml are in php-cli;
			// the XML extensions come together in php-xml.
			self::APT => match ($extension) {
				'pdo', 'json', 'openssl', 'posix', 'fileinfo', 'libxml' => null,
				'simplexml', 'dom', 'xml'                               => 'php-xml',
				default                                                 => 'php-' . $extension,
			},
			// Fedora: posix lives in php-process, the XML extensions in php-xml, and the rest of
			// what OZone needs is in php-common.
			self::DNF => match ($extension) {
				'json', 'openssl', 'fileinfo'       => null,
				'posix'                             => 'php-process',
				'simplexml', 'dom', 'xml', 'libxml' => 'php-xml',
				default                             => 'php-' . $extension,
			},
			// Arch ships one `php` package with almost everything built in.
			self::PACMAN => \in_array(
				$extension,
				[
					'pdo', 'json', 'posix', 'libxml', 'simplexml', 'dom', 'xml',
					'fileinfo', 'openssl', 'mbstring', 'bcmath',
				],
				true
			) ? null : 'php-' . $extension,
			// Alpine names every extension after the PHP series it belongs to. `libxml` is the one
			// with no package of its own: it comes with `php{ver}-xml` (verified against alpine:3).
			self::APK => self::alpinePrefix() . '-' . ('libxml' === $extension ? 'xml' : $extension),
			// Homebrew ships one `php` formula with everything built in.
			self::BREW    => null,
			self::UNKNOWN => 'php-' . $extension,
		};
	}

	/**
	 * The Alpine package prefix for the running PHP series, e.g. `php84`.
	 *
	 * Alpine has no unversioned `php` package; the series of the PHP running `oz` is the one the
	 * host is expected to keep.
	 */
	public static function alpinePrefix(): string
	{
		return 'php' . \PHP_MAJOR_VERSION . \PHP_MINOR_VERSION;
	}

	/**
	 * The package providing PHP-FPM.
	 */
	public function phpFpmPackage(): string
	{
		return match ($this) {
			self::APT, self::DNF, self::PACMAN, self::UNKNOWN => 'php-fpm',
			self::APK                                         => self::alpinePrefix() . '-fpm',
			// Homebrew's `php` formula includes php-fpm.
			self::BREW                                        => 'php',
		};
	}

	/**
	 * The package providing a service, or null when this manager does not ship it under a known name.
	 */
	public function servicePackage(string $service): ?string
	{
		return match ($service) {
			'nginx'      => 'nginx',
			'apache'     => match ($this) {
				self::APT, self::APK, self::UNKNOWN => 'apache2',
				self::DNF                           => 'httpd',
				self::PACMAN                        => 'apache',
				self::BREW                          => 'httpd',
			},
			'caddy'      => 'caddy',
			'mysql'      => match ($this) {
				self::APT, self::DNF, self::UNKNOWN => 'mariadb-server',
				self::PACMAN, self::APK, self::BREW => 'mariadb',
			},
			'postgresql' => match ($this) {
				self::DNF                                                      => 'postgresql-server',
				self::APT, self::PACMAN, self::APK, self::BREW, self::UNKNOWN  => 'postgresql',
			},
			'redis'      => match ($this) {
				self::APT                                                     => 'redis-server',
				self::DNF, self::PACMAN, self::APK, self::BREW, self::UNKNOWN => 'redis',
			},
			'certbot'    => 'certbot',
			'ufw'        => 'ufw',
			'firewalld'  => 'firewalld',
			default      => null,
		};
	}

	/**
	 * The firewall this manager's distributions ship by default.
	 */
	public function firewall(): string
	{
		return match ($this) {
			self::APT, self::PACMAN, self::APK, self::BREW => 'ufw',
			self::DNF, self::UNKNOWN                       => 'firewalld',
		};
	}

	/**
	 * Whether `oz server provision` is covered by the container test matrix for this manager.
	 *
	 * apt and apk are provisioned for real by `ServerProvisionTest`. The others are written from
	 * their documented package splits and never run -- and the apt map was wrong in exactly that way
	 * (`php-json` and `php-pdo` are not Debian packages) until a real container caught it, so the
	 * command warns rather than pretending.
	 */
	public function isVerified(): bool
	{
		return self::APT === $this || self::APK === $this;
	}

	/**
	 * Whether this manager belongs to a machine one provisions as a server.
	 *
	 * Homebrew is a developer machine: no systemd, no distribution service names, and nobody serves
	 * production from it. `install` supports it for getting `oz` onto a Mac; provisioning does not.
	 */
	public function isServerTarget(): bool
	{
		return self::BREW !== $this && self::UNKNOWN !== $this;
	}
}
