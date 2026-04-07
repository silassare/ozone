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
use OZONE\Core\App\Settings;
use OZONE\Core\Cache\CacheRegistry;
use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\Enums\FileKind;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\Filters\Interfaces\FileFilterHandlerInterface;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\Response;

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
 * Processed images are cached via the persistent cache. The cache key
 * includes the file key so it is naturally invalidated when the file changes.
 *
 * Output format always matches the original file mime type.
 */
class ImageFileFilterHandler implements FileFilterHandlerInterface
{
	private const CACHE_NS = 'oz:fs:image:filters';

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
		$cacheKey = \md5($file->getID() . ':' . $file->getKey() . ':' . \implode(',', $filterTokens));
		$store    = CacheRegistry::store(self::CACHE_NS);

		/** @var null|array{mime: string, bytes: string} $cached */
		$cached   = $store->get($cacheKey);

		if (null !== $cached) {
			$mime  = $cached['mime'];
			$bytes = $cached['bytes'];
		} else {
			[$mime, $bytes] = $this->process($file, $stream, $filterTokens);
			$store->set($cacheKey, ['mime' => $mime, 'bytes' => $bytes]);
		}

		return $response
			->withHeader('Content-type', $mime)
			->withHeader('Content-Length', (string) \strlen($bytes))
			->withBody(Body::fromString($bytes));
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
