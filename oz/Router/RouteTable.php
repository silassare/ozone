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

namespace OZONE\Core\Router;

use OZONE\Core\FS\FilesManager;
use Throwable;

/**
 * What a router needs to route a request without registering every route provider: for each
 * route, its methods, its path or pattern, where it stands in priority order, and the provider that
 * maps it (with its position among that provider's routes).
 *
 * Compiled from a router that registered every provider ({@see Router::compileTable()}), it lets a
 * router of the same code and settings register only the provider of the route a request matched
 * ({@see Router::registerProviders()}). It describes the code it was compiled from and nothing else:
 * whoever keeps it must key it by that code, and a router drops a table that disagrees with the
 * routes the code maps.
 *
 * @internal
 */
final class RouteTable
{
	/**
	 * The layout of {@see toArray()}: a file of another layout is ignored.
	 */
	public const FORMAT = 1;

	/**
	 * @param array{
	 *     format: int,
	 *     eager: list<string>,
	 *     static: array<string, list<array{0: array<string, int>, 1: string, 2: int}>>,
	 *     dynamic: list<array{0: string, 1: array<string, int>, 2: string, 3: int}>,
	 *     names: array<string, array{0: string, 1: int}>
	 * } $data
	 * @param null|string $file the file the table was loaded from
	 */
	public function __construct(private readonly array $data, private readonly ?string $file = null) {}

	/**
	 * Loads a table saved by {@see save()}: null when there is none, or it has another layout.
	 */
	public static function load(string $file): ?self
	{
		if (!\is_file($file)) {
			return null;
		}

		try {
			$data = include $file;
		} catch (Throwable) {
			return null;
		}

		if (!\is_array($data) || self::FORMAT !== ($data['format'] ?? null)) {
			return null;
		}

		return new self($data, $file);
	}

	/**
	 * Saves this table as a PHP file returning its data, which opcache keeps in shared memory.
	 *
	 * Written atomically: a concurrent request reads the whole table or none.
	 *
	 * @return bool false when the file could not be written
	 */
	public function save(string $file): bool
	{
		$dir = \dirname($file);

		try {
			if (!\is_dir($dir) && !\mkdir($dir, 0775, true) && !\is_dir($dir)) {
				return false;
			}

			$code = '<?php' . \PHP_EOL . \PHP_EOL
				. '// Compiled by OZone from its route providers: see OZONE\Core\Router\RouteTable.'
				. \PHP_EOL . \PHP_EOL . 'return ' . \var_export($this->data, true) . ';' . \PHP_EOL;

			(new FilesManager($dir))->writeAtomic(\basename($file), $code);
		} catch (Throwable) {
			return false;
		}

		return true;
	}

	/**
	 * Deletes the file this table was loaded from: it no longer describes the routes.
	 */
	public function discard(): void
	{
		if (null !== $this->file && \is_file($this->file)) {
			@\unlink($this->file);
		}
	}

	/**
	 * The route providers that do more than map routes, which a router registers whenever it is
	 * created: adding a global parameter changes what every route matches.
	 *
	 * @return list<string>
	 */
	public function getEagerProviders(): array
	{
		return $this->data['eager'];
	}

	/**
	 * Matches a request the way {@see Router::find()} does, static routes first, each list in
	 * priority order: the status and, when found, the route's source, its position there, and
	 * whether it is dynamic.
	 *
	 * @param string $method the request method, upper case
	 * @param string $path   the request path
	 *
	 * @return array{0: RouteSearchStatus, 1: null|array{0: string, 1: int, 2: bool}}
	 */
	public function match(string $method, string $path): array
	{
		$matched = false;

		foreach ($this->data['static'][$path] ?? [] as [$methods, $source, $ordinal]) {
			$matched = true;

			if (isset($methods[$method])) {
				return [RouteSearchStatus::FOUND, [$source, $ordinal, false]];
			}
		}

		foreach ($this->data['dynamic'] as [$regexp, $methods, $source, $ordinal]) {
			if (1 === \preg_match($regexp, $path)) {
				$matched = true;

				if (isset($methods[$method])) {
					return [RouteSearchStatus::FOUND, [$source, $ordinal, true]];
				}
			}
		}

		return [$matched ? RouteSearchStatus::METHOD_NOT_ALLOWED : RouteSearchStatus::NOT_FOUND, null];
	}

	/**
	 * The source and position of the route {@see Router::getRoute()} returns for a name.
	 *
	 * @return null|array{0: string, 1: int}
	 */
	public function lookup(string $name): ?array
	{
		return $this->data['names'][$name] ?? null;
	}

	/**
	 * The table's data, as {@see save()} writes it.
	 */
	public function toArray(): array
	{
		return $this->data;
	}
}
