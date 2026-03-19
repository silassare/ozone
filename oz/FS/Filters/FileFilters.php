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

use OZONE\Core\App\Settings;
use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\Filters\Interfaces\FileFilterHandlerInterface;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\Response;

/**
 * Class FileFilters.
 *
 * Registry and dispatcher for file filter handlers.
 *
 * Handlers registered earlier take priority over later ones. Handlers
 * declared in the `oz.files.filters` settings group are appended after those
 * registered programmatically (e.g. via a plugin's `boot()` method),
 * so plugin handlers always take precedence over default ones.
 *
 * Example - registering a custom handler from a plugin:
 *
 * ```php
 * // Inside MyPlugin::boot():
 * FileFilters::register(new MyPdfThumbnailHandler());
 * ```
 *
 * Example - registering via settings (lower priority than programmatic):
 *
 * ```php
 * // app/settings/oz.files.filters.php
 * return [
 *     MyPdfThumbnailHandler::class => true,
 * ];
 * ```
 */
final class FileFilters
{
	/**
	 * @var FileFilterHandlerInterface[]
	 */
	private static array $handlers = [];

	private static bool $loaded = false;

	/**
	 * Register a file filter handler.
	 *
	 * Handlers registered here are checked before settings-declared handlers.
	 *
	 * @param FileFilterHandlerInterface $handler
	 */
	public static function register(FileFilterHandlerInterface $handler): void
	{
		self::$handlers[] = $handler;
	}

	/**
	 * Apply filter tokens to a file and return the populated response.
	 *
	 * The first registered handler whose `canHandle()` returns true is used.
	 * When no handler matches, the raw file content is streamed as a safe fallback.
	 *
	 * @param OZFile     $file         the file entity
	 * @param FileStream $stream       a fresh, unread file stream
	 * @param Response   $response     the response to populate
	 * @param string[]   $filterTokens the individual filter tokens to apply
	 *
	 * @return Response
	 */
	public static function apply(OZFile $file, FileStream $stream, Response $response, array $filterTokens): Response
	{
		self::loadHandlers();

		foreach (self::$handlers as $handler) {
			if ($handler->canHandle($file, $filterTokens)) {
				return $handler->handle($file, $stream, $response, $filterTokens);
			}
		}

		// No handler matched - stream the raw file so the request never returns an empty body.
		$content = $stream->getContents();

		return $response
			->withHeader('Content-type', $file->getMime())
			->withHeader('Content-Length', (string) \strlen($content))
			->withBody(Body::fromString($content));
	}

	/**
	 * Load and instantiate handlers declared in `oz.files.filters` settings.
	 *
	 * Runs once per PHP process; subsequent calls are no-ops.
	 */
	private static function loadHandlers(): void
	{
		if (self::$loaded) {
			return;
		}

		self::$loaded = true;

		$map = Settings::load('oz.files.filters');

		foreach ($map as $class => $enabled) {
			if ($enabled && \is_subclass_of($class, FileFilterHandlerInterface::class)) {
				self::register(new $class());
			}
		}
	}
}
