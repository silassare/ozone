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

namespace OZONE\Core\Lang\Message;

/**
 * Parses a text of a catalog into the nodes {@see MessageRenderer} writes.
 *
 * The syntax, which a client of the API reads the same way:
 *
 * - `{name}`, `{a.b}`: a value of the data; `{name | f | g: 2, "x"}` through filters, whose
 *   arguments are numbers or double-quoted strings;
 * - `{{KEY}}`: the text of another key, filled with the same data;
 * - `{n, plural, =0 {none} >9 {many} one {# item} other {# items}}`, `{n, selectordinal, ...}` and
 *   `{v, select, a {...} other {...}}`: a choice between branches, `other` required, `#` the number
 *   inside a plural branch;
 * - `\{`, `\}`, `\\` and, inside a plural branch, `\#` write the character.
 *
 * Parsed once and kept per text: the text of a key does not change while a process runs.
 */
final class MessageParser
{
	private const KINDS = ['plural', 'selectordinal', 'select'];

	private const KEY_REG = '~^[A-Z][A-Z0-9_.]+$~';

	private const SEGMENT_REG = '~^\w+$~';

	private const NUMBER_REG = '~^-?\d+(\.\d+)?$~';

	/** @var array<string, list<MessageNode>> */
	private static array $cache = [];

	private int $at = 0;

	private readonly int $length;

	private function __construct(private readonly string $text)
	{
		$this->length = \strlen($text);
	}

	/**
	 * The nodes of a text.
	 *
	 * @return list<MessageNode>
	 *
	 * @throws MessageSyntaxException
	 */
	public static function parse(string $text): array
	{
		if (isset(self::$cache[$text])) {
			return self::$cache[$text];
		}

		$parser = new self($text);
		$nodes  = $parser->message(false, false);

		if ($parser->at < $parser->length) {
			throw $parser->error('Unexpected "}"');
		}

		return self::$cache[$text] = $nodes;
	}

	/**
	 * A message up to the end of the text, or up to the `}` closing a branch.
	 *
	 * @return list<MessageNode>
	 */
	private function message(bool $in_branch, bool $in_plural): array
	{
		$nodes = [];
		$text  = '';

		while ($this->at < $this->length) {
			$char = $this->text[$this->at];

			if ('\\' === $char) {
				$next = $this->text[$this->at + 1] ?? '';

				if ('{' === $next || '}' === $next || '\\' === $next || ('#' === $next && $in_plural)) {
					$text .= $next;
					$this->at += 2;
				} else {
					$text .= $char;
					++$this->at;
				}

				continue;
			}

			if ('}' === $char) {
				if ($in_branch) {
					break;
				}

				throw $this->error('Unexpected "}"');
			}

			if ('#' === $char && $in_plural) {
				self::flush($nodes, $text);
				$nodes[] = MessageNode::hash();
				++$this->at;

				continue;
			}

			if ('{' === $char) {
				self::flush($nodes, $text);
				$nodes[] = '{' === ($this->text[$this->at + 1] ?? '') ? $this->include() : $this->argument();

				continue;
			}

			$text .= $char;
			++$this->at;
		}

		self::flush($nodes, $text);

		return $nodes;
	}

	/**
	 * `{{KEY}}`.
	 */
	private function include(): MessageNode
	{
		$start    = $this->at;
		$this->at += 2;
		$key      = \trim($this->until('}'));

		if (!\preg_match(self::KEY_REG, $key)) {
			throw $this->error('Expected a key to include', $start);
		}

		if ('}}' !== \substr($this->text, $this->at, 2)) {
			throw $this->error('Expected "}}"');
		}

		$this->at += 2;

		return MessageNode::include($key);
	}

	/**
	 * `{path}`, `{path | filters}` or `{path, kind, branches}`.
	 */
	private function argument(): MessageNode
	{
		++$this->at;
		$path = $this->path();

		$this->spaces();

		$char = $this->text[$this->at] ?? '';

		if ('}' === $char) {
			++$this->at;

			return MessageNode::argument($path, []);
		}

		if ('|' === $char) {
			$filters = $this->filters();

			return MessageNode::argument($path, $filters);
		}

		if (',' === $char) {
			return $this->choice($path);
		}

		throw $this->error('Expected "}", "|" or ","');
	}

	/**
	 * @return list<string>
	 */
	private function path(): array
	{
		$this->spaces();

		$start    = $this->at;
		$raw      = $this->until('}', '|', ',', ' ', "\t", "\n", "\r");
		$segments = \explode('.', $raw);

		foreach ($segments as $segment) {
			if (!\preg_match(self::SEGMENT_REG, $segment)) {
				throw $this->error('Expected a name', $start);
			}
		}

		return $segments;
	}

