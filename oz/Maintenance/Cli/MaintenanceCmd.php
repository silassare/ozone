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

namespace OZONE\Core\Maintenance\Cli;

use Kli\KliArgs;
use Override;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Http\IpRange;
use OZONE\Core\Maintenance\Enums\MaintenanceCoverage;
use OZONE\Core\Maintenance\Maintenance;
use OZONE\Core\Maintenance\MaintenanceGate;
use OZONE\Core\Maintenance\MaintenanceMode;
use Throwable;

/**
 * Class MaintenanceCmd.
 *
 * Turns maintenance on and off ({@see Maintenance}). Nothing here needs the database, which is what
 * a maintenance may be changing.
 */
final class MaintenanceCmd extends Command
{
	/**
	 * Puts a maintenance in force, and prints the secret of its link.
	 */
	public function on(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		$cli    = $this->getCli();
		$scopes = self::listOption($args->get('scopes'));
		$only   = $args->get('only');
		$allow  = self::listOption($args->get('allow'));

		if ([] !== $scopes && null !== $only) {
			$cli->error('Choose the scopes with --scopes or with --only, not both.');

			return;
		}

		foreach ($scopes as $scope) {
			try {
				app()->getScope($scope);
			} catch (Throwable) {
				$cli->error(\sprintf('"%s" is not a scope of this project.', $scope));

				return;
			}
		}

		foreach ($allow as $entry) {
			if (!IpRange::isValid($entry)) {
				$cli->error(\sprintf('"%s" is not an IP address or a CIDR range.', $entry));

				return;
			}
		}

		$coverage = match (true) {
			[] !== $scopes => MaintenanceCoverage::SCOPES,
			'api' === $only => MaintenanceCoverage::API,
			'web' === $only => MaintenanceCoverage::WEB,
			default         => MaintenanceCoverage::ALL,
		};

		$retry_after = $args->get('retry-after');
		$message     = \trim((string) $args->get('message'));

		$previous = Maintenance::current();

		[$mode, $secret] = MaintenanceMode::start(
			$coverage,
			$scopes,
			$allow,
			null === $retry_after ? null : (int) $retry_after,
			'' === $message ? null : $message,
			(bool) $args->get('pause-jobs'),
		);

		Maintenance::enable($mode);

		if ($args->get('json')) {
			$cli->writeJson([
				'maintenance' => self::report($mode),
				'replaced'    => null !== $previous,
				'secret'      => $secret,
				'link_param'  => MaintenanceGate::LINK_PARAM,
			]);
		}

		if (null !== $previous) {
			$cli->warn('It replaces the maintenance in force, whose link and cookies no longer work.');
		}

		$cli->success(\sprintf('Maintenance is on, for %s.', self::coverageText($mode)));
		$cli->writeLn(\sprintf(
			'Let through: %s.',
			\implode(', ', [...$mode->allow, 'signed-in administrators', 'browsers that followed the link'])
		));
		$cli->writeLn($mode->pauseJobs ? 'Cron and the queued jobs wait for "oz maintenance off".'
			: 'Cron and the queued jobs keep running.');
		$cli->writeLn('The link, shown once (only its hash is kept): any address of a held scope with');
		// Not wrapped: a terminal copy of the secret must be the whole of it.
		$cli->writeLn(\sprintf('  ?%s=%s', MaintenanceGate::LINK_PARAM, $secret), false);
	}

	/**
	 * Ends the maintenance in force.
	 */
	public function off(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		$cli = $this->getCli();
		$was = Maintenance::disable();

		if ($args->get('json')) {
			$cli->writeJson(['was_on' => $was]);
		}

		if ($was) {
			$cli->success('Maintenance is off.');
		} else {
			$cli->info('Maintenance was not on.');
		}
	}

	/**
	 * Says whether a maintenance is in force, and what it does.
	 */
	public function status(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		$cli  = $this->getCli();
		$mode = Maintenance::current();

		if ($args->get('json')) {
			$cli->writeJson(['maintenance' => null === $mode ? null : self::report($mode)]);
		}

		if (null === $mode) {
			$cli->info('Maintenance is off.');

			return;
		}

		if (!$mode->isReadable()) {
			$cli->error(\sprintf(
				'Maintenance is on, and its record cannot be read (%s): every scope is held and the jobs'
					. ' wait. Run "oz maintenance off", or "oz maintenance on" again.',
				(string) $mode->fault
			), true, null);

			return;
		}

		$cli->writeLn(self::summary($mode));
	}

