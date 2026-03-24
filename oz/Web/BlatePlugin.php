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
use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\CSRF\CSRF;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Http\Uri;
use OZONE\Core\Lang\I18n;
use OZONE\Core\Lang\I18nMessage;
use OZONE\Core\Lang\Polyglot;
use OZONE\Core\Router\Route;
use Throwable;

/**
 * Class BlatePlugin.
 *
 * @internal
 */
final class BlatePlugin implements BootHookReceiverInterface
{
	public const CONTEXT_INJECT_KEY = '__blate_inject_oz_context__';

	private static $enable_risky = false;

	/**
	 * Registers the Blate plugin helpers and global variables.
	 */
	public static function register(): void
	{
		static $registered = false;

		if ($registered) {
			return;
		}
		$registered = true;

		Blate::registerHelper(
			'setting',
			[Settings::class, 'get']
		);
		Blate::registerHelper('env', env(...));
		Blate::registerHelper('log', oz_logger(...));
		Blate::registerHelper('t', self::t(...));
		Blate::registerHelper('uri', self::uri(...));
		Blate::registerHelper('route_uri', self::routeUri(...));

		Blate::registerComputedGlobalVar('csrf_token', self::csrfToken(...), [
			'description' => 'The CSRF token for the current route.',
		]);
		Blate::registerComputedGlobalVar('route', self::route(...), [
			'description' => 'The current route instance.',
		]);

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

		if (self::$enable_risky) {
			// Do we really need the whole context? Maybe we can just inject exactly
			// what is useful (like above) and avoid giving too much power to the templates?
			// But for now we can keep it like this and see if we really need it.
			Blate::registerComputedGlobalVar('oz_context', self::getContext(...), [
				'description' => 'The OZone application context.',
			]);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
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
	 * Returns the current route instance.
	 */
	public static function route(): Route
	{
		return self::getContext()->getRouteInfo()->route();
	}

	/**
	 * Returns the CSRF token for the current route.
	 */
	public static function csrfToken(): string
	{
		$ctx   = self::getContext();
		$route =  self::route();
		$scope = $route->getOptions()->getCSRFScope() ?? RequestScope::STATE;

		return (new CSRF($ctx, $scope))->generateToken();
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
