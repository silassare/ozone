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

	use OZONE\OZ\Core\Context;
	use OZONE\OZ\Http\Environment;

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
		 * @param \OZONE\OZ\Http\Environment $env
		 *
		 * @return void
		 */
		public static function init(Environment $env)
		{
			if (!self::$ready) {
				self::$ready   = true;
				self::$browser = PolyglotUtils::parseBrowserLanguage($env);
			}
		}

		/**
		 * Gets language to use.
		 *
		 * @param \OZONE\OZ\Core\Context|null $context
		 *
		 * @return string
		 */
		public static function getLanguage(Context $context = null)
		{
			$browser_lang_advice = self::$browser['advice'];

			if ($context) {
				$user_lang = $context->getSession()
									 ->get('ozone_lang:favorite');
				if (!empty($user_lang)) return $user_lang;
			}

			if (!empty($browser_lang_advice)) return $browser_lang_advice;

			return PolyglotUtils::getDefaultLanguage();
		}

		/**
		 * Sets user preferred language
		 *
		 * @param \OZONE\OZ\Core\Context $context
		 * @param string                 $lang
		 *
		 * @return void
		 */
		public static function setUserLanguage(Context $context, $lang)
		{
			$list = PolyglotUtils::getEnabledLanguages();

			if (isset($list[$lang])) {
				$context->getSession()
						->set('ozone_lang:favorite', $lang);
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
		 * @param string                      $key     the human readable text key
		 * @param array                       $data    data to use for replacement
		 * @param string                      $lang    use a specific lang
		 * @param \OZONE\OZ\Core\Context|null $context the context
		 *
		 * @return string    human readable text or null if none found
		 * @throws \Exception
		 */
		public static function translate($key, array $data = [], $lang = null, Context $context = null)
		{
			if (!PolyglotUtils::isLangKey($key)) {
				return $key;
			}

			if ($lang === null) {
				$lang = self::getLanguage($context);
			}

			return PolyglotUtils::parseText($key, $data, $lang);
		}
	}