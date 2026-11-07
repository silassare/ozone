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

use OZONE\Core\App\Settings;

/**
 * Class ContentSecurityPolicy.
 *
 * What a response tells the browser it may load, from the `OZ_CONTENT_SECURITY_POLICY` setting:
 * a directive name mapped to the sources it allows.
 *
 * **Declared as directives and not as the header string it becomes**, because that is what makes
 * it layerable. A settings map merges key by key, so a plugin, a scope or a project tightens
 * `img-src` alone and keeps every other directive the sources before it declared; one string can
 * only be restated whole, and restating it is how a directive gets dropped by accident. A source
 * that would rather add to the lists than replace them says so with `Settings::MERGE_APPEND`.
 *
 * An empty policy sends no header, which is what an API serving no HTML wants.
 *
 * What one page needs beyond the policy goes through {@see self::with()} rather than a second
 * setting, and that method is the reason this is a class and not an `implode()`: a directive
 * nobody wrote is answered by the one it falls back to, so a grant has to start from the sources
 * that answer today or it hands the page something narrower than the policy it was composed from.
 */
final class ContentSecurityPolicy
{
	/**
	 * The header a policy is sent as.
	 */
	public const string HEADER = 'Content-Security-Policy';

	/**
	 * The source that allows nothing, and the one source that cannot stand beside another.
	 */
	public const string NONE = "'none'";

	/**
	 * A policy fit for a page made of this project's own files, for a project to start from:
	 * `'OZ_CONTENT_SECURITY_POLICY' => ContentSecurityPolicy::STRICT` in its own settings, then
	 * widen the directives for the origins it really loads.
	 *
	 * **Not what ships**, and that is the one judgement in this class a reader should know about.
	 * Which origins a page may load is a fact about a project and not about a framework, so a
	 * policy sent by default would be broken for every project that loads a font, a map, a
	 * payment widget or an analytics script, and a default that breaks a working page is a
	 * default projects switch off rather than tighten. Shipped empty, a project that writes one
	 * line gets this, and one that writes none is where it already was.
	 *
	 * Why `style-src` allows what `script-src` does not: every HTML page OZone itself serves
	 * inlines its stylesheet into the layout, and a style attribute cannot carry a nonce. Script
	 * is what an injected value needs in order to run, and it stays on `'self'`, with neither
	 * `'unsafe-inline'` nor `'unsafe-eval'`.
	 */
	public const array STRICT = [
		'default-src'     => ["'self'"],
		'script-src'      => ["'self'"],
		'style-src'       => ["'self'", "'unsafe-inline'"],
		'img-src'         => ["'self'", 'data:'],
		'font-src'        => ["'self'"],
		'connect-src'     => ["'self'"],
		'object-src'      => ["'none'"],
		'base-uri'        => ["'self'"],
		'form-action'     => ["'self'"],
		'frame-ancestors' => ["'self'"],
	];

	/**
	 * Which directive answers for one that was not written, as CSP level 3 defines it.
	 *
	 * A chain and not a map to `default-src`: `style-src-attr` is answered by `style-src`, which
	 * is answered by `default-src`, and `worker-src` by `child-src` before `default-src`.
	 *
	 * A directive absent from this list answers for itself only, which is why there is no
	 * entry for `base-uri`, `form-action` or `frame-ancestors`: a policy that does not write
	 * them does not restrict them.
	 */
	public const array FALLBACK = [
		'child-src'       => 'default-src',
		'connect-src'     => 'default-src',
		'font-src'        => 'default-src',
		'frame-src'       => 'child-src',
		'img-src'         => 'default-src',
		'manifest-src'    => 'default-src',
		'media-src'       => 'default-src',
		'object-src'      => 'default-src',
		'script-src'      => 'default-src',
		'script-src-attr' => 'script-src',
		'script-src-elem' => 'script-src',
		'style-src'       => 'default-src',
		'style-src-attr'  => 'style-src',
		'style-src-elem'  => 'style-src',
		'worker-src'      => 'child-src',
	];

	/**
	 * ContentSecurityPolicy constructor.
	 *
	 * @param array<string, list<string>> $directives lowercased names, in the order they are sent
	 */
	private function __construct(private readonly array $directives) {}

	/**
	 * Builds the policy of this project.
	 */
	public static function fromSettings(): self
	{
		return self::fromDirectives((array) Settings::get('oz.request', 'OZ_CONTENT_SECURITY_POLICY', []));
	}

