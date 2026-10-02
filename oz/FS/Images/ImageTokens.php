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

namespace OZONE\Core\FS\Images;

use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\Images\Interfaces\ImageTokenInterface;

/**
 * The image tokens a project adds: registered in code, or named by class in `oz.files`
 * `OZ_IMAGE_TOKENS`. A process-wide list, as the code that declares them.
 */
final class ImageTokens
{
	/** @var list<ImageTokenInterface> */
	private static array $tokens = [];

	private static bool $loaded = false;

	public static function register(ImageTokenInterface $token): void
	{
		self::$tokens[] = $token;
	}

	/**
	 * The canonical form of a token one of them knows, with the one that knows it.
	 *
	 * @return null|array{string, ImageTokenInterface}
	 */
	public static function canonical(string $token): ?array
	{
		foreach (self::all() as $handler) {
			$canonical = $handler->canonical($token);

			if (null !== $canonical) {
				if (!\preg_match('~^[a-z0-9]+$~', $canonical)) {
					throw new RuntimeException(\sprintf(
						'The image token class "%s" gave "%s", which a URL cannot carry ([a-z0-9]+).',
						$handler::class,
						$canonical
					));
				}

				return [$canonical, $handler];
			}
		}

		return null;
	}

	/**
	 * The one that knows a canonical token, if any.
	 */
	public static function handlerOf(string $token): ?ImageTokenInterface
	{
		return self::canonical($token)[1] ?? null;
	}

	/**
	 * Forgets the registered tokens: for tests.
	 */
	public static function reset(): void
	{
		self::$tokens = [];
		self::$loaded = false;
	}

	/**
	 * @return list<ImageTokenInterface>
	 */
	private static function all(): array
	{
		if (!self::$loaded) {
			self::$loaded = true;

			foreach ((array) Settings::get('oz.files', 'OZ_IMAGE_TOKENS', []) as $class) {
				if (\is_string($class) && \is_subclass_of($class, ImageTokenInterface::class)) {
					self::$tokens[] = new $class();
				}
			}
		}

		return self::$tokens;
	}
}
