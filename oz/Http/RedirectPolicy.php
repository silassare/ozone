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

namespace OZONE\Core\Http;

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use Throwable;

/**
 * Class RedirectPolicy.
 *
 * Decides which hosts a client may be sent to, from the `OZ_REDIRECT_ALLOWED_HOSTS` setting:
 *
 * - `[]` (default): the host of the current request, and nothing else;
 * - `['example.com', 'https://app.example.com']`: those hosts too, a URL reduced to its host;
 * - `['*']`: any host, which is what an open redirect is.
 *
 * Hosts are compared on the host alone, lowercased: a port, a path and the scheme take no part, as
 * {@see TypeUrl} compares them too. A target naming no host stays on this server, so it is always
 * allowed.
 */
final class RedirectPolicy
{
	/**
	 * The entry of `OZ_REDIRECT_ALLOWED_HOSTS` that allows any host.
	 *
	 * Spelled as `OZ_CORS_ALLOWED_ORIGIN`'s own wildcard, so one setting does not say `*` while its
	 * neighbour says something else.
	 */
	public const string ANY_HOST = '*';

	/**
	 * RedirectPolicy constructor.
	 *
	 * @param null|list<string> $allowed lowercased hosts; null allows any host
	 */
	public function __construct(private readonly ?array $allowed) {}

	/**
	 * Builds the policy of the current request.
	 */
	public static function fromContext(Context $context): self
	{
		return self::fromSetting(
			Settings::get('oz.request', 'OZ_REDIRECT_ALLOWED_HOSTS', []),
			$context->getHost()
		);
	}

	/**
	 * Builds a policy from an `OZ_REDIRECT_ALLOWED_HOSTS` value.
	 *
	 * @param list<string>|string $setting      the setting value
	 * @param string              $current_host the host of the current request, always allowed
	 */
	public static function fromSetting(array|string $setting, string $current_host): self
	{
		$entries = \is_array($setting) ? $setting : [$setting];

		if (\in_array(self::ANY_HOST, $entries, true)) {
			return new self(null);
		}

		$allowed = [self::normalize($current_host)];

		foreach ($entries as $entry) {
			$allowed[] = self::normalize((string) $entry);
		}

		return new self(\array_values(\array_unique(\array_filter($allowed))));
	}

	/**
	 * Reduces an entry to the host it names, lowercased, or null when it names none.
	 *
	 * An entry may be written as a host (`example.com`) or as a URL (`https://example.com:8443/p`):
	 * both answer `example.com`, so a project is not refused for writing the form its neighbours in
	 * the settings use.
	 */
	public static function normalize(string $entry): ?string
	{
		$entry = \strtolower(\trim($entry));

		if (\str_contains($entry, '//')) {
			try {
				$entry = \strtolower(Uri::createFromString($entry)->getHost());
			} catch (Throwable) {
				return null;
			}
		}

		return '' === $entry ? null : $entry;
	}

	/**
	 * Whether any host is allowed (`'*'`).
	 */
	public function allowsAnyHost(): bool
	{
		return null === $this->allowed;
	}

	/**
	 * The hosts a redirection target may name, the current request's own included.
	 *
	 * Empty when any host is allowed, which is what {@see TypeUrl::allowedHosts()} reads as "any
	 * host": a form asking a client for a destination hands it this list and needs no branch of its
	 * own for the wildcard.
	 *
	 * @return list<string>
	 */
	public function hosts(): array
	{
		return $this->allowed ?? [];
	}

	/**
	 * Whether a client may be sent to this target.
	 */
	public function allows(string|Uri $to): bool
	{
		if (null === $this->allowed) {
			return true;
		}

		try {
			$host = $to instanceof Uri ? $to->getHost() : Uri::createFromString($to)->getHost();
		} catch (Throwable) {
			return false;
		}

		return '' === $host || \in_array(\strtolower($host), $this->allowed, true);
	}
}