	/**
	 * One line saying what a maintenance does, for `status` and `oz doctor check`.
	 */
	public static function summary(MaintenanceMode $mode): string
	{
		if (!$mode->isReadable()) {
			return \sprintf('on, record unreadable (%s): every scope held, jobs paused', (string) $mode->fault);
		}

		$parts = [
			null === $mode->since ? 'on' : 'on since ' . \date('Y-m-d H:i:s', $mode->since),
			'for ' . self::coverageText($mode),
			$mode->pauseJobs ? 'jobs paused' : 'jobs running',
		];

		if ([] !== $mode->allow) {
			$parts[] = 'allowed: ' . \implode(', ', $mode->allow);
		}

		if (null !== $mode->retryAfter) {
			$parts[] = 'retry after ' . $mode->retryAfter . ' s';
		}

		return \implode(', ', $parts);
	}

	#[Override]
	protected function describe(): void
	{
		$this->description('Turn maintenance on and off: the held scopes answer 503, the jobs may wait.');

		$on = $this->action('on', 'Put the project under maintenance, replacing the maintenance in force.');
		$on->option('scopes', 's')
			->description('Hold only these scopes, by name, comma separated (by default: every scope).')
			->string();
		$on->option('only')
			->description('Hold only the API scopes ("api") or only the web ones ("web").')
			->string()
			->pattern('~^(api|web)$~', '"%s" is neither "api" nor "web".');
		$on->option('allow', 'a')
			->description('Let these IP addresses or CIDR ranges through, comma separated.')
			->string();
		$on->option('retry-after', 'r')
			->description('The seconds a client is told to wait (Retry-After); none by default.')
			->number(1)
			->integer();
		$on->option('message', 'm')
			->description('A text the maintenance page shows, and the API answers under data.message.')
			->string();
		$on->option('pause-jobs', 'p')
			->description('Hold cron and the queued jobs until "oz maintenance off"; they run by default.')
			->bool()
			->def(false);
		self::withJsonSupport($on);
		$on->handler($this->on(...));

		$off = $this->action('off', 'End the maintenance in force.');
		self::withJsonSupport($off);
		$off->handler($this->off(...));

		$status = $this->action('status', 'Say whether the project is under maintenance, and what it holds.');
		self::withJsonSupport($status);
		$status->handler($this->status(...));
	}

	/**
	 * A maintenance as the JSON outputs report it: what its record holds, never its secret's hash or
	 * its run, which together would let a cookie be checked against the record.
	 *
	 * @return array{
	 *     readable: bool,
	 *     fault: null|string,
	 *     since: null|int,
	 *     covers: string,
	 *     scopes: list<string>,
	 *     allow: list<string>,
	 *     retry_after: null|int,
	 *     message: null|string,
	 *     pause_jobs: bool,
	 * }
	 */
	private static function report(MaintenanceMode $mode): array
	{
		return [
			'readable'    => $mode->isReadable(),
			'fault'       => $mode->fault,
			'since'       => $mode->since,
			'covers'      => $mode->coverage->value,
			'scopes'      => $mode->scopes,
			'allow'       => $mode->allow,
			'retry_after' => $mode->retryAfter,
			'message'     => $mode->message,
			'pause_jobs'  => $mode->pauseJobs,
		];
	}

	/**
	 * What a maintenance holds, for a person to read.
	 */
	private static function coverageText(MaintenanceMode $mode): string
	{
		return match ($mode->coverage) {
			MaintenanceCoverage::ALL    => 'every scope',
			MaintenanceCoverage::API    => 'the API scopes',
			MaintenanceCoverage::WEB    => 'the web scopes',
			MaintenanceCoverage::SCOPES => 'the scopes ' . \implode(', ', $mode->scopes),
		};
	}

	/**
	 * The entries of a comma separated option, trimmed, empty ones and repeats dropped.
	 *
	 * @return list<string>
	 */
	private static function listOption(mixed $value): array
	{
		if (!\is_string($value)) {
			return [];
		}

		$entries = \array_filter(
			\array_map(\trim(...), \explode(',', $value)),
			static fn (string $entry): bool => '' !== $entry
		);

		return \array_values(\array_unique($entries));
	}
}
