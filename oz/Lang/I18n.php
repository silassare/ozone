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

/**
 * Class I18n.
 */
class I18n
{
	/**
	 * Shortcut for {@see \OZONE\Core\Lang\Polyglot::translate()}.
	 *
	 * @param \OZONE\Core\Lang\I18nMessage|string $message the message
	 * @param array                               $inject  data to use for replacement
	 * @param null|string                         $lang    use a specific lang
	 * @param null|\OZONE\Core\App\Context        $context the context
	 *
	 * @return string
	 */
	public static function t(I18nMessage|string $message, array $inject = [], string $lang = null, Context $context = null): string
	{
		if (\is_string($message)) {
			return Polyglot::translate($message, $inject, $lang, $context);
		}

		$inject = \array_merge($message->getInject(), $inject);

		return Polyglot::translate($message->getText(), $inject, $lang, $context);
	}

	/**
	 * Creates an instance of {@see \OZONE\Core\Lang\I18nMessage}.
	 *
	 * @param string $message
	 * @param array  $inject
	 *
	 * @return \OZONE\Core\Lang\I18nMessage
	 */
	public static function m(string $message, array $inject): I18nMessage
	{
		return new I18nMessage($message, $inject);
	}
}
