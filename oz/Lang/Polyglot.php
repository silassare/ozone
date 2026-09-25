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

namespace OZONE\Core\Lang;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Router\Events\RouteBeforeRun;
use OZONE\Core\Router\Interfaces\RouteProviderInterface;
use OZONE\Core\Router\Router;
use PHPUtils\Events\Event;

/**
 * Class Polyglot.
 */
final class Polyglot implements BootHookReceiverInterface, RouteProviderInterface
{
	public const ROUTE_LANG_PARAM         = 'lang';
	public const ROUTE_LANG_PARAM_PATTERN = '[a-z]{1,8}(-[a-z]{1,8})?';

	public const CLIENT_LANG_SESSION_KEY = 'oz.polyglot.favorite';

	public const ACCEPT_LANGUAGE_REG = '~([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.\d+))?~i';

	public const LANG_KEY_REG = '~^[A-Z][A-Z0-9_.]+$~';

	// for {{LANG_KEY}}
	public const PORTION_COPY_REG = '~{{\s*([A-Z][A-Z0-9_.]+)\s*}}~';

	// for {variable} and {variable | filter1 | filter2}
	public const SIMPLE_REPLACE_REG = '~{\s*(\w+)\s*((?:\|\s*[a-zA-Z_]\w*\s*)+)?}~';
	public const FILTERS_SEP        = '|';

	private static array $filters = [];

	/**
	 * Declare a filter.
	 *
	 * When used, the filter will receive two arguments:
	 *  - the `value` on which the filter is being applied
	 *  - the current `lang` used for translation
	 *
	 * @param string   $name
	 * @param callable $filter
	 */
	public static function declareFilter(string $name, callable $filter): void
	{
		self::$filters[$name] = $filter;
	}

	/**
	 * The names of the translation filters declared ({@see self::declareFilter()}), which a client
	 * translating the catalogs itself must implement too.
	 *
	 * @return list<string>
	 */
	public static function getFilterNames(): array
	{
		return \array_keys(self::$filters);
	}

	/**
	 * What a client needs to translate the keys the API sends, since the API never translates them:
	 * the catalog of every enabled language as {@see self::translate()} reads it (OZone's, the
	 * plugins' and the project's merged by {@see Settings}), the default language a missing text falls
	 * back to, and the filters the texts may use.
	 *
	 * The texts are as written: `{var}`, `{var | filter}` and `{{KEY}}` are for the client to resolve.
	 * A language enabled without a catalog has an empty one, and every text falls back to the default.
	 *
	 * @return array{
	 *  default: string,
	 *  languages: list<string>,
	 *  catalogs: array<string, array<string, mixed>>,
	 *  filters: list<string>
	 * }
	 */
	public static function exportCatalogs(): array
	{
		$languages = \array_keys(self::getEnabledLanguages());
		$catalogs  = [];

		foreach ($languages as $lang) {
			$group           = 'lang/oz.' . $lang;
			$catalogs[$lang] = Settings::has($group) ? Settings::load($group) : [];
		}

		return [
			'default'   => self::getDefaultLanguage(),
			'languages' => $languages,
			'catalogs'  => $catalogs,
			'filters'   => self::getFilterNames(),
		];
	}

	/**
	 * Gets language to use.
	 *
	 * @param null|Context $context
	 *
	 * @return string
	 */
	public static function getLanguage(?Context $context = null): string
	{
		if ($context) {
			$store = $context->authStore();

			$user_lang = $store?->get(self::CLIENT_LANG_SESSION_KEY);

			if (!empty($user_lang)) {
				return $user_lang;
			}

			$accept_language = $context->getRequest()
				->getHeaderLine('HTTP_ACCEPT_LANGUAGE');
			$browser         = self::parseBrowserLanguage($accept_language);

			// Not stored: the browser sends it with every request, and writing it would give every
			// anonymous visitor a session. Only a choice (setUserLanguage()) is kept.
			if (!empty($browser['advice'])) {
				return $browser['advice'];
			}
		}

		return self::getDefaultLanguage();
	}

	/**
	 * Sets user preferred language.
	 *
	 * @param Context $context
	 * @param string  $lang
	 *
	 * @return bool
	 */
	public static function setUserLanguage(Context $context, string $lang): bool
	{
		$list = self::getEnabledLanguages();

		if (isset($list[$lang])) {
			$context->authStore()
				?->set(self::CLIENT_LANG_SESSION_KEY, $lang);

			return true;
		}

		return false;
	}

