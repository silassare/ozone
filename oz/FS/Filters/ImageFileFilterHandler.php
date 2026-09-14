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

namespace OZONE\Core\FS\Filters;

use claviska\SimpleImage;
use Exception;
use Override;
use OZONE\Core\App\GarbageCollector;
use OZONE\Core\App\Settings;
use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\Enums\FileKind;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\Filters\Interfaces\FileFilterHandlerInterface;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Http\Response;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Class ImageFileFilterHandler.
 *
 * Built-in filter handler for image files (any `image/*` mime type).
 * Uses the `claviska/simpleimage` library to apply transformations.
 *
 * Supported filter tokens (URL-safe: `[a-z0-9]+`):
 *
 * | Token       | Effect                                                                    |
 * | ----------- | ------------------------------------------------------------------------- |
 * | `thumb`     | Smart thumbnail using the default `OZ_THUMBNAIL_MAX_SIZE` value (crop)    |
 * | `thumb{N}`  | Smart thumbnail with max N x N pixels (crop)                              |
 * | `w{N}`      | Resize to width N, scale height proportionally                            |
 * | `h{N}`      | Resize to height N, scale width proportionally                            |
 * | `q{N}`      | Output quality percentage 1-100 (meaningful for JPEG and WebP)            |
 * | `crop`      | Force center-crop when both width and height are specified                 |
 * | `nocrop`    | Use bestFit (no crop) even when `thumb` was used                          |
 * | `grayscale` | Convert to grayscale                                                      |
 * | `sepia`     | Apply sepia effect                                                        |
 * | `blur`      | Gaussian blur (1 pass)                                                    |
 * | `blur{N}`   | Gaussian blur with N passes                                               |
 * | `sharpen`   | Sharpen the image                                                         |
 *
 * Each rendition is kept as a file in the scope's cache directory (`.ozone/cache/.../fs/image-filters`)
 * and served from it as a stream: never in a key-value store, where a large image would travel
 * through the database or Redis and sit in memory whole. Its name hashes the file ID, the file key
 * and the tokens, so a changed file gets a new rendition; renditions older than
 * `OZ_IMAGE_FILTERS_CACHE_TTL` (`oz.files`) are removed by the garbage collector and rendered again
 * on demand. `.ozone/` is per instance and may be deleted at any time, which only costs a render.
 *
 * Output format always matches the original file mime type.
 */
class ImageFileFilterHandler implements FileFilterHandlerInterface, BootHookReceiverInterface
{
	private const CACHE_DIR = 'fs/image-filters';

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		GarbageCollector::register('oz:fs:image-filters', self::gc(...));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function canHandle(OZFile $file, array $filterTokens): bool
	{
		return FileKind::IMAGE === FileKind::fromMime($file->getMime());
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function handle(OZFile $file, FileStream $stream, Response $response, array $filterTokens): Response
	{
		$key  = \md5($file->getID() . ':' . $file->getKey() . ':' . \implode(',', $filterTokens));
		$dir  = self::cacheDir()->cd(\substr($key, 0, 2), true);
		$path = $dir->resolve($key);

		if (!\is_file($path)) {
			[, $bytes] = $this->process($file, $stream, $filterTokens);

			// Atomic: a concurrent request serves either no rendition yet or a whole one.
			$dir->writeAtomic($key, $bytes);
		}

		return $response
			->withHeader('Content-type', $file->getMime())
			->withHeader('Content-Length', (string) \filesize($path))
			->withBody(FileStream::fromPath($path));
	}

	/**
	 * Where the renditions are kept: the scope's cache directory, per instance.
	 */
	private static function cacheDir(): FilesManager
	{
		return app()->getCacheDir()->cd(self::CACHE_DIR, true);
	}

	/**
	 * Removes the renditions older than `OZ_IMAGE_FILTERS_CACHE_TTL`: they are rendered again when
	 * next asked for, and renditions of a changed or deleted file are never asked for again.
	 */
	private static function gc(): void
	{
		$root = self::cacheDir()->getRoot();
		$ttl  = (int) Settings::get('oz.files', 'OZ_IMAGE_FILTERS_CACHE_TTL', 604800);

		if ($ttl <= 0 || !\is_dir($root)) {
			return;
		}

		$before = \time() - $ttl;
		$files  = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
		);

		/** @var SplFileInfo $entry */
		foreach ($files as $entry) {
			if ($entry->isFile() && $entry->getMTime() < $before) {
				\unlink($entry->getPathname());
			}
		}
	}

