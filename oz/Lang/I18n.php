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

namespace OZONE\OZ\Lang;

use OZONE\OZ\Core\Context;

/**
 * Class I18n.
 */
class I18n
{
	/**
	 * Shortcut for {@see \OZONE\OZ\Lang\Polyglot::translate()}.
	 *
	 * @param string                      $key     the human readable text key
	 * @param array                       $inject  data to use for replacement
	 * @param null|string                 $lang    use a specific lang
	 * @param null|\OZONE\OZ\Core\Context $context the context
	 *
	 * @return string
	 */
	public static function t(string $key, array $inject = [], string $lang = null, Context $context = null): string
	{
		return Polyglot::translate($key, $inject, $lang, $context);
	}
}
