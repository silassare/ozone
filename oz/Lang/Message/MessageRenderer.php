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

use NumberFormatter;
use OZONE\Core\Exceptions\RuntimeException;
use Stringable;

/**
 * Writes the nodes of a parsed message ({@see MessageParser}) with the given data, in one pass: a
 * value is written as it is and never read for placeholders.
 *
 * A value is written as PHP's `(string)` writes a scalar (`true` as `1`, `false` and `null` as
 * nothing); an array or an object without `__toString()` writes nothing. A client of the API writes
 * values the same way.
 */
final class MessageRenderer
{
	/** The filters every side has. A project's own, declared with the same name, is used instead. */
	public const BUILTIN_FILTERS = ['upper', 'lower', 'capitalize', 'number'];

	/** @var array<string, true> The keys being included, to refuse one including itself. */
	private array $including = [];

	/**
	 * @param string                                                    $lang     the language written in
	 * @param callable(string):list<MessageNode>                        $include  the nodes of another key
	 * @param array<string, callable>                                   $filters  the project's own filters,
	 *                                                                            called with the value,
	 *                                                                            the language, then the
	 *                                                                            arguments
	 * @param null|callable(float|int, string, bool):?string            $category the plural category of a
	 *                                                                            number, null when there
	 *                                                                            are no rules
	 */
	public function __construct(
		private readonly string $lang,
		private readonly mixed $include,
		private readonly array $filters,
		private readonly mixed $category,
	) {}

	/**
	 * @param list<MessageNode>    $nodes
	 * @param array<string, mixed> $data
	 */
	public function render(array $nodes, array $data): string
	{
		return $this->write($nodes, $data, null);
	}

	/**
	 * @param list<MessageNode>    $nodes
	 * @param array<string, mixed> $data
	 * @param null|bool|float|int|string $hash what `#` writes, inside a plural branch
	 */
	private function write(array $nodes, array $data, bool|float|int|string|null $hash): string
	{
		$out = '';

		foreach ($nodes as $node) {
			$out .= match ($node->type) {
				'text'     => $node->value,
				'hash'     => self::stringOf($hash),
				'include'  => $this->included($node->value, $data),
				'argument' => $this->argument($node, $data),
				'choice'   => $this->choice($node, $data),
			};
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function included(string $key, array $data): string
	{
		if (isset($this->including[$key])) {
			throw new RuntimeException(\sprintf('Possible infinite loop in lang key: %s.', $key));
		}

		$this->including[$key] = true;

		try {
			return $this->write(($this->include)($key), $data, null);
		} finally {
			unset($this->including[$key]);
		}
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function argument(MessageNode $node, array $data): string
	{
		$value = self::valueAt($data, $node->path);

		foreach ($node->filters as $filter) {
			$value = $this->filter($filter['name'], $value, $filter['args']);
		}

		return self::stringOf($value);
	}

	/**
	 * @param list<float|int|string> $args
	 */
	private function filter(string $name, mixed $value, array $args): mixed
	{
		$own = $this->filters[$name] ?? null;

		if (null !== $own) {
			return $own($value, $this->lang, ...$args);
		}

		return match ($name) {
			'upper'      => \mb_strtoupper(self::stringOf($value)),
			'lower'      => \mb_strtolower(self::stringOf($value)),
			'capitalize' => self::capitalize(self::stringOf($value)),
			'number'     => $this->number($value, $args[0] ?? null),
			default      => throw new RuntimeException(\sprintf('Undefined translation filter: %s', $name)),
		};
	}

	/**
	 * A number for the language (`number: 2` for two decimals); with no ext-intl, with `.` and no
	 * grouping. What is not a number is written as it is.
	 */
	private function number(mixed $value, float|int|string|null $decimals): mixed
	{
		$n = self::numberOf($value);

		if (null === $n) {
			return $value;
		}

		$digits = null === $decimals ? null : (int) $decimals;

		if (\extension_loaded('intl')) {
			$formatter = new NumberFormatter($this->lang, NumberFormatter::DECIMAL);

			if (null !== $digits) {
				$formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $digits);
				$formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $digits);
			}

			$formatted = $formatter->format($n);

			return false === $formatted ? $value : $formatted;
		}

		return null === $digits ? $n : \number_format($n, $digits, '.', '');
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function choice(MessageNode $node, array $data): string
	{
		$value = self::valueAt($data, $node->path);

		if ('select' === $node->kind) {
			$text = self::stringOf($value);

			foreach ($node->branches as $branch) {
				if ('other' !== $branch['selector'] && $branch['selector'] === $text) {
					return $this->write($branch['nodes'], $data, null);
				}
			}

			return $this->write($this->other($node), $data, null);
		}

		$n    = self::numberOf($value);
		$hash = \is_scalar($value) ? $value : null;

		if (null !== $n) {
			$category = null === $this->category
				? null
				: ($this->category)($n, $this->lang, 'selectordinal' === $node->kind);

			foreach ($node->branches as $branch) {
				if ('other' !== $branch['selector'] && self::matches($branch['selector'], $n, $category)) {
					return $this->write($branch['nodes'], $data, $hash);
				}
			}
		}

		return $this->write($this->other($node), $data, $hash);
	}

	/**
	 * @return list<MessageNode>
	 */
	private function other(MessageNode $node): array
	{
		foreach ($node->branches as $branch) {
			if ('other' === $branch['selector']) {
				return $branch['nodes'];
			}
		}

		return [];
	}

	private static function matches(string $selector, float|int $n, ?string $category): bool
	{
		if (\preg_match('~^(=|>=|<=|>|<)(-?\d+(?:\.\d+)?)$~', $selector, $m)) {
			$bound = (float) $m[2];

			// Compared as floats, so that an int and a float that are equal match (the fixer turns a
			// loose `==` into `===`, which would tell `1` from `1.0`).
			return match ($m[1]) {
				'='  => (float) $n === $bound,
				'>'  => $n > $bound,
				'>=' => $n >= $bound,
				'<'  => $n < $bound,
				'<=' => $n <= $bound,
			};
		}

		// A CLDR category, which only the language's rules can answer.
		return null !== $category && $selector === $category;
	}

	/**
	 * The value at a path of the data; null wherever it stops.
	 *
	 * @param array<string, mixed> $data
	 * @param list<string>         $path
	 */
	private static function valueAt(array $data, array $path): mixed
	{
		$at = $data;

		foreach ($path as $segment) {
			if (!\is_array($at) || !\array_key_exists($segment, $at)) {
				return null;
			}

			$at = $at[$segment];
		}

		return $at;
	}

	/** A number, from a number or the text of one; null otherwise. */
	private static function numberOf(mixed $value): float|int|null
	{
		if (\is_int($value) || \is_float($value)) {
			return $value;
		}

		if (\is_string($value) && \is_numeric($value)) {
			return +$value;
		}

		return null;
	}

	private static function stringOf(mixed $value): string
	{
		if (\is_scalar($value) || $value instanceof Stringable) {
			return (string) $value;
		}

		return '';
	}

	private static function capitalize(string $text): string
	{
		return \mb_strtoupper(\mb_substr($text, 0, 1)) . \mb_substr($text, 1);
	}
}