	/**
	 * Builds a policy from an `OZ_CONTENT_SECURITY_POLICY` value.
	 *
	 * A name is lowercased, a directive name being ASCII case-insensitive, and a name written
	 * twice keeps the first, as a browser does with a header that repeats a directive.
	 *
	 * @param array<array-key, mixed> $directives
	 */
	public static function fromDirectives(array $directives): self
	{
		$clean = [];

		foreach ($directives as $name => $sources) {
			$name = \strtolower(\trim((string) $name));

			if ('' === $name || isset($clean[$name])) {
				continue;
			}

			$clean[$name] = self::readSources($sources);
		}

		return new self($clean);
	}

	/**
	 * Builds a policy from a header value, so a policy written out can be read back.
	 */
	public static function fromHeader(string $header): self
	{
		$directives = [];

		foreach (\explode(';', $header) as $part) {
			$tokens = \preg_split('~\s+~', \trim($part), -1, \PREG_SPLIT_NO_EMPTY);

			if (!$tokens) {
				continue;
			}

			$name = \strtolower((string) \array_shift($tokens));

			if (!isset($directives[$name])) {
				$directives[$name] = $tokens;
			}
		}

		return self::fromDirectives($directives);
	}

	/**
	 * Whether there is nothing to send.
	 */
	public function isEmpty(): bool
	{
		return [] === $this->directives;
	}

	/**
	 * The directives as written, in the order they are sent.
	 *
	 * @return array<string, list<string>>
	 */
	public function directives(): array
	{
		return $this->directives;
	}

	/**
	 * The sources a directive allows: its own, or those of the directive that answers for it.
	 *
	 * Null when nothing answers for it, which is a policy that does not restrict it at all.
	 *
	 * @return null|list<string>
	 */
	public function sourcesOf(string $directive): ?array
	{
		$name = \strtolower($directive);

		while (true) {
			if (isset($this->directives[$name])) {
				return $this->directives[$name];
			}

			$next = self::FALLBACK[$name] ?? null;

			if (null === $next) {
				return null;
			}

			$name = $next;
		}
	}

	/**
	 * Whether a directive allows a source, as written or through the one that answers for it.
	 *
	 * A directive nothing answers for allows everything, so it allows this too.
	 */
	public function allows(string $directive, string $source): bool
	{
		$sources = $this->sourcesOf($directive);

		return null === $sources || \in_array($source, $sources, true);
	}

	/**
	 * The same policy with these sources added to one directive.
	 *
	 * The directive is written out when only another answered for it, so what it already allowed
	 * is kept: granted `'unsafe-inline'` for styles, a policy of `default-src 'self'` sends
	 * `style-src 'self' 'unsafe-inline'`, where writing the grant on its own would have taken
	 * `'self'` away from every stylesheet the page loads. `'none'` makes way for a real source,
	 * a list holding both being one a browser refuses to read.
	 *
	 * **A directive nothing answers for is left alone**, and that is not an oversight: a policy
	 * that does not restrict it cannot be loosened, so writing the grant there would be the one
	 * way a grant ends up restricting a page.
	 */
	public function with(string $directive, string ...$sources): self
	{
		$name    = \strtolower($directive);
		$current = $this->sourcesOf($name);

		if (null === $current) {
			return $this;
		}

		$merged = $current;

		foreach ($sources as $source) {
			$source = \trim($source);

			if ('' !== $source && !\in_array($source, $merged, true)) {
				$merged[] = $source;
			}
		}

		if ($merged === $current && isset($this->directives[$name])) {
			return $this;
		}

		if (\count($merged) > 1) {
			$merged = \array_values(\array_filter(
				$merged,
				static fn (string $source): bool => self::NONE !== $source
			));
		}

		$directives = $this->directives;
		// Writing to a name that is already there keeps the position it was first declared at, so
		// a grant does not move a directive to the end of the header it was in the middle of.
		$directives[$name] = $merged;

		return new self($directives);
	}

	/**
	 * The header value.
	 */
	public function header(): string
	{
		$parts = [];

		foreach ($this->directives as $name => $sources) {
			// A directive with no source is sent as its name alone, which is what
			// `upgrade-insecure-requests` is.
			$parts[] = [] === $sources ? $name : $name . ' ' . \implode(' ', $sources);
		}

		return \implode('; ', $parts);
	}

	/**
	 * The sources a directive was declared with.
	 *
	 * `true` and `null` are a directive sent without a source, so that
	 * `upgrade-insecure-requests` can be written as the flag it reads as.
	 *
	 * @return list<string>
	 */
	private static function readSources(mixed $value): array
	{
		if (true === $value || null === $value) {
			return [];
		}

		$sources = [];

		/** @var mixed $source */
		foreach (\is_array($value) ? $value : [$value] as $source) {
			if (!\is_scalar($source)) {
				continue;
			}

			$source = \trim((string) $source);

			if ('' !== $source && !\in_array($source, $sources, true)) {
				$sources[] = $source;
			}
		}

		return $sources;
	}
}
