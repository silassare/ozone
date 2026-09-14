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

namespace OZONE\Core\FS;

use InvalidArgumentException;
use JsonException;
use Override;
use OZONE\Core\App\GarbageCollector;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Utils\Random;
use RuntimeException;
use Throwable;

/**
 * Class TempFS.
 *
 * Create a temporary directory in which files and directories can be created.
 * The created directory has an expiration date for automatic deletion.
 */
final class TempFS implements BootHookReceiverInterface
{
	/**
	 * TempFS constructor.
	 *
	 * @param string $ref
	 */
	private function __construct(private readonly string $ref) {}

	/**
	 * Gets the ref.
	 *
	 * @return string
	 */
	public function getRef(): string
	{
		return $this->ref;
	}

	/**
	 * Gets the temp directory.
	 *
	 * @return FilesManager
	 */
	public function dir(): FilesManager
	{
		return self::root()->cd($this->ref, true);
	}

	/**
	 * Sets the temp directory expiration time.
	 *
	 * @param int $lifetime
	 */
	public function setLifetime(int $lifetime): static
	{
		$expires = \time() + $lifetime;

		/** @noinspection JsonEncodingApiUsageInspection */
		$content = \json_encode(['expires' => $expires]);

		// Atomic: a concurrent reader that caught this file truncated would fail to decode it and
		// treat a live ref as expired, and data/ may be on a volume shared between instances.
		$this->dir()->writeAtomic('./info.json', (string) $content);

		return $this;
	}

	/**
	 * Checks if a ref is valid and not expired.
	 *
	 * @param string $ref
	 *
	 * @return bool
	 */
	public static function canUse(string $ref): bool
	{
		if (!self::isValidSegment($ref)) {
			return false;
		}

		$expire = self::getExpirationTime(self::path($ref));

		return $expire && $expire > \time();
	}

	/**
	 * Gets a new instance.
	 *
	 * @param int    $lifetime the lifetime in seconds
	 * @param string $prefix   the prefix
	 */
	public static function get(int $lifetime, string $prefix = ''): static
	{
		$prefix = \trim($prefix);
		$ref    = ($prefix ? $prefix . '-' : '') . \date('Y-m-d') . '-' . Random::alpha(8);

		return (new self($ref))->setLifetime($lifetime);
	}

	/**
	 * Use a existing instance.
	 *
	 * @param string   $ref      the ref
	 * @param null|int $lifetime the lifetime in seconds
	 *
	 * @return static
	 */
	public static function use(string $ref, ?int $lifetime = null): static
	{
		$has = self::canUse($ref);

		if (!$has) {
			throw new RuntimeException(\sprintf('%s: invalid or expired ref "%s".', self::class, $ref));
		}

		$instance = new self($ref);

		if ($lifetime) {
			$instance->setLifetime($lifetime);
		}

		return $instance;
	}

	/**
	 * Gets the temp directory root of the current scope.
	 *
	 * @return FilesManager
	 */
	public static function root(): FilesManager
	{
		// data/tmp-fs/{scope}, not .ozone/cache: an in-flight chunked upload and a file a form has
		// already accepted are a user's work in progress, and must survive what may delete a cache.
		return scope()->getTempDir();
	}

	/**
	 * The absolute path of a ref, or of a file directly in it.
	 *
	 * The ref is not checked for expiration: {@see self::canUse()} does that.
	 *
	 * @param string $ref  the ref
	 * @param string $name the name of a file in the ref directory, when any
	 *
	 * @return string
	 */
	public static function path(string $ref, string $name = ''): string
	{
		self::assertSegment($ref);

		if ('' === $name) {
			return self::root()->resolve($ref);
		}

		self::assertSegment($name);

		return self::root()->resolve($ref . DS . $name);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		GarbageCollector::register('oz:temp-fs', self::gc(...));
	}

	/**
	 * Asserts that a ref or a file name is a single, safe path segment.
	 *
	 * @param string $segment
	 */
	public static function assertSegment(string $segment): void
	{
		if (!self::isValidSegment($segment)) {
			throw new InvalidArgumentException(
				\sprintf('%s: "%s" is not a valid ref or file name.', self::class, $segment)
			);
		}
	}

	/**
	 * Checks that a ref or a file name is a single, safe path segment.
	 *
	 * @param string $segment
	 *
	 * @return bool
	 */
	private static function isValidSegment(string $segment): bool
	{
		return '' !== $segment
			&& '.' !== $segment
			&& '..' !== $segment
			&& !\strpbrk($segment, "/\\\0");
	}

	/**
	 * Loads the temp directory expiration time.
	 *
	 * @param string $tmp_dir
	 *
	 * @return null|int
	 */
	private static function getExpirationTime(string $tmp_dir): ?int
	{
		$info_path = FS::from($tmp_dir)->resolve('./info.json');

		if (\file_exists($info_path)) {
			try {
				$data = \json_decode(\file_get_contents($info_path), true, 512, \JSON_THROW_ON_ERROR);
				if (\is_array($data) && \array_key_exists('expires', $data)) {
					return (int) $data['expires'];
				}
			} catch (JsonException) {
			}
		}

		return null;
	}

	/**
	 * Cleans up the temp directory.
	 */
	private static function gc(): void
	{
		self::root()->walk('.', static function (string $name, string $path, bool $is_dir) {
			if ($is_dir) {
				$expires = self::getExpirationTime($path);
				if (!$expires) {
					// we simply ignore as its maybe a new temporary directory in creation process
					return false;
				}

				if ($expires < \time()) {
					try {
						// this may fail if other process is using or deleting the directory
						FS::fromRoot()->rmdir($path);
					} catch (Throwable) {
					}
				}
			}

			return false;
		});
	}
}
