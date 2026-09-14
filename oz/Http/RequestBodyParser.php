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

use JsonException;
use RuntimeException;
use SimpleXMLElement;

/**
 * Class RequestBodyParser.
 *
 * Parses request bodies by media type. JSON, XML and url-encoded bodies are handled out of
 * the box; register a parser for any other type. A type without a parser of its own falls
 * back to its structured syntax suffix (RFC 6839): `application/vnd.api+json` is parsed as
 * JSON unless a parser is registered for that exact type.
 */
final class RequestBodyParser
{
	/**
	 * @var array<string, callable(string):(null|array|object)>
	 */
	private array $parsers = [];

	/**
	 * RequestBodyParser constructor.
	 */
	public function __construct()
	{
		$xml = static function (string $input): ?SimpleXMLElement {
			$backup_errors = \libxml_use_internal_errors(true);
			$result        = \simplexml_load_string($input);
			\libxml_clear_errors();
			\libxml_use_internal_errors($backup_errors);

			return false === $result ? null : $result;
		};

		$this->register('application/json', static function (string $input): ?array {
			try {
				$result = \json_decode($input, true, 512, \JSON_THROW_ON_ERROR);

				return \is_array($result) ? $result : null;
			} catch (JsonException) {
				return null;
			}
		});
		$this->register('application/xml', $xml);
		$this->register('text/xml', $xml);
		$this->register('application/x-www-form-urlencoded', static function (string $input): array {
			\parse_str($input, $data);

			return $data;
		});
	}

	/**
	 * Registers (or replaces) the parser of a media type.
	 *
	 * @param string                               $media_type a media type, without params
	 * @param callable(string):(null|array|object) $parser     receives the raw body
	 */
	public function register(string $media_type, callable $parser): static
	{
		$this->parsers[\strtolower($media_type)] = $parser;

		return $this;
	}

	/**
	 * Whether a body of this media type can be parsed.
	 */
	public function supports(MediaType $media_type): bool
	{
		return null !== $this->parserFor($media_type);
	}

	/**
	 * Parses a body, or returns null when it is empty or no parser handles its media type.
	 *
	 * @throws RuntimeException when a parser returns something else than an array, an object or null
	 */
	public function parse(?MediaType $media_type, string $body): array|object|null
	{
		if (null === $media_type || '' === $body) {
			return null;
		}

		$parser = $this->parserFor($media_type);

		if (null === $parser) {
			return null;
		}

		$parsed = $parser($body);

		if (null !== $parsed && !\is_object($parsed) && !\is_array($parsed)) {
			throw new RuntimeException(\sprintf(
				'The body parser of "%s" must return an array, an object or null.',
				$media_type->type
			));
		}

		return $parsed;
	}

	/**
	 * @return null|callable(string):(null|array|object)
	 */
	private function parserFor(MediaType $media_type): ?callable
	{
		$suffix = $media_type->suffixType();

		return $this->parsers[$media_type->type] ?? (null === $suffix ? null : $this->parsers[$suffix] ?? null);
	}
}