	/**
	 * Translate lang key to human readable text.
	 *
	 * when the lang key is invalid the supplied key is simply returned
	 *
	 * ```php
	 * $data = ["name" => "Dig Ma", "age" => 18 ];
	 *
	 * Polyglot::translate('MY_LANG_KEY', $data);
	 * Polyglot::translate('group.based.LANG_KEY', $data);
	 * Polyglot::translate('MY_LANG_KEY', $data, 'fr');
	 * ```
	 *
	 * @param string       $key     the human readable text key
	 * @param null|array   $inject  data to use for replacement
	 * @param null|string  $lang    use a specific lang
	 * @param null|Context $context the context
	 *
	 * @return string human readable text or null if none found
	 */
	public static function translate(
		string $key,
		?array $inject = null,
		?string $lang = null,
		?Context $context = null
	): string {
		if (!self::isLangKey($key)) {
			return $key;
		}

		if (null === $lang) {
			$lang = self::getLanguage($context);
		}

		return self::parseText($key, $inject ?? [], $lang);
	}

	/**
	 * Checks if we have a valid lang key.
	 *
	 * @param string $key the key to check
	 *
	 * @return bool
	 */
	public static function isLangKey(string $key): bool
	{
		return (bool) \preg_match(self::LANG_KEY_REG, $key);
	}

	/**
	 * Gets available languages list or check if a given one is defined.
	 *
	 * @return array
	 */
	public static function getAvailableLanguages(): array
	{
		return Settings::load('lang/oz.lang.list');
	}

	/**
	 * Gets the default language.
	 *
	 * @return string
	 */
	public static function getDefaultLanguage(): string
	{
		return Settings::get('lang/oz.lang.list', 'default');
	}

	/**
	 * Gets enabled languages list from available languages.
	 *
	 * @return array
	 */
	public static function getEnabledLanguages(): array
	{
		$list   = self::getAvailableLanguages();
		$result = [];

		foreach ($list as $lang => $value) {
			if (true !== $value) {
				continue;
			}

			$result[$lang] = true;
		}

		return $result;
	}

	/**
	 * Parse user browser 'Accept-Language' header and advice
	 * for the best to use according to available languages.
	 *
	 * @param null|string                    $http_accept_language
	 * @param null|array<string, true>        $enabled_languages    the enabled ones by default
	 *
	 * @return array
	 */
	public static function parseBrowserLanguage(
		?string $http_accept_language = null,
		?array $enabled_languages = null
	): array {
		$browser_languages = [];
		$enabled_languages ??= self::getEnabledLanguages();
		$advice            = null;

		if (!empty($http_accept_language)) {
			// break up string into pieces (languages and q factors)
			\preg_match_all(self::ACCEPT_LANGUAGE_REG, $http_accept_language, $lang_parse);

			if (\count($lang_parse[1])) {
				// creates a list like "en" => 0.8
				$browser_languages = \array_combine($lang_parse[1], $lang_parse[4]);

				// sets default to 1 for any without q factor
				foreach ($browser_languages as $lang => $q) {
					if ('' !== $q) {
						continue;
					}

					$browser_languages[$lang] = 1;
				}

				// sort list based on value
				\arsort($browser_languages, \SORT_NUMERIC);
			}
		}

		if (\count($browser_languages) && \count($enabled_languages)) {
			// look through sorted list and use first one that matches our languages
			foreach (\array_keys($browser_languages) as $lang) {
				// user language is available
				if (isset($enabled_languages[$lang])) {
					$advice = $lang;

					break;
				}

				// for 'fr-bj' choose 'fr' if available
				$lang_group = self::getLanguageGroup($lang);

				if ($lang_group !== $lang && isset($enabled_languages[$lang_group])) {
					$advice = $lang_group;

					break;
				}
			}

			// none found : let's search for available according to language group
			if (null === $advice) {
				$browser_languages_groups = self::sortLanguagesByGroups($browser_languages);
				$enabled_languages_groups = self::sortLanguagesByGroups($enabled_languages);

				foreach ($browser_languages_groups as $group => $_) {
					if (!(isset($enabled_languages_groups[$group]))) {
						continue;
					}

					$advice = $enabled_languages_groups[$group][0];

					break;
				}
			}
		}

		return ['languages' => $browser_languages, 'advice' => $advice];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		RouteBeforeRun::listen(static function (RouteBeforeRun $ev): void {
			$lang = $ev->target->param(self::ROUTE_LANG_PARAM);

			if ($lang) {
				Polyglot::setUserLanguage($ev->context, $lang);
			}
		}, Event::RUN_FIRST);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router->addGlobalParam(
			self::ROUTE_LANG_PARAM,
			self::ROUTE_LANG_PARAM_PATTERN,
			self::getLanguage(...)
		);
	}

