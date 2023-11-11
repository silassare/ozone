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

use JsonException;
use OZONE\Core\Hooks\Events\FinishHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Utils\Random;
use PHPUtils\Events\Event;
use RuntimeException;
use Throwable;

/**
 * Class TempFS.
 *
 * Create a temporary directory in which files and directories can be created.
 * The created directory has an expiration date for automatic deletion.
 */
class TempFS implements BootHookReceiverInterface
{
	/**
	 * TempFS constructor.
	 *
	 * @param string $ref
	 */
	protected function __construct(protected readonly string $ref) {}

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
		return self::getTempDir()->cd($this->ref, true);
	}

	/**
	 * Sets the temp directory expiration time.
	 *
	 * @param int $lifetime
	 *
	 * @return $this
	 */
	public function setLifetime(int $lifetime): self
	{
		$info_path = $this->dir()->resolve('./info.json');
		$expires   = \time() + $lifetime;
		$content   = \json_encode(['expires' => $expires]);

		\file_put_contents($info_path, $content);

		return $this;
	}

	/**
	 * Checks if a ref exists.
	 *
	 * @param string $ref
	 *
	 * @return bool
	 */
	public static function exists(string $ref): bool
	{
		$dir = self::getTempDir()->resolve($ref);

		$expire = self::getExpirationTime($dir);

		return $expire && $expire > \time();
	}

	/**
	 * Gets a new instance.
	 *
	 * @param int $lifetime the lifetime in seconds
	 *
	 * @return self
	 */
	public static function get(int $lifetime): self
	{
		$ref = Random::alpha(8) . '-' . \time();

		return (new self($ref))->setLifetime($lifetime);
	}

	/**
	 * Use a existing instance.
	 *
	 * @param string   $ref      the ref
	 * @param null|int $lifetime the lifetime in seconds
	 *
	 * @return self
	 */
	public static function use(string $ref, ?int $lifetime = null): self
	{
		$has = self::exists($ref);

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
	 * {@inheritDoc}
	 */
	public static function boot(): void
	{
		FinishHook::listen(static function () {
			self::gc();
		}, Event::RUN_LAST);
	}

	/**
	 * Gets the temp directory.
	 *
	 * @return FilesManager
	 */
	protected static function getTempDir(): FilesManager
	{
		return app()->getCacheDir()->cd('tmp-fs', true);
	}

	/**
	 * Loads the temp directory expiration time.
	 *
	 * @param string $tmp_dir
	 *
	 * @return null|int
	 */
	protected static function getExpirationTime(string $tmp_dir): ?int
	{
		$fs        = new FilesManager($tmp_dir);
		$info_path = $fs->resolve('./info.json');

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
		if (Random::bool()) {
			$root = self::getTempDir();

			$filter = $root->filter()->isDir();

			foreach ($filter->find() as $entry) {
				$dir     = $entry->getPathname();
				$expires = self::getExpirationTime($dir);
				if (!$expires) {
					// we simply ignore as its maybe a new temporary directory in creation process
					return;
				}

				if ($expires < \time()) {
					try {
						// this may fail if other process is using or deleting the directory
						(new FilesManager())->rmdir($dir);
					} catch (Throwable) {
					}
				}
			}
		}
	}
}
