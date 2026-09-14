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

/**
 * Class MediaType.
 *
 * A parsed `Content-Type` header value: `text/html; charset="utf-8"` gives the type
 * `text/html` and the params `['charset' => 'utf-8']`.
 */
final class MediaType
{
	/**
	 * MediaType constructor.
	 *
	 * @param string                $type   the lower-cased media type, without params
	 * @param array<string, string> $params the params, keyed by lower-cased name
	 */
	public function __construct(public readonly string $type, public readonly array $params = []) {}

	/**
	 * Parses a `Content-Type` header value, or returns null when it is empty.
	 */
	public static function fromContentType(?string $content_type): ?self
	{
		if (null === $content_type || '' === \trim($content_type)) {
			return null;
		}

		$parts  = \preg_split('~\s*[;,]\s*~', \trim($content_type)) ?: [];
		$type   = \strtolower((string) \array_shift($parts));
		$params = [];

		foreach ($parts as $part) {
			[$name, $value] = \array_pad(\explode('=', $part, 2), 2, '');

			if ('' !== \trim($name)) {
				$params[\strtolower(\trim($name))] = \trim($value, " \t\"");
			}
		}

		return new self($type, $params);
	}

	/**
	 * The type for a structured syntax suffix (RFC 6839): `application/vnd.api+json` gives
	 * `application/json`; null when the type has no suffix.
	 */
	public function suffixType(): ?string
	{
		$pos = \strrpos($this->type, '+');

		return false === $pos ? null : 'application/' . \substr($this->type, $pos + 1);
	}

	/**
	 * The value of a param, or null.
	 */
	public function param(string $name): ?string
	{
		return $this->params[\strtolower($name)] ?? null;
	}
}
