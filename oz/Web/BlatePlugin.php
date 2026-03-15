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

namespace OZONE\Core\Web;

use Blate\Blate;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Http\Uri;
use OZONE\Core\Lang\I18n;
use OZONE\Core\Lang\I18nMessage;
use OZONE\Core\Lang\Polyglot;
use Throwable;

/**
 * Class BlatePlugin.
 *
 * @internal
 */
final class BlatePlugin implements BootHookReceiverInterface
{
	public const CONTEXT_INJECT_KEY = '__oz_web_blate_inject_context';

	public static function register(): void
	{
		Blate::registerHelper(
			'setting',
			[Settings::class, 'get']
		);
		Blate::registerHelper('env', env(...));
		Blate::registerHelper('log', oz_logger(...));
		Blate::registerHelper('t', self::t(...));
		Blate::registerHelper('uri', self::uri(...));
		Blate::registerHelper('route', self::routeUri(...));

		Blate::registerComputedGlobalVar('request_uri', self::requestUri(...), [
			'description' => 'The URI of the current request.',
		]);
		Blate::registerComputedGlobalVar('base_url', self::baseURL(...), [
			'description' => 'The base URL of the application.',
		]);
		Blate::registerComputedGlobalVar('lang', self::lang(...), [
			'description' => 'The current language.',
		]);

		Blate::registerGlobalVar('oz_version', OZ_OZONE_VERSION, [
			'description' => 'The current OZone version.',
		]);
		Blate::registerGlobalVar('oz_version_name', OZ_OZONE_VERSION_NAME, [
			'description' => 'The current OZone version name.',
		]);

		// Do we really need the whole context? Maybe we can just inject exactly
		// what we need (like the above) and avoid giving too much power to the templates?
		// Blate::registerGlobalVar('oz_context', self::getContext(...), [
		//   'description' => 'The OZone application context.',
		// ]);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function boot()
	{
		// auto load blate configs
		Blate::autoLoad(OZ_OZONE_DIR); // oz root
		Blate::autoLoad(OZ_PROJECT_DIR); // project root
		Blate::setCacheDir(app()->getCacheDir()->getRoot());
	}

	/**
	 * Returns the context.
	 */
	public static function getContext(): Context
	{
		/** @var null|Context $ctx */
		$ctx = Blate::scope()->data->get(self::CONTEXT_INJECT_KEY);

		return $ctx ?? context();
	}

	/**
	 * Translation helper for Blate templates.
	 *
	 * Shortcut for {@see I18n::t()}.
	 *
	 * @param I18nMessage|string $key  The translation key
	 * @param array              $data The data for interpolation
	 * @param null|string        $lang The language to use (optional)
	 *
	 * @return string
	 */
	public static function t(I18nMessage|string $key, array $data = [], ?string $lang = null): string
	{
		$ctx = self::getContext();

		try {
			return I18n::t($key, $data, $lang, $ctx);
		} catch (Throwable $t) {
			throw new RuntimeException('Translation failed.', [
				'key'  => $key,
				'data' => $data,
				'lang' => $lang,
			], $t);
		}
	}

	/**
	 * Returns the current request URI.
	 *
	 * Shortcut for {@see Request::getUri()}.
	 *
	 * @return Uri
	 */
	public static function requestUri(): Uri
	{
		$ctx = self::getContext();

		return $ctx->getRequest()->getUri();
	}

	/**
	 * Builds a URI for the given path and query parameters.
	 *
	 * Shortcut for {@see Context::buildUri()}.
	 *
	 * @param string $path  The path for the URI
	 * @param array  $query The query parameters for the URI
	 *
	 * @return Uri
	 */
	public static function uri(string $path, array $query = []): Uri
	{
		$ctx = self::getContext();

		return $ctx->buildUri($path, $query);
	}

	/**
	 * Builds a URI for the given route name, parameters, and query parameters.
	 *
	 * Shortcut for {@see Context::buildRouteUri()}.
	 *
	 * @param string $name   The name of the route
	 * @param array  $params The parameters for the route
	 * @param array  $query  The query parameters
	 *
	 * @return Uri
	 */
	public static function routeUri(string $name, array $params, array $query = []): Uri
	{
		$ctx = self::getContext();

		return $ctx->buildRouteUri($name, $params, $query);
	}

	/**
	 * Returns the base URL of the application.
	 *
	 * Shortcut for {@see Context::getBaseUrl()}.
	 *
	 * @return string
	 */
	public static function baseURL(): string
	{
		$ctx = self::getContext();

		return $ctx->getBaseUrl();
	}

	/**
	 * Gets current language.
	 *
	 * @return string
	 */
	public static function lang(): string
	{
		$ctx = self::getContext();

		return Polyglot::getLanguage($ctx);
	}
}
