<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Http;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Body
	 *
	 * This class represents an HTTP message body and encapsulates a
	 * streamable resource according to the PSR-7 standard.
	 *
	 * @link https://github.com/php-fig/http-message/blob/master/src/StreamInterface.php
	 */
	class Body extends Stream
	{
		/**
		 * Create new body.
		 *
		 * @param string $mode fopen mode
		 *
		 * @return \OZONE\OZ\Http\Body
		 */
		public static function create($mode = 'r+')
		{
			return new Body(fopen('php://temp', $mode));
		}

		/**
		 * Create new body with string.
		 *
		 * @param string $content content
		 * @param string $mode    fopen mode
		 *
		 * @return \OZONE\OZ\Http\Body
		 */
		public static function fromString($content, $mode = 'r+')
		{
			$body = new Body(fopen('php://temp', $mode));
			$body->write($content);

			return $body;
		}

		/**
		 * Create new body with string.
		 *
		 * @param string $path file path
		 * @param string $mode fopen mode, default to 'r' readonly mode
		 *
		 * @return \OZONE\OZ\Http\Body
		 */
		public static function fromPath($path, $mode = 'r')
		{
			return new Body(fopen($path, $mode));
		}

	}