	/**
	 * Parse tokens, apply SimpleImage transformations, return [mime, bytes].
	 *
	 * Falls back to the raw content when image processing fails.
	 *
	 * @param OZFile     $file
	 * @param FileStream $stream
	 * @param string[]   $filterTokens
	 *
	 * @return array{0: string, 1: string}
	 */
	private function process(OZFile $file, FileStream $stream, array $filterTokens): array
	{
		/**
		 * @var null|int $maxW
		 * @var null|int $maxH
		 */
		$maxW           = null;
		$maxH           = null;
		$quality        = 100;
		$useCrop        = false;
		// true when 'crop' or 'nocrop' was explicit
		$cropOverridden = false;

		// Effects are stored as closures applied in declaration order.
		$effects = [];

		foreach ($filterTokens as $token) {
			if ('thumb' === $token) {
				if (!$cropOverridden) {
					$useCrop = true;
				}
				$maxW ??= (int) Settings::get('oz.files', 'OZ_THUMBNAIL_MAX_SIZE');
				$maxH ??= (int) Settings::get('oz.files', 'OZ_THUMBNAIL_MAX_SIZE');
			} elseif (\preg_match('/^thumb(\d+)$/', $token, $m)) {
				if (!$cropOverridden) {
					$useCrop = true;
				}
				$maxW = (int) $m[1];
				$maxH = (int) $m[1];
			} elseif (\preg_match('/^w(\d+)$/', $token, $m)) {
				$maxW = (int) $m[1];
			} elseif (\preg_match('/^h(\d+)$/', $token, $m)) {
				$maxH = (int) $m[1];
			} elseif (\preg_match('/^q(\d+)$/', $token, $m)) {
				$quality = \max(1, \min(100, (int) $m[1]));
			} elseif ('crop' === $token) {
				$useCrop        = true;
				$cropOverridden = true;
			} elseif ('nocrop' === $token) {
				$useCrop        = false;
				$cropOverridden = true;
			} elseif ('grayscale' === $token) {
				$effects[] = static function (SimpleImage $img): void {
					$img->desaturate();
				};
			} elseif ('sepia' === $token) {
				$effects[] = static function (SimpleImage $img): void {
					$img->sepia();
				};
			} elseif ('sharpen' === $token) {
				$effects[] = static function (SimpleImage $img): void {
					$img->sharpen();
				};
			} elseif ('blur' === $token) {
				$effects[] = static function (SimpleImage $img): void {
					$img->blur('gaussian', 1);
				};
			} elseif (\preg_match('/^blur(\d+)$/', $token, $m)) {
				$passes    = (int) $m[1];
				$effects[] = static function (SimpleImage $img) use ($passes): void {
					$img->blur('gaussian', $passes);
				};
			}
			// Unknown tokens are silently ignored.
		}

		// Process image

		$mime    = $file->getMime();
		$content = $stream->getContents();

		try {
			$img = new SimpleImage();
			$img->fromString($content);

			if (null !== $maxW || null !== $maxH) {
				$w = $maxW ?? 0;
				$h = $maxH ?? 0;

				if ($useCrop && $w && $h) {
					$img->thumbnail($w, $h);
				} elseif ($w && $h) {
					$img->bestFit($w, $h);
				} elseif ($w) {
					$img->resize($w, null);
				} else {
					$img->resize(null, $h);
				}
			}

			foreach ($effects as $effect) {
				$effect($img);
			}

			$bytes = $img->toString($mime, $quality);
		} catch (Exception $e) {
			oz_logger()->error('Image filter processing failed, falling back to raw file.', [
				'_file'      => $file->getID(),
				'_filters'   => $filterTokens,
				'_exception' => $e->getMessage(),
			]);

			// Return raw content so the request never serves an empty body.
			$bytes = $content;
		}

		return [$mime, $bytes];
	}
}
