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

namespace OZONE\Core\App;

use OZONE\Core\Exceptions\RuntimeException;
use Throwable;

/**
 * Class SettingsSchema.
 *
 * Reads the settings files as declarations: every key with its type, its default, its documentation
 * and whether a browser may read it.
 *
 * **Why the files are read as text.** A settings file documents each key with a block comment above
 * it, inside the returned array, so the comment belongs to no declaration and no reflection reaches
 * it. `token_get_all()` is the way in: a doc comment is paired with the array element after it.
 *
 * **Why a value is not always answered.** A default may be an expression -- `1000 * 1000`, a class
 * constant -- which only running the file resolves, and running it resolves `env()` too: on a
 * configured machine that is the project's secret (`OZ_CRON_WEB_KEY`, the MinIO keys). So a key whose
 * declaration reads the environment is answered with its source text and `from_env`, never with the
 * value it holds here.
 *
 * A **group** is either named settings (keys written as string literals, documented) or a map whose
 * keys are expressions -- the authentication methods by scheme, a provider by its `NAME`. Only the
 * first kind is a schema; the second is listed by name and its file is never run.
 */
final class SettingsSchema
{
	/**
	 * The doc comment tag saying a browser may read the key.
	 *
	 * Beside the key it describes, because the reader is already reading that comment and a mark kept
	 * anywhere else is a second list to hold in step.
	 */
	public const string CLIENT_TAG = 'client';

	/** What a value's source text must not call for the value to be answered. */
	private const string ENV_CALL = 'env(';

	/**
	 * The schema of the settings OZone itself declares.
	 *
	 * Its own source directories only: a project's file may feed a key from the environment, and what
	 * a reference documents is what the framework declares, not what one machine resolved.
	 *
	 * @return array{
	 *     settings: list<array{
	 *         group: string,
	 *         key: string,
	 *         type: string,
	 *         default: mixed,
	 *         source: string,
	 *         from_env: bool,
	 *         doc: string,
	 *         client: bool,
	 *     }>,
	 *     maps: list<string>,
	 *     errors: list<array{group: string, reason: string}>,
	 * }
	 */
	public static function describe(): array
	{
		$settings = [];
		$maps     = [];
		$errors   = [];

		foreach (Settings::getSources()->getInternalSources() as $source) {
			foreach (self::groupsIn($source) as $group => $file) {
				$declared = self::readFile($file);

				if ([] === $declared['keys']) {
					if ($declared['dynamic']) {
						$maps[] = $group;
					}

					continue;
				}

				try {
					$values = self::runFile($file);
				} catch (Throwable $error) {
					$errors[] = ['group' => $group, 'reason' => $error->getMessage()];

					continue;
				}

				foreach ($declared['keys'] as $key => $about) {
					$from_env = \str_contains($about['source'], self::ENV_CALL);
					$value    = $values[$key] ?? null;

					$settings[] = [
						'group'    => $group,
						'key'      => $key,
						'type'     => $from_env ? 'unknown' : \get_debug_type($value),
						'default'  => $from_env ? null : $value,
						'source'   => $about['source'],
						'from_env' => $from_env,
						'doc'      => $about['doc'],
						'client'   => \in_array(self::CLIENT_TAG, $about['tags'], true),
					];
				}
			}
		}

		\usort($settings, static fn (array $a, array $b): int => [$a['group'], $a['key']] <=> [$b['group'], $b['key']]);
		\sort($maps);

		return ['settings' => $settings, 'maps' => $maps, 'errors' => $errors];
	}

	/**
	 * The settings a browser may read, with the values this project answers.
	 *
	 * Every source, because a project marks its own keys in its own files, and the value is the
	 * effective one -- the whole point being that a client follows an override instead of holding a
	 * copy of the default.
	 *
	 * A key marked for the client and fed from the environment is an **error** and not an entry: the
	 * environment is where a deployment's secrets live, and one of those marked by accident would
	 * reach every browser. A value a browser is meant to have belongs in the file, written out.
	 *
	 * @return array{
	 *     settings: array<string, array<string, mixed>>,
	 *     errors: list<array{group: string, reason: string}>,
	 * }
	 */
	public static function forClient(): array
	{
		$settings = [];
		$errors   = [];

		foreach (Settings::getSources()->getAllSources() as $source) {
			foreach (self::groupsIn($source) as $group => $file) {
				$declared = self::readFile($file);

				foreach ($declared['keys'] as $key => $about) {
					if (!\in_array(self::CLIENT_TAG, $about['tags'], true)) {
						continue;
					}

					if (\str_contains($about['source'], self::ENV_CALL)) {
						$errors[] = [
							'group'  => $group,
							'reason' => \sprintf(
								'"%s" is marked for the client and read from the environment:'
									. ' write the value in the file instead.',
								$key
							),
						];

						continue;
					}

					$settings[$group][$key] = Settings::get($group, $key);
				}
			}
		}

		\ksort($settings);

		foreach ($settings as &$group_settings) {
			\ksort($group_settings);
		}

		return ['settings' => $settings, 'errors' => $errors];
	}

