<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Lang;

	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Core\SessionsData;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class Polyglot
	{
		private static $ready = false;
		private static $browser;
		private static $user_lang;
		private static $default_lang;

		/**
		 * To make everything ready
		 *
		 * Should be called first and once, before any other call to this class method
		 *
		 * @return void
		 */
		public static function init()
		{
			if (!self::$ready) {
				self::$ready        = true;
				self::$browser      = self::parseBrowserLanguage();
				self::$default_lang = self::getAvailablesLanguages('default');
				self::$user_lang    = SessionsData::get('oz_lang:prefered');

				// add template plugin
				// usage: @oz_lang( $key [, $data [, $lang ] ] )
				\OTpl::addPluginAlias('oz_lang', ['OZONE\OZ\Lang\Polyglot', 'translate']);
			}
		}

		/**
		 * Gets availables languages list or check if a given one is defined
		 *
		 * @param string $key
		 *
		 * @return array | null
		 */
		public static function getAvailablesLanguages($key = null)
		{
			return SettingsManager::get('lang/oz.lang.list', $key);
		}

		/**
		 * Gets enabled languages list from availables languages
		 *
		 * @return array
		 */
		public static function getEnabledLanguages()
		{
			$list   = self::getAvailablesLanguages();
			$result = [];

			if (is_array($list)) {
				foreach ($list as $lang => $value) {
					if ($value === true) $result[$lang] = true;
				}
			}

			return $result;
		}

		/**
		 * Gets language to use as user language
		 *
		 * @return String
		 */
		public static function getUserLanguage()
		{
			$browser_lang_andvice = self::$browser['advice'];

			if (!empty(self::$user_lang)) return self::$user_lang;

			if (!empty($browser_lang_andvice)) return $browser_lang_andvice;

			return self::$default_lang;
		}

		/**
		 * Sets user prefered language
		 *
		 * @param string $lang
		 *
		 * @return void
		 */
		public static function setUserLanguage($lang)
		{
			$l = self::getEnabledLanguages();

			if (isset($l[$lang])) {
				SessionsData::set('oz_lang:prefered', $lang);
			}
		}

		/**
		 * Checks if we have a valid lang key
		 *
		 * @param string $key the key to check
		 *
		 * @return bool     true if
		 */
		public static function isLangKey($key)
		{
			return preg_match("#^[A-Z0-9_]+$#", $key);
		}

		/**
		 * translate lang key to human readable text
		 *
		 * when the lang key is invalid the supplied key is simply returned
		 *
		 * @param string $key  the human readable text key
		 * @param array  $data data to use for replacement
		 * @param string $lang use a specific lang
		 *
		 * @return String | null    human readable text or null if none found
		 */
		public static function translate($key, $data = [], $lang = null)
		{
			if (!self::isLangKey($key)) {
				return $key;
			}

			if ($lang === null) {
				$lang = self::getUserLanguage();
			}

			// for 'fr-bj' lang settings should be 'lang/oz.fr-bj'
			$l = SettingsManager::get('lang/oz.' . $lang, $key);

			// could be string or array or anything else
			//if (is_string($l)) {
				// TODO
				// $l = self::parse( $l, $data , $lang );
			//}

			return $l;
		}

		/**
		 * parse user browser 'Accept-Language' header and advice
		 * for the best to use according to availables languages
		 *
		 * @return array
		 */
		private static function parseBrowserLanguage()
		{
			$browser_langs = [];
			$enabled_langs = self::getEnabledLanguages();
			$advice        = null;

			if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
				// break up string into pieces (languages and q factors)
				preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

				if (count($lang_parse[1])) {
					// create a list like "en" => 0.8
					$browser_langs = array_combine($lang_parse[1], $lang_parse[4]);

					// set default to 1 for any without q factor
					foreach ($browser_langs as $lang => $q) {
						if ($q === '') $browser_langs[$lang] = 1;
					}

					// sort list based on value	
					arsort($browser_langs, SORT_NUMERIC);
				}
			}

			if (count($browser_langs) AND count($enabled_langs)) {
				// look through sorted list and use first one that matches our languages
				foreach ($browser_langs as $lang => $q) {
					// user language is available
					if (isset($enabled_langs[$q])) {
						$advice = $lang;
						break;
					}

					// for 'fr-bj' choose 'fr' if available
					$lang_group = self::getLanguageGroup($lang);
					if ($lang_group != $lang AND isset($enabled_langs[$lang_group])) {
						$advice = $lang_group;
						break;
					}
				}

				// none found : let's search for availables according to language group
				if ($advice === null) {
					$browser_langs_groups = self::sortLanguagesByGroups($browser_langs);
					$enabled_langs_groups = self::sortLanguagesByGroups($enabled_langs);

					foreach ($browser_langs_groups as $group => $list) {
						if (isset($enabled_langs_groups[$group])) {
							// get the first language in this group
							$advice = $enabled_langs_groups[$group][0];
							break;
						}
					}
				}
			}

			return ['langs' => $browser_langs, 'advice' => $advice];
		}

		/**
		 * sort languages by language group
		 *
		 * @param array $langs
		 *
		 * @return array
		 */
		private static function sortLanguagesByGroups(array $langs)
		{
			$langs_groups = [];

			foreach ($langs as $lang => $value) {
				$group                  = self::getLanguageGroup($lang);
				$langs_groups[$group][] = $lang;
			}

			return $langs_groups;
		}

		/**
		 * Gets the language group of a given language
		 * ex: 'fr-bj' -> 'fr'
		 *
		 * @param string $lang
		 *
		 * @return string
		 */
		private static function getLanguageGroup($lang)
		{
			$parts = explode('-', $lang);

			return $parts[0];
		}
	}