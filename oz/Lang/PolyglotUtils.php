<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Lang;

use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\Http\Environment;

class PolyglotUtils
{
	const ACCEPT_LANGUAGE_REG = "/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i";

	const LANG_KEY_REG        = '#^[A-Z][A-Z0-9_]+$#';

	// for {{LANG_KEY}}
	const PORTION_COPY_REG = "/{{\s*([A-Z][A-Z0-9_]+)\s*}}/";

	// for {variable} and {fn:variable}
	const SIMPLE_REPLACE_REG = "/{\s*(?:([a-zA-Z_]+):)?([a-zA-Z0-9_]+)\s*}/";

	/**
	 * Checks if we have a valid lang key
	 *
	 * @param string $key the key to check
	 *
	 * @return bool
	 */
	public static function isLangKey($key)
	{
		return \preg_match(self::LANG_KEY_REG, $key);
	}

	/**
	 * Gets available languages list or check if a given one is defined
	 *
	 * @return array
	 */
	public static function getAvailableLanguages()
	{
		return SettingsManager::get('lang/oz.lang.list');
	}

	/**
	 * Gets the default language.
	 *
	 * @return string
	 */
	public static function getDefaultLanguage()
	{
		return SettingsManager::get('lang/oz.lang.list', 'default');
	}

	/**
	 * Gets enabled languages list from available languages
	 *
	 * @return array
	 */
	public static function getEnabledLanguages()
	{
		$list   = self::getAvailableLanguages();
		$result = [];

		if (\is_array($list)) {
			foreach ($list as $lang => $value) {
				if ($value === true) {
					$result[$lang] = true;
				}
			}
		}

		return $result;
	}

	/**
	 * Parse lang key.
	 *
	 * @param string $lang_key
	 * @param array  $data
	 * @param string $lang_code
	 *
	 * @throws \Exception
	 *
	 * @return mixed
	 */
	public static function parseText($lang_key, $data, $lang_code)
	{
		$text = self::getLangText($lang_key, $lang_code);

		if (\is_string($text) && \preg_match(self::SIMPLE_REPLACE_REG, $text)) {
			$in = [];

			while (\preg_match(self::SIMPLE_REPLACE_REG, $text, $in)) {
				@[$found, $fn, $variable] = $in;

				$value = isset($data[$variable]) ? $data[$variable] : '';

				if (!empty($fn)) {
					$value = self::fnExec($fn, $value, $lang_code);
				}

				$text = \str_replace($found, $value, $text);
			}
		}

		return $text;
	}

	/**
	 * Parse user browser 'Accept-Language' header and advice
	 * for the best to use according to available languages
	 *
	 * @param \OZONE\OZ\Http\Environment $env
	 *
	 * @return array
	 */
	public static function parseBrowserLanguage(Environment $env)
	{
		$browser_languages = [];
		$enabled_languages = self::getEnabledLanguages();
		$advice            = null;

		if ($env->has('HTTP_ACCEPT_LANGUAGE')) {
			// break up string into pieces (languages and q factors)
			\preg_match_all(self::ACCEPT_LANGUAGE_REG, $env->get('HTTP_ACCEPT_LANGUAGE'), $lang_parse);

			if (\count($lang_parse[1])) {
				// creates a list like "en" => 0.8
				$browser_languages = \array_combine($lang_parse[1], $lang_parse[4]);

				// sets default to 1 for any without q factor
				foreach ($browser_languages as $lang => $q) {
					if ($q === '') {
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

				if ($lang_group != $lang && isset($enabled_languages[$lang_group])) {
					$advice = $lang_group;

					break;
				}
			}

			// none found : let's search for available according to language group
			if ($advice === null) {
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
	 * @param callable $fn
	 * @param mixed    $value
	 * @param string   $lang_code
	 *
	 * @return mixed
	 */
	private static function fnExec($fn, $value, $lang_code)
	{
		if (\function_exists($fn)) {
			return \call_user_func($fn, $value, $lang_code);
		}

		return $value;
	}

	/**
	 * Translate lang key to human readable text
	 *
	 * when the lang key is invalid the supplied key is simply returned
	 *
	 * @param string $lang_key
	 * @param string $lang_code
	 * @param array  $history
	 *
	 * @throws \Exception
	 *
	 * @return mixed
	 */
	private static function getLangText($lang_key, $lang_code, $history = [])
	{
		// for 'fr-bj' lang settings should be 'lang/oz.fr-bj'
		$text = SettingsManager::get('lang/oz.' . $lang_code, $lang_key);

		if (null === $text) {
			$default = self::getDefaultLanguage();
			$text    = SettingsManager::get('lang/oz.' . $default, $lang_key);
		}

		// could be string or array or anything else
		if (\is_string($text) && \preg_match(self::PORTION_COPY_REG, $text)) {
			$in                 = [];
			$history[$lang_key] = true;

			while (\preg_match(self::PORTION_COPY_REG, $text, $in)) {
				@[$found, $lk] = $in;

				if (isset($history[$lk])) {
					throw new \Exception(\sprintf('Possible infinite loop in lang key: %s.', $lk));
				}

				$history[$lk] = true;
				$part         = self::getLangText($lk, $lang_code, $history);
				$text         = \str_replace($found, $part, $text);
			}
		}

		return null === $text ? $lang_key : $text;
	}

	/**
	 * Gets the language group of a given language
	 *
	 * ex: 'fr-bj' -> 'fr'
	 *
	 * @param string $lang
	 *
	 * @return string
	 */
	private static function getLanguageGroup($lang)
	{
		$parts = \explode('-', $lang);

		return $parts[0];
	}

	/**
	 * Sort languages by language group
	 *
	 * @param array $languages
	 *
	 * @return array
	 */
	private static function sortLanguagesByGroups(array $languages)
	{
		$groups = [];

		foreach ($languages as $lang => $value) {
			$group            = self::getLanguageGroup($lang);
			$groups[$group][] = $lang;
		}

		return $groups;
	}
}