	/**
	 * The groups a settings directory declares, each with the keys of its values: what adding that
	 * directory as a source adds, read from its files and not from a source being added.
	 *
	 * @return array<string, list<string>>
	 */
	public static function keysIn(string $source): array
	{
		$out = [];

		foreach (self::groupsIn($source) as $group => $file) {
			$out[$group] = \array_map('strval', \array_keys(self::runFile($file)));
		}

		return $out;
	}

	/**
	 * The settings groups of a source directory, by group name.
	 *
	 * The name is the path under the directory without `.php`, which is how a group is resolved
	 * ({@see Settings} on `foo.bar.baz` and `foo/bar.baz`).
	 *
	 * @return array<string, string>
	 */
	private static function groupsIn(string $source): array
	{
		$root  = \rtrim($source, '/\\');
		$found = [];

		foreach (\glob($root . \DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
			$found[\basename($file, '.php')] = $file;
		}

		foreach (\glob($root . \DIRECTORY_SEPARATOR . '*', \GLOB_ONLYDIR) ?: [] as $dir) {
			foreach (self::groupsIn($dir) as $name => $file) {
				$found[\basename($dir) . '/' . $name] = $file;
			}
		}

		\ksort($found);

		return $found;
	}

	/**
	 * Runs a settings file for the values it declares.
	 *
	 * Only a file with keys of its own is run, which is why a map's file never is: those return a
	 * registry's answer, and reading a schema is no reason to build one.
	 *
	 * @return array<string, mixed>
	 */
	private static function runFile(string $file): array
	{
		$values = require $file;

		if (!\is_array($values)) {
			throw new RuntimeException(\sprintf('Settings file "%s" does not return an array.', $file));
		}

		/** @var array<string, mixed> $values */
		return $values;
	}

	/**
	 * What a settings file declares, read from its text.
	 *
	 * Each key written as a string literal, with the doc comment above it, the tags that comment
	 * carries and the source text of its value. `dynamic` says the file has keys that are expressions,
	 * which makes it a map rather than a set of named settings.
	 *
	 * @return array{
	 *     keys: array<string, array{doc: string, tags: list<string>, source: string}>,
	 *     dynamic: bool,
	 * }
	 */
	private static function readFile(string $file): array
	{
		$keys    = [];
		$dynamic = false;

		foreach (self::elementsIn(\token_get_all(\file_get_contents($file) ?: '')) as [$doc, $tokens]) {
			$at = self::arrowIn($tokens);

			if (null === $at) {
				continue;
			}

			$name = self::literalKey(\array_slice($tokens, 0, $at));

			if (null === $name) {
				$dynamic = true;

				continue;
			}

			if (Settings::MERGE_KEY === $name) {
				continue;
			}

			$keys[$name] = self::documentation($doc) + ['source' => self::textOf(\array_slice($tokens, $at + 1))];
		}

		return ['keys' => $keys, 'dynamic' => $dynamic];
	}

	/**
	 * The elements of the array a settings file returns, each with the doc comment above it.
	 *
	 * Split on the commas of the outermost array alone, so a value that is itself an array or a call
	 * stays one element: reading the elements first is what keeps a key from being taken for part of
	 * the value before it.
	 *
	 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
	 *
	 * @return list<array{0: null|string, 1: list<array{0: int, 1: string, 2: int}|string>}>
	 */
	private static function elementsIn(array $tokens): array
	{
		$elements = [];
		$started  = false;
		$stack    = [];
		$doc      = null;
		$current  = [];
		$previous = null;

		foreach ($tokens as $token) {
			if (!$started) {
				$started = \is_array($token) && \T_RETURN === $token[0];

				continue;
			}

			$opens = self::opensWith($token, $previous);
			$depth = \count($stack);

			if (null !== $opens) {
				$stack[] = $opens;

				// The returned array itself is structure; anything nested is part of a value.
				if (0 === $depth && 'array' === $opens) {
					$previous = $token;

					continue;
				}

				$current[] = $token;
				$previous  = $token;

				continue;
			}

			if (self::closes($token)) {
				\array_pop($stack);

				if (1 === $depth) {
					break;
				}

				$current[] = $token;
				$previous  = $token;

				continue;
			}

			if (!\is_array($token) || !\in_array($token[0], [\T_WHITESPACE, \T_COMMENT], true)) {
				$previous = $token;
			}

			if (1 !== $depth) {
				continue;
			}

			if (',' === $token) {
				$elements[] = [$doc, $current];
				$current     = [];
				$doc         = null;

				continue;
			}

			// Before an element starts, a doc comment belongs to it and whitespace to nobody.
			if ([] === $current && \is_array($token)) {
				if (\T_DOC_COMMENT === $token[0]) {
					$doc = $token[1];

					continue;
				}

				if (\in_array($token[0], [\T_WHITESPACE, \T_COMMENT], true)) {
					continue;
				}
			}

			$current[] = $token;
		}

		if ([] !== $current) {
			$elements[] = [$doc, $current];
		}

		return $elements;
	}

	/**
	 * What a token opens, or null when it opens nothing.
	 *
	 * `array` for `[` and for the `(` of `array(`, `call` for any other `(`: the two forms of an
	 * array literal close with different characters, so what opened has to be remembered to know
	 * what a closing one ends.
	 *
	 * @param array{0: int, 1: string, 2: int}|string      $token
	 * @param null|array{0: int, 1: string, 2: int}|string $previous the token before it
	 */
	private static function opensWith(array|string $token, array|string|null $previous): ?string
	{
		if ('[' === $token) {
			return 'array';
		}

		if ('(' !== $token) {
			return null;
		}

		return \is_array($previous) && \T_ARRAY === $previous[0] ? 'array' : 'call';
	}

	/**
	 * Whether a token closes what one of {@see self::opensWith()} opened.
	 *
	 * @param array{0: int, 1: string, 2: int}|string $token
	 */
	private static function closes(array|string $token): bool
	{
		return ']' === $token || ')' === $token;
	}

	/**
	 * The index of the `=>` of one element, at its own level, or null when the element has no key.
	 *
	 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
	 */
	private static function arrowIn(array $tokens): ?int
	{
		$depth  = 0;
		$parens = 0;

		foreach ($tokens as $at => $token) {
			$depth += ('[' === $token ? 1 : 0) - (']' === $token ? 1 : 0);
			$parens += ('(' === $token ? 1 : 0) - (')' === $token ? 1 : 0);

			if (0 === $depth && 0 === $parens && \is_array($token) && \T_DOUBLE_ARROW === $token[0]) {
				return $at;
			}
		}

		return null;
	}

	/**
	 * The name of a key written as one string literal, or null when it is an expression.
	 *
	 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
	 */
	private static function literalKey(array $tokens): ?string
	{
		$found = null;

		foreach ($tokens as $token) {
			if (\is_array($token) && \in_array($token[0], [\T_WHITESPACE, \T_COMMENT], true)) {
				continue;
			}

			if (null !== $found || !\is_array($token) || \T_CONSTANT_ENCAPSED_STRING !== $token[0]) {
				return null;
			}

			$found = \trim($token[1], '\'"');
		}

		return $found;
	}

	/**
	 * The source text of a run of tokens, as it was written.
	 *
	 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
	 */
	private static function textOf(array $tokens): string
	{
		$text = '';

		foreach ($tokens as $token) {
			$text .= \is_array($token) ? $token[1] : $token;
		}

		return \trim($text);
	}

	/**
	 * A doc comment read as its text and its tags: `@client` says a browser may read the key.
	 *
	 * @return array{doc: string, tags: list<string>}
	 */
	private static function documentation(?string $comment): array
	{
		if (null === $comment) {
			return ['doc' => '', 'tags' => []];
		}

		$lines = [];
		$tags  = [];

		foreach (\explode("\n", $comment) as $line) {
			$line = \trim($line);
			$line = \trim(\preg_replace('~^(/\*\*|\*/|\*)~', '', $line) ?? $line);

			if (\str_starts_with($line, '@')) {
				$tags[] = \strtolower(\explode(' ', \substr($line, 1), 2)[0]);

				continue;
			}

			if ('' !== $line) {
				$lines[] = $line;
			}
		}

		return ['doc' => \implode(' ', $lines), 'tags' => $tags];
	}
}
