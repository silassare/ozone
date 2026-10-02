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

use Exception;
use Override;
use OZONE\Core\App\GarbageCollector;
use OZONE\Core\App\Settings;
use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\Enums\FileKind;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\Filters\Interfaces\FileFilterHandlerInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\Images\ImageRecipe;
use OZONE\Core\FS\Images\ImageWatermarks;
use OZONE\Core\FS\Images\Images;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Http\Response;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Class ImageFileFilterHandler.
 *
 * Built-in filter handler for image files (any `image/*` mime type), rendered by the image
 * processor of OZone ({@see Images}: Intervention Image, with libvips, Imagick or GD). Every
 * rendition is upright (its orientation applied) and carries no metadata (location, camera).
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
 * The tokens are first reduced to one canonical list ({@see ImageFilterTokens}): sizes snapped to
 * `OZ_IMAGE_FILTERS_SIZES`, blur and quality bounded, unknown tokens dropped, their number capped;
 * and an image is never enlarged beyond its own size. So a URL cannot make the server render, or
 * keep, an unbounded number of renditions, nor an image of any size.
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
		$tokens   = ImageFilterTokens::normalize($filterTokens);
		$auto     = \in_array('auto', $tokens, true);
		$tokens   = self::resolveFormat($tokens, $file->getMime());
		$enforced = ImageWatermarks::enforcedFor($file);

		// A watermark a project forces replaces any the URL asks for: it cannot be dropped.
		if (null !== $enforced) {
			$tokens   = \array_values(
				\array_filter($tokens, static fn (string $t): bool => !\str_starts_with($t, 'wm'))
			);
			$tokens[] = 'wm' . $enforced;
		}

		$mime = self::mimeOf($tokens, $file->getMime());
		$key  = \md5($file->getID() . ':' . $file->getKey() . ':' . \implode(',', $tokens));
		$dir  = self::cacheDir()->cd(\substr($key, 0, 2), true);
		$path = $dir->resolve($key);

		if (!\is_file($path)) {
			$bytes = $this->process($file, $stream, $tokens, null !== $enforced);

			if (null === $bytes) {
				// Not rendered: the file as it is, which is not kept as a rendition.
				$stream->rewind();

				return $response
					->withHeader('Content-type', $file->getMime())
					->withBody($stream);
			}

			// Atomic: a concurrent request serves either no rendition yet or a whole one.
			$dir->writeAtomic($key, $bytes);
		}

		$response = $response
			->withHeader('Content-type', $mime)
			->withHeader('Content-Length', (string) \filesize($path))
			->withBody(FileStream::fromPath($path));

		// What was served depends on what the browser accepts: a cache must keep them apart.
		return $auto ? $response->withAddedHeader('Vary', 'Accept') : $response;
	}

	/**
	 * `auto` made the best format the browser accepts and this server writes (AVIF, then WebP),
	 * or dropped for the image's own; an animated format keeps its own.
	 *
	 * @param list<string> $tokens
	 *
	 * @return list<string>
	 */
	public static function resolveFormat(array $tokens, string $mime, ?string $accept = null): array
	{
		$index = \array_search('auto', $tokens, true);

		if (false === $index) {
			return $tokens;
		}

		$accept ??= context()->getRequest()->getHeaderLine('Accept');
		$chosen = null;

		if ('image/gif' !== $mime) {
			foreach (['avif', 'webp'] as $format) {
				if (
					\str_contains($accept, 'image/' . $format)
					&& Images::processor()->supports('image/' . $format)
				) {
					$chosen = 'image/' . $format === $mime ? null : $format;

					break;
				}
			}
		}

		if (null === $chosen) {
			unset($tokens[$index]);
		} else {
			$tokens[$index] = $chosen;
		}

		return \array_values($tokens);
	}

	/**
	 * The media type a rendition of these tokens has.
	 *
	 * @param list<string> $tokens
	 */
	private static function mimeOf(array $tokens, string $mime): string
	{
		foreach (['webp', 'avif'] as $format) {
			if (\in_array($format, $tokens, true)) {
				return 'image/' . $format;
			}
		}

		return $mime;
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
	 * Renders the canonical tokens: the rendition's bytes, or null when the image could not be
	 * rendered (it is then served as it is), unless a watermark is due on it: then it is never served
	 * without, and the failure is the request's.
	 *
	 * @param list<string> $filterTokens
	 */
	private function process(OZFile $file, FileStream $stream, array $filterTokens, bool $watermarked): ?string
	{
		$content = $stream->getContents();
		$recipe  = ImageRecipe::fromTokens(
			$filterTokens,
			(int) Settings::get('oz.files', 'OZ_THUMBNAIL_MAX_SIZE')
		);

		try {
			return Images::processor()->render($content, $file->getMime(), $recipe)->bytes;
		} catch (Exception $e) {
			if ($watermarked) {
				throw new RuntimeException('The image could not be rendered with its watermark.', [
					'_file' => $file->getID(),
				], $e);
			}

			oz_logger()->error('Image filter processing failed, serving the raw file.', [
				'_file'      => $file->getID(),
				'_filters'   => $filterTokens,
				'_exception' => $e->getMessage(),
			]);

			return null;
		}
	}
}
