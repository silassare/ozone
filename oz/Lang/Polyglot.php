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

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use Throwable;

/**
 * Class Polyglot.
 */
final class Polyglot
{
	public const CLIENT_LANG_SESSION_KEY = 'oz.polyglot.favorite';

	public const ACCEPT_LANGUAGE_REG = '~([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.\d+))?~i';

	public const LANG_KEY_REG = '~^[A-Z][A-Z0-9_.]+$~';

	// for {{LANG_KEY}}
	public const PORTION_COPY_REG = '~{{\s*([A-Z][A-Z0-9_.]+)\s*}}~';

	// for {variable} and {variable | filter1 | filter2}
	public const SIMPLE_REPLACE_REG = '~{\s*([a-zA-Z0-9_]+)\s*((?:\|\s*[a-zA-Z_][a-zA-Z0-9_]*\s*)+)?}~';
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
	 * Gets language to use.
	 *
	 * @param null|\OZONE\Core\App\Context $context
	 *
	 * @return string
	 */
	public static function getLanguage(Context $context = null): string
	{
		if ($context) {
			$state = $context->state();

			$user_lang = $state?->get(self::CLIENT_LANG_SESSION_KEY);

			if (!empty($user_lang)) {
				return $user_lang;
			}

			$accept_language = $context->getRequest()
				->getHeaderLine('HTTP_ACCEPT_LANGUAGE');
			$browser         = self::parseBrowserLanguage($accept_language);

			if (!empty($browser['advice'])) {
				$state?->set(self::CLIENT_LANG_SESSION_KEY, $browser['advice']);

				return $browser['advice'];
			}
		}

		return self::getDefaultLanguage();
	}

	/**
	 * Sets user preferred language.
	 *
	 * @param \OZONE\Core\App\Context $context
	 * @param string                  $lang
	 *
	 * @return bool
	 */
	public static function setUserLanguage(Context $context, string $lang): bool
	{
		$list = self::getEnabledLanguages();

		if (isset($list[$lang])) {
			$context->state()
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
	 * @param string                       $key     the human readable text key
	 * @param array                        $inject  data to use for replacement
	 * @param null|string                  $lang    use a specific lang
	 * @param null|\OZONE\Core\App\Context $context the context
	 *
	 * @return string human readable text or null if none found
	 */
	public static function translate(
		string $key,
		array $inject = [],
		?string $lang = null,
		Context $context = null
	): string {
		if (!self::isLangKey($key)) {
			return $key;
		}

		if (null === $lang) {
			$lang = self::getLanguage($context);
		}

		return self::parseText($key, $inject, $lang);
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
			if (true === $value) {
				$result[$lang] = true;
			}
		}

		return $result;
	}

	/**
	 * Parse user browser 'Accept-Language' header and advice
	 * for the best to use according to available languages.
	 *
	 * @param null|string $http_accept_language
	 *
	 * @return array
	 */
	public static function parseBrowserLanguage(?string $http_accept_language = null): array
	{
		$browser_languages = [];
		$enabled_languages = self::getEnabledLanguages();
		$advice            = null;

		if (!empty($http_accept_language)) {
			// break up string into pieces (languages and q factors)
			\preg_match_all(self::ACCEPT_LANGUAGE_REG, $http_accept_language, $lang_parse);

			if (\count($lang_parse[1])) {
				// creates a list like "en" => 0.8
				$browser_languages = \array_combine($lang_parse[1], $lang_parse[4]);

				// sets default to 1 for any without q factor
				foreach ($browser_languages as $lang => $q) {
					if ('' === $q) {
						$browser_languages[$lang] = 1;
					}
				}

				// sort list based on value
				\arsort($browser_languages, \SORT_NUMERIC);
			}
		}

		if (\count($browser_languages) && \count($enabled_languages)) {
			// look through sorted list and use first one that matches our languages
			foreach ($browser_languages as $lang => $q) {
				// user language is available
				if (isset($enabled_languages[$q])) {
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

				foreach ($browser_languages_groups as $group => $list) {
					if (isset($enabled_languages_groups[$group])) {
						// gets the first language in this group
						$advice = $enabled_languages_groups[$group][0];

						break;
					}
				}
			}
		}

		return ['languages' => $browser_languages, 'advice' => $advice];
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

		if (\is_string($text) && \preg_match(self::SIMPLE_REPLACE_REG, $text)) {
			$in = [];

			while (\preg_match(self::SIMPLE_REPLACE_REG, $text, $in)) {
				[$found, $variable] = $in;
				$filters            = $in[2] ?? '';

				$value = (string) ($inject[$variable] ?? '');

				if (!empty($filters)) {
					$filters_list = \explode(self::FILTERS_SEP, $filters);

					foreach ($filters_list as $filter) {
						if (!empty($filter = \trim($filter))) {
							$value = (string) self::applyFilter($filter, $value, $lang);
						}
					}
				}

				$text = \str_replace($found, $value, $text);
			}
		}

		return $text;
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

		try {
			return $fn($value, $lang);
		} catch (Throwable) {
		}

		return $value;
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
		$text = Settings::get('lang/oz.' . $lang, $i18n_key);

		if (null === $text) {
			$default = self::getDefaultLanguage();
			$text    = Settings::get('lang/oz.' . $default, $i18n_key);
		}

		// could be string or array or anything else
		if (\is_string($text) && \preg_match(self::PORTION_COPY_REG, $text)) {
			$in                 = [];
			$history[$i18n_key] = true;

			while (\preg_match(self::PORTION_COPY_REG, $text, $in)) {
				@[$found, $lk] = $in;

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

		foreach ($languages as $lang => $value) {
			$group            = self::getLanguageGroup($lang);
			$groups[$group][] = $lang;
		}

		return $groups;
	}
}