	/**
	 * Parse lang key.
	 *
	 * @param string $i18n_key
	 * @param array  $inject
	 * @param string $lang
	 *
	 * @return mixed
	 */
	private static function parseText(string $i18n_key, array $inject, string $lang): mixed
	{
		$text = self::getI18n($i18n_key, $lang);

		if (!\is_string($text)) {
			return $text;
		}

		// One pass over the text as written: a value is never read for placeholders, so one that holds
		// `{name}` is shown as it is, rather than filled again (forever, when it names itself).
		return \preg_replace_callback(
			self::SIMPLE_REPLACE_REG,
			static function (array $in) use ($inject, $lang): string {
				$value = (string) ($inject[$in[1]] ?? '');

				foreach (\explode(self::FILTERS_SEP, $in[2] ?? '') as $filter) {
					if ('' !== ($filter = \trim($filter))) {
						$value = (string) self::applyFilter($filter, $value, $lang);
					}
				}

				return $value;
			},
			$text
		);
	}

	/**
	 * Apply a filter to a given value.
	 *
	 * @param string $filter
	 * @param mixed  $value
	 * @param string $lang
	 *
	 * @return mixed
	 */
	private static function applyFilter(string $filter, mixed $value, string $lang): mixed
	{
		$fn = self::$filters[$filter] ?? null;

		if (!$fn) {
			throw new RuntimeException(\sprintf('Undefined translation filter: %s', $filter));
		}

		return $fn($value, $lang);
	}

	/**
	 * Returns i18n data for a given key.
	 *
	 * when the lang key is invalid the supplied key is simply returned
	 *
	 * @param string $i18n_key
	 * @param string $lang
	 * @param array  $history
	 *
	 * @return mixed
	 */
	private static function getI18n(string $i18n_key, string $lang, array $history = []): mixed
	{
		// for 'fr-bj' lang settings should be 'lang/oz.fr-bj'
		$text = self::catalogText($lang, $i18n_key);

		if (null === $text) {
			$text = self::catalogText(self::getDefaultLanguage(), $i18n_key);
		}

		// could be string or array or anything else
		if (\is_string($text) && \preg_match(self::PORTION_COPY_REG, $text)) {
			$in                 = [];
			$history[$i18n_key] = true;

			while (\preg_match(self::PORTION_COPY_REG, $text, $in)) {
				[$found, $lk] = $in;

				if (isset($history[$lk])) {
					throw new RuntimeException(\sprintf('Possible infinite loop in lang key: %s.', $lk));
				}

				$history[$lk] = true;
				$part         = self::getI18n($lk, $lang, $history);
				$text         = \str_replace($found, $part, $text);
			}
		}

		return $text ?? $i18n_key;
	}

	/**
	 * A text of a language's catalog; none when the language has no catalog, which a project may
	 * enable before writing it: its texts then fall back to the default language.
	 */
	private static function catalogText(string $lang, string $i18n_key): mixed
	{
		$group = 'lang/oz.' . $lang;

		return Settings::has($group) ? Settings::get($group, $i18n_key) : null;
	}

	/**
	 * Gets the language group of a given language.
	 *
	 * ex: 'fr-bj' -> 'fr'
	 *
	 * @param string $lang
	 *
	 * @return string
	 */
	private static function getLanguageGroup(string $lang): string
	{
		$parts = \explode('-', $lang);

		return $parts[0];
	}

	/**
	 * Sort languages by language group.
	 *
	 * @param array $languages
	 *
	 * @return array
	 */
	private static function sortLanguagesByGroups(array $languages): array
	{
		$groups = [];

		foreach ($languages as $lang => $_) {
			$group            = self::getLanguageGroup($lang);
			$groups[$group][] = $lang;
		}

		return $groups;
	}
}
