<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Lang;

	use OTpl\OTpl;
	use OZONE\OZ\Core\SessionsData;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class Polyglot
	{
		private static $ready = false;
		private static $browser;

		/**
		 * To make everything ready
		 *
		 * Should be called first and once, before any other call to this class method
		 *
		 * @return void
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function init()
		{
			if (!self::$ready) {
				self::$ready   = true;
				self::$browser = PolyglotUtils::parseBrowserLanguage();

				// add template plugin
				// usage: @oz_lang( $key [, $data [, $format [, $lang ] ] ] )
				OTpl::addPluginAlias('oz_lang', [self::class, 'translate']);
			}
		}

		/**
		 * Gets language to use.
		 *
		 * @return String
		 * @throws \Exception
		 */
		public static function getLanguage()
		{
			$browser_lang_advice = self::$browser['advice'];
			$user_lang           = SessionsData::get('oz_lang:favorite');

			if (!empty($user_lang)) return $user_lang;

			if (!empty($browser_lang_advice)) return $browser_lang_advice;

			return PolyglotUtils::getDefaultLanguage();
		}

		/**
		 * Sets user preferred language
		 *
		 * @param string $lang
		 *
		 * @return void
		 * @throws \Exception
		 */
		public static function setUserLanguage($lang)
		{
			$list = PolyglotUtils::getEnabledLanguages();

			if (isset($list[$lang])) {
				SessionsData::set('oz_lang:favorite', $lang);
			}
		}

		/**
		 * Translate lang key to human readable text
		 *
		 * when the lang key is invalid the supplied key is simply returned
		 *
		 * ```php
		 * $data = ["name" => "Dig Ma", "age" => 18 ];
		 *
		 * Polyglot::translate('MY_LANG_KEY', $data);
		 * Polyglot::translate('MY_LANG_KEY', $data, 'fr');
		 * ```
		 *
		 * @param string $key  the human readable text key
		 * @param array  $data data to use for replacement
		 * @param string $lang use a specific lang
		 *
		 * @return String | null    human readable text or null if none found
		 * @throws \Exception
		 */
		public static function translate($key, array $data = [], $lang = null)
		{
			if (!PolyglotUtils::isLangKey($key)) {
				return $key;
			}

			if ($lang === null) {
				$lang = self::getLanguage();
			}

			return PolyglotUtils::parseText($key, $data, $lang);
		}
	}