<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Lang;

use Exception;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Http\Environment;
use Throwable;

final class Polyglot
{
	const USER_LANG_SESSION_KEY = 'ozone.polyglot.favorite';

	private static $ready = false;

	private static $browser;

	/**
	 * To make everything ready
	 *
	 * Should be called first and once, before any other call to this class method
	 *
	 * @param \OZONE\OZ\Http\Environment $env
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
	 * @param null|\OZONE\OZ\Core\Context $context
	 *
	 * @return string
	 */
	public static function getLanguage(Context $context = null)
	{
		$browser_lang_advice = self::$browser['advice'];

		if ($context) {
			try {
				$user_lang = $context->getSession()
									 ->get(self::USER_LANG_SESSION_KEY);

				if (!empty($user_lang)) {
					return $user_lang;
				}
			} catch (Exception $e) {
				// php 5.6 and earlier
				// session not started
			} catch (Throwable $e) {
				// session not started
			}
		}

		if (!empty($browser_lang_advice)) {
			return $browser_lang_advice;
		}

		return PolyglotUtils::getDefaultLanguage();
	}

	/**
	 * Sets user preferred language
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param string                 $lang
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return bool
	 */
	public static function setUserLanguage(Context $context, $lang)
	{
		$list = PolyglotUtils::getEnabledLanguages();

		if (isset($list[$lang])) {
			$context->getSession()
					->set(self::USER_LANG_SESSION_KEY, $lang);

			return true;
		}

		return false;
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
	 * Polyglot::translate('group.based.LANG_KEY', $data);
	 * Polyglot::translate('MY_LANG_KEY', $data, 'fr');
	 * ```
	 *
	 * @param string                      $key     the human readable text key
	 * @param array                       $data    data to use for replacement
	 * @param string                      $lang    use a specific lang
	 * @param null|\OZONE\OZ\Core\Context $context the context
	 *
	 * @throws \Exception
	 *
	 * @return string human readable text or null if none found
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
