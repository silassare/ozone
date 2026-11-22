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

namespace OZONE\Core\Maintenance;

use InvalidArgumentException;
use JsonException;
use OZONE\Core\App\Keys;
use OZONE\Core\Http\IpRange;
use OZONE\Core\Maintenance\Enums\MaintenanceCoverage;

/**
 * Class MaintenanceMode.
 *
 * One maintenance, as `oz maintenance on` recorded it: what it holds, who it lets through, what it
 * answers, and whether the jobs wait for it. {@see Maintenance} keeps it in a file.
 *
 * A record that cannot be read holds everything ({@see self::unreadable()}): the file exists only
 * because someone turned maintenance on, so its presence is the one fact that is certain, and a body
 * that cannot be read cannot narrow it.
 */
final class MaintenanceMode
{
	/**
	 * The shape of the record in its file; another one is a record this OZone cannot read.
	 */
	public const int FORMAT = 1;

	/**
	 * @param string              $run        what tells this maintenance from the next one: a secret
	 *                                        link's cookie is bound to it
	 * @param null|int            $since      when it started; null when the record cannot say
	 * @param MaintenanceCoverage $coverage   which requests it holds
	 * @param list<string>        $scopes     the scopes it holds, under {@see MaintenanceCoverage::SCOPES}
	 * @param list<string>        $allow      the addresses and CIDR ranges it lets through
	 * @param null|string         $secretHash the SHA-256 of the secret of its link, never the secret
	 * @param null|int            $retryAfter the seconds a client is told to wait (`Retry-After`)
	 * @param null|string         $message    what the maintenance page says beside the standard text
	 * @param bool                $pauseJobs  whether cron and the queued jobs wait for it to end
	 * @param null|string         $fault      why the record could not be read, when it could not
	 */
	public function __construct(
		public readonly string $run,
		public readonly ?int $since,
		public readonly MaintenanceCoverage $coverage = MaintenanceCoverage::ALL,
		public readonly array $scopes = [],
		public readonly array $allow = [],
		public readonly ?string $secretHash = null,
		public readonly ?int $retryAfter = null,
		public readonly ?string $message = null,
		public readonly bool $pauseJobs = false,
		public readonly ?string $fault = null,
	) {}

	/**
	 * A new maintenance, starting now, and the secret of its link: given once, to whoever turned it
	 * on, and kept only as its hash.
	 *
	 * @param list<string> $scopes the scopes held, under {@see MaintenanceCoverage::SCOPES}
	 * @param list<string> $allow  addresses and CIDR ranges let through
	 *
	 * @return array{0: self, 1: string} the record, and the secret
	 */
	public static function start(
		MaintenanceCoverage $coverage = MaintenanceCoverage::ALL,
		array $scopes = [],
		array $allow = [],
		?int $retry_after = null,
		?string $message = null,
		bool $pause_jobs = false,
	): array {
		$secret = Keys::id64();

		return [
			new self(
				Keys::id32(),
				\time(),
				$coverage,
				$scopes,
				$allow,
				\hash('sha256', $secret),
				$retry_after,
				$message,
				$pause_jobs,
			),
			$secret,
		];
	}

	/**
	 * The maintenance a record that cannot be read stands for: every scope, nobody let through but a
	 * signed-in administrator, and the jobs paused.
	 *
	 * @param string   $fault why it cannot be read
	 * @param null|int $since when the file was written, when that is known
	 */
	public static function unreadable(string $fault, ?int $since = null): self
	{
		return new self('', $since, pauseJobs: true, fault: $fault);
	}

	/**
	 * Reads a record from its JSON, an unreadable one ({@see self::unreadable()}) when it is not one.
	 */
	public static function fromJson(string $json): self
	{
		try {
			$data = \json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			return self::unreadable('not JSON: ' . $e->getMessage());
		}

		if (!\is_array($data)) {
			return self::unreadable('not a JSON object');
		}

		try {
			return self::fromArray($data);
		} catch (InvalidArgumentException $e) {
			return self::unreadable($e->getMessage());
		}
	}