	/**
	 * `| name`, `| name: arg, arg`, repeated, then `}`.
	 *
	 * @return list<array{name: string, args: list<float|int|string>}>
	 */
	private function filters(): array
	{
		$filters = [];

		while ('|' === ($this->text[$this->at] ?? '')) {
			++$this->at;
			$this->spaces();

			$start = $this->at;
			$name  = $this->until(':', '|', '}', ' ', "\t", "\n", "\r");

			if (!\preg_match('~^[a-zA-Z_]\w*$~', $name)) {
				throw $this->error('Expected a filter name', $start);
			}

			$this->spaces();

			$args = [];

			if (':' === ($this->text[$this->at] ?? '')) {
				while (true) {
					++$this->at;
					$this->spaces();
					$args[] = $this->literal();
					$this->spaces();

					if (',' !== ($this->text[$this->at] ?? '')) {
						break;
					}
				}
			}

			$filters[] = ['name' => $name, 'args' => $args];
		}

		if ('}' !== ($this->text[$this->at] ?? '')) {
			throw $this->error('Expected "|" or "}"');
		}

		++$this->at;

		return $filters;
	}

	/**
	 * A filter argument: a number, or a string in double quotes (`\"` and `\\` escaped).
	 */
	private function literal(): float|int|string
	{
		$start = $this->at;

		if ('"' === ($this->text[$this->at] ?? '')) {
			++$this->at;
			$out = '';

			while ($this->at < $this->length && '"' !== $this->text[$this->at]) {
				$char = $this->text[$this->at];

				$next = $this->text[$this->at + 1] ?? '';

				if ('\\' === $char && ('"' === $next || '\\' === $next)) {
					$char = $this->text[++$this->at];
				}

				$out .= $char;
				++$this->at;
			}

			if ($this->at >= $this->length) {
				throw $this->error('Unterminated string', $start);
			}

			++$this->at;

			return $out;
		}

		$raw = $this->until(',', '|', '}', ' ', "\t", "\n", "\r");

		if (!\preg_match(self::NUMBER_REG, $raw)) {
			throw $this->error('Expected a number or a string', $start);
		}

		return \str_contains($raw, '.') ? (float) $raw : (int) $raw;
	}

	/**
	 * `, kind, selector {text} selector {text} ... }`.
	 *
	 * @param list<string> $path
	 */
	private function choice(array $path): MessageNode
	{
		++$this->at;
		$this->spaces();

		$start = $this->at;
		$kind  = $this->until(',', '}', ' ', "\t", "\n", "\r");

		if (!\in_array($kind, self::KINDS, true)) {
			throw $this->error('Expected "plural", "selectordinal" or "select"', $start);
		}

		$this->spaces();

		if (',' !== ($this->text[$this->at] ?? '')) {
			throw $this->error('Expected ","');
		}

		++$this->at;

		$branches  = [];
		$has_other = false;

		while (true) {
			$this->spaces();

			$char = $this->text[$this->at] ?? '';

			if ('}' === $char) {
				++$this->at;

				break;
			}

			if ('' === $char) {
				throw $this->error('Unterminated choice');
			}

			$start    = $this->at;
			$selector = $this->until('{', ' ', "\t", "\n", "\r", '}');

			if (!self::isSelector($selector, $kind)) {
				throw $this->error(\sprintf('Invalid selector "%s"', $selector), $start);
			}

			$this->spaces();

			if ('{' !== ($this->text[$this->at] ?? '')) {
				throw $this->error('Expected "{"');
			}

			++$this->at;
			$nodes = $this->message(true, 'select' !== $kind);

			if ('}' !== ($this->text[$this->at] ?? '')) {
				throw $this->error('Unterminated branch');
			}

			++$this->at;

			$has_other  = $has_other || 'other' === $selector;
			$branches[] = ['selector' => $selector, 'nodes' => $nodes];
		}

		if (!$has_other) {
			throw $this->error('A choice needs an "other" branch');
		}

		return MessageNode::choice($path, $kind, $branches);
	}

	private static function isSelector(string $selector, string $kind): bool
	{
		if ('select' === $kind) {
			return (bool) \preg_match('~^[\w-]+$~', $selector);
		}

		if (\in_array($selector, ['zero', 'one', 'two', 'few', 'many', 'other'], true)) {
			return true;
		}

		return (bool) \preg_match('~^(=|>=|<=|>|<)-?\d+(\.\d+)?$~', $selector);
	}

	/** Moves up to the first of the given characters, answering what it passed over. */
	private function until(string ...$stops): string
	{
		$start = $this->at;

		while ($this->at < $this->length && !\in_array($this->text[$this->at], $stops, true)) {
			++$this->at;
		}

		return \substr($this->text, $start, $this->at - $start);
	}

	private function spaces(): void
	{
		while ($this->at < $this->length && \in_array($this->text[$this->at], [' ', "\t", "\n", "\r"], true)) {
			++$this->at;
		}
	}

	/**
	 * @param list<MessageNode> $nodes
	 */
	private static function flush(array &$nodes, string &$text): void
	{
		if ('' !== $text) {
			$nodes[] = MessageNode::text($text);
			$text    = '';
		}
	}

	private function error(string $reason, ?int $at = null): MessageSyntaxException
	{
		return new MessageSyntaxException($reason, $this->text, $at ?? $this->at);
	}
}