	/**
	 * Reads a record from what {@see self::toArray()} wrote.
	 *
	 * Every key is checked rather than defaulted: a key a hand edit dropped would otherwise read as
	 * "nobody let through", "jobs running" or "every scope", and which of those is the safe reading
	 * depends on the key.
	 *
	 * @throws InvalidArgumentException naming the first key that is wrong
	 */
	public static function fromArray(array $data): self
	{
		if (self::FORMAT !== ($data['format'] ?? null)) {
			throw new InvalidArgumentException(\sprintf(
				'format %s, where this OZone reads %d',
				(string) \json_encode($data['format'] ?? null),
				self::FORMAT
			));
		}

		$run      = $data['run'] ?? null;
		$since    = $data['since'] ?? null;
		$coverage = MaintenanceCoverage::tryFrom(\is_string($data['covers'] ?? null) ? $data['covers'] : '');

		if (!\is_string($run) || !\preg_match('~^[a-f0-9]{32}$~', $run)) {
			throw new InvalidArgumentException('"run" is not a run id');
		}

		if (!\is_int($since)) {
			throw new InvalidArgumentException('"since" is not a time');
		}

		if (null === $coverage) {
			throw new InvalidArgumentException('"covers" is not one of: all, api, web, scopes');
		}

		$scopes = self::stringList($data, 'scopes');
		$allow  = self::stringList($data, 'allow');

		if (MaintenanceCoverage::SCOPES === $coverage && [] === $scopes) {
			throw new InvalidArgumentException('"scopes" is empty, where the record covers chosen scopes');
		}

		foreach ($allow as $entry) {
			if (!IpRange::isValid($entry)) {
				throw new InvalidArgumentException(
					\sprintf('"allow" holds "%s", which is no address or range', $entry)
				);
			}
		}

		$secret_hash = $data['secret'] ?? null;
		$retry_after = $data['retry_after'] ?? null;
		$message     = $data['message'] ?? null;
		$pause_jobs  = $data['pause_jobs'] ?? null;

		$hash_shape = '~^[a-f0-9]{64}$~';

		if (null !== $secret_hash && (!\is_string($secret_hash) || !\preg_match($hash_shape, $secret_hash))) {
			throw new InvalidArgumentException('"secret" is not a SHA-256 hash');
		}

		if (null !== $retry_after && (!\is_int($retry_after) || $retry_after < 1)) {
			throw new InvalidArgumentException('"retry_after" is not a number of seconds');
		}

		if (null !== $message && !\is_string($message)) {
			throw new InvalidArgumentException('"message" is not a text');
		}

		if (!\is_bool($pause_jobs)) {
			throw new InvalidArgumentException('"pause_jobs" is not true or false');
		}

		return new self($run, $since, $coverage, $scopes, $allow, $secret_hash, $retry_after, $message, $pause_jobs);
	}

	/**
	 * The record as its file holds it.
	 *
	 * @return array{
	 *     format: int,
	 *     run: string,
	 *     since: null|int,
	 *     covers: string,
	 *     scopes: list<string>,
	 *     allow: list<string>,
	 *     secret: null|string,
	 *     retry_after: null|int,
	 *     message: null|string,
	 *     pause_jobs: bool,
	 * }
	 */
	public function toArray(): array
	{
		return [
			'format'      => self::FORMAT,
			'run'         => $this->run,
			'since'       => $this->since,
			'covers'      => $this->coverage->value,
			'scopes'      => $this->scopes,
			'allow'       => $this->allow,
			'secret'      => $this->secretHash,
			'retry_after' => $this->retryAfter,
			'message'     => $this->message,
			'pause_jobs'  => $this->pauseJobs,
		];
	}

	/**
	 * Whether the record was read; one that was not holds everything.
	 */
	public function isReadable(): bool
	{
		return null === $this->fault;
	}

	/**
	 * Whether it holds a request of a scope.
	 *
	 * @param string $scope the scope's name, `root` for the application's own
	 * @param bool   $api   whether the scope serves the API rather than the web
	 */
	public function covers(string $scope, bool $api): bool
	{
		return match ($this->coverage) {
			MaintenanceCoverage::ALL    => true,
			MaintenanceCoverage::API    => $api,
			MaintenanceCoverage::WEB    => !$api,
			MaintenanceCoverage::SCOPES => \in_array($scope, $this->scopes, true),
		};
	}

	/**
	 * Whether a client address is one it lets through.
	 */
	public function allowsIp(?string $ip): bool
	{
		if (null === $ip || '' === $ip) {
			return false;
		}

		$packed = IpRange::pack($ip);

		if (null === $packed) {
			return false;
		}

		foreach ($this->allow as $range) {
			if (IpRange::containsPacked($range, $packed)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a secret is the one of its link, compared in constant time.
	 */
	public function acceptsSecret(string $secret): bool
	{
		return null !== $this->secretHash && '' !== $secret
			&& \hash_equals($this->secretHash, \hash('sha256', $secret));
	}

	/**
	 * The value of the cookie that lets a browser through this maintenance, and no other one.
	 *
	 * Bound to the run, which every `on` draws anew, and signed with the project secret, so the
	 * cookie of a past maintenance and one made up from the record both fail. Null for a record that
	 * cannot be read, which lets no cookie through.
	 */
	public function bypassToken(): ?string
	{
		if (!$this->isReadable() || '' === $this->run) {
			return null;
		}

		return \hash_hmac('sha256', 'oz:maintenance:' . $this->run, Keys::secret());
	}

	/**
	 * Whether a cookie value is the one {@see self::bypassToken()} gives, compared in constant time.
	 */
	public function acceptsBypassToken(string $token): bool
	{
		$expected = $this->bypassToken();

		return null !== $expected && '' !== $token && \hash_equals($expected, $token);
	}

	/**
	 * A list of strings under a key.
	 *
	 * @return list<string>
	 *
	 * @throws InvalidArgumentException when it is not one
	 */
	private static function stringList(array $data, string $key): array
	{
		$list = $data[$key] ?? null;

		if (!\is_array($list) || !\array_is_list($list)) {
			throw new InvalidArgumentException(\sprintf('"%s" is not a list', $key));
		}

		foreach ($list as $entry) {
			if (!\is_string($entry) || '' === $entry) {
				throw new InvalidArgumentException(\sprintf('"%s" holds something other than a name', $key));
			}
		}

		/** @var list<string> $list */
		return $list;
	}
}
